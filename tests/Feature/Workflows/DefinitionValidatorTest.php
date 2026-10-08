<?php

use App\Enums\Role;
use App\Models\Organization;
use App\Models\WorkflowVersion;
use App\Workflows\Definition\DefinitionValidator;
use App\Workflows\Definition\WorkflowDefinition;
use App\Workflows\Templates\WorkflowTemplates;

/**
 * @param  list<array<string, mixed>>  $steps
 * @param  list<array<string, string>>  $paths
 * @return list<string>
 */
function problems(Organization $organization, array $steps, array $paths, string $trigger = 'manual'): array
{
    return inTenant($organization, fn () => array_column(
        app(DefinitionValidator::class)->validate(WorkflowDefinition::fromArray(['nodes' => $steps, 'edges' => $paths]), $trigger, $organization),
        'message',
    ));
}

it('accepts a connected graph', function () {
    $organization = Organization::factory()->create();

    expect(problems($organization, [step('trigger', 'trigger'), step('done', 'end')], [path('trigger', 'done')]))->toBe([]);
});

it('needs exactly one trigger, connected to something', function () {
    $organization = Organization::factory()->create();

    expect(problems($organization, [step('done', 'end')], []))->toBe(['Add a trigger to say what starts the workflow.'])
        ->and(problems($organization, [step('trigger', 'trigger'), step('another', 'trigger')], [path('trigger', 'another')]))
        ->toContain('A workflow has exactly one trigger.')
        ->and(problems($organization, [step('trigger', 'trigger')], []))->toBe(['Connect the trigger to the first step.']);
});

it('rejects loops, unreachable steps and paths a step does not have', function () {
    $organization = Organization::factory()->create();

    $loop = problems($organization, [
        step('trigger', 'trigger'),
        step('a', 'delay', ['amount' => 1, 'unit' => 'hours']),
        step('b', 'delay', ['amount' => 1, 'unit' => 'hours']),
    ], [path('trigger', 'a'), path('a', 'b'), path('b', 'a')]);

    $loose = problems($organization, [step('trigger', 'trigger'), step('done', 'end'), step('orphan', 'end')], [path('trigger', 'done')]);

    $wrongPath = problems($organization, [step('trigger', 'trigger'), step('done', 'end'), step('other', 'end')], [
        path('trigger', 'done', 'approved'),
        path('trigger', 'other'),
    ]);

    $twoNext = problems($organization, [step('trigger', 'trigger'), step('a', 'end'), step('b', 'end')], [path('trigger', 'a'), path('trigger', 'b')]);

    expect($loop)->toContain('Workflows cannot loop back to an earlier step.')
        ->and($loose)->toBe(['Connect this step, or remove it.'])
        ->and($wrongPath)->toContain('A connection leaves this step from a path it does not have.')
        ->and($twoNext)->toContain('Each path out of a step can lead to only one next step.');
});

it('checks each step against the trigger, members and projects', function () {
    $organization = Organization::factory()->create();
    $outsider = memberIn(Organization::factory()->create(), Role::Manager);

    $problems = problems($organization, [
        step('trigger', 'trigger', ['inputs' => [['key' => 'amount', 'label' => 'Amount', 'type' => 'money']]]),
        step('check', 'condition', ['match' => 'all', 'rules' => [['field' => 'subject.priority', 'operator' => 'equals', 'value' => 'high']]]),
        step('tell', 'notification', ['recipients' => [['type' => 'member', 'id' => $outsider->id]], 'title' => 'About {{ input.nothing }}']),
        step('fix', 'update_record', ['field' => 'status', 'value' => 'done']),
        step('send', 'webhook', ['url' => 'http://10.0.0.5/hook']),
        step('make', 'create_record', ['record' => 'task', 'title' => 'Follow up', 'project' => '0199a1b2-0000-7000-8000-000000000000']),
    ], [path('trigger', 'check'), path('check', 'tell', 'true'), path('check', 'fix', 'false'), path('tell', 'send'), path('send', 'make')]);

    expect($problems)->toContain(
        'The field "subject.priority" is not available for this trigger.',
        'A chosen person is no longer a member.',
        'The title uses {{ input.nothing }}, which this workflow does not have.',
        'This trigger has no record to update. Use a trigger about a task or an issue.',
        'Webhooks must use https.',
        'The chosen project no longer exists.',
    );
});

it('ships templates that are ready to publish, apart from the webhook address', function () {
    $organization = Organization::factory()->create();
    $templates = app(WorkflowTemplates::class);

    foreach ($templates->all() as $template) {
        $problems = problems($organization, $template['definition']['nodes'], $template['definition']['edges'], $template['trigger']);

        match ($template['key']) {
            'blank' => expect($problems)->toBe(['Connect the trigger to the first step.']),
            'completed-task-webhook' => expect($problems)->toBe(['Enter the address to send to.']),
            default => expect($problems)->toBe([], "Template {$template['key']} has problems: ".implode(' ', $problems)),
        };
    }
});

it('never lets a published version change', function () {
    $organization = Organization::factory()->create();
    $workflow = publishedWorkflow($organization, [step('trigger', 'trigger'), step('done', 'end')], [path('trigger', 'done')]);

    inTenant($organization, function () use ($workflow) {
        $version = WorkflowVersion::query()->where('workflow_id', $workflow->id)->firstOrFail();

        expect(fn () => $version->forceFill(['notes' => 'Edited later'])->save())
            ->toThrow(LogicException::class, 'Published workflow versions cannot be changed.');
    });
});
