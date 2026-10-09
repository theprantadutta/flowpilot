<?php

use App\Enums\AiBriefStatus;
use App\Enums\PurchaseRequestStatus;
use App\Enums\Role;
use App\Jobs\GenerateOperationsBrief;
use App\Models\AiBrief;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\PurchaseRequest;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

beforeEach(function () {
    config([
        'ai.provider' => 'freeway',
        'ai.providers.freeway.url' => 'https://freeway.test',
        'ai.providers.freeway.key' => 'fw_test_key',
        'ai.providers.freeway.model' => 'paid:premium',
        'ai.providers.freeway.reasoning_effort' => 'low',
    ]);
});

/**
 * A Freeway answer whose message content is the given brief.
 *
 * @param  array<string, mixed>|string  $brief
 */
function freewayAnswer(array|string $brief, int $status = 200): void
{
    Http::fake(['freeway.test/*' => Http::response($status === 200 ? [
        'id' => 'chatcmpl-1',
        'object' => 'chat.completion',
        'model' => 'openai/gpt-5.6-sol',
        'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => is_string($brief) ? $brief : json_encode($brief)], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 812, 'completion_tokens' => 164, 'total_tokens' => 976],
    ] : ['detail' => 'OpenRouter API error: TooManyRequests'], $status)]);
}

function writeBriefFor(User $user, Organization $organization): AiBrief
{
    $brief = inTenant($organization, fn () => AiBrief::query()->create(['user_id' => $user->id]));

    dispatch_sync(new GenerateOperationsBrief($organization->id, $brief->id));

    return inTenant($organization, fn () => $brief->refresh());
}

it('queues a brief for members who may use AI', function () {
    Queue::fake();
    $organization = Organization::factory()->create();

    actingAs($organization->owner)->post(route('ai.brief.store', $organization))->assertRedirect()->assertSessionHasNoErrors();
    // Asking again while it is being written does not queue a second one.
    actingAs($organization->owner)->post(route('ai.brief.store', $organization))->assertRedirect();

    expect(inTenant($organization, fn () => AiBrief::query()->count()))->toBe(1);
    Queue::assertPushed(GenerateOperationsBrief::class, 1);

    actingAs(memberIn($organization, Role::Employee))->post(route('ai.brief.store', $organization))->assertForbidden();
});

it('stays off until the provider has a key', function () {
    config(['ai.providers.freeway.key' => null]);
    $organization = Organization::factory()->create();

    actingAs($organization->owner)->get(route('overview', $organization))
        ->assertInertia(fn (Assert $page) => $page->where('ai.enabled', false)->where('brief', null));
    actingAs($organization->owner)->post(route('ai.brief.store', $organization))->assertSessionHasErrors('brief');
});

it('writes a brief from the member\'s own facts and links every line to a record', function () {
    $organization = Organization::factory()->create();
    $other = Organization::factory()->create();
    $owner = $organization->owner;

    $task = inTenant($organization, fn () => Task::factory()->overdue()->create(['organization_id' => $organization->id, 'assignee_id' => $owner->id, 'title' => 'Calibrate line 2 sensors']));
    inTenant($other, fn () => Task::factory()->overdue()->create(['organization_id' => $other->id, 'title' => 'Secret project from another company']));

    freewayAnswer([
        'headline' => 'One thing needs your attention.',
        'items' => [['title' => 'Calibrate the line 2 sensors', 'detail' => 'It is two days late.', 'severity' => 'high', 'facts' => ["task:{$task->reference()}"]]],
        'actions' => [['label' => 'Open the task', 'fact' => "task:{$task->reference()}"]],
    ]);

    $brief = writeBriefFor($owner, $organization);

    expect($brief->status)->toBe(AiBriefStatus::Completed)
        ->and($brief->used_fallback)->toBeFalse()
        ->and($brief->model)->toBe('openai/gpt-5.6-sol')
        ->and($brief->prompt_tokens)->toBe(812)
        ->and($brief->completion_tokens)->toBe(164);

    Http::assertSent(function (Request $request) use ($task) {
        $body = $request->data();

        return $request->url() === 'https://freeway.test/chat/completions'
            && $request->hasHeader('X-Api-Key', 'fw_test_key')
            && ! $request->hasHeader('Authorization')
            && $body['model'] === 'paid:premium'
            && $body['reasoning'] === ['effort' => 'low']
            && ! array_key_exists('stream', $body)
            && str_contains($body['messages'][1]['content'], "task:{$task->reference()}")
            && ! str_contains($body['messages'][1]['content'], 'Secret project');
    });

    actingAs($owner)->get(route('overview', $organization))
        ->assertInertia(fn (Assert $page) => $page
            ->where('ai.enabled', true)
            ->where('brief.headline', 'One thing needs your attention.')
            ->where('brief.items.0.links.0.url', route('tasks.show', [$organization, $task]))
            ->where('brief.actions.0.url', route('tasks.show', [$organization, $task])));
});

it('only puts in front of the model what the member may see', function () {
    $organization = Organization::factory()->create();
    $finance = memberIn($organization, Role::Finance);

    inTenant($organization, function () use ($organization) {
        InventoryItem::factory()->create(['organization_id' => $organization->id, 'current_stock' => 0, 'reorder_point' => 5, 'sku' => 'GLV-100']);
        PurchaseRequest::factory()->create(['organization_id' => $organization->id, 'status' => PurchaseRequestStatus::Submitted, 'item_name' => 'Torque wrench']);
    });

    freewayAnswer(['headline' => 'Stock is out.', 'items' => [['title' => 'Gloves are out', 'detail' => 'None left.', 'severity' => 'high', 'facts' => ['item:GLV-100']]], 'actions' => []]);

    writeBriefFor($finance, $organization);

    // Finance can see stock but does not manage purchases, so pending purchase requests stay out.
    Http::assertSent(fn (Request $request) => str_contains($request->data()['messages'][1]['content'], 'item:GLV-100')
        && ! str_contains($request->data()['messages'][1]['content'], 'Torque wrench'));
});

it('falls back to a plain brief when the model fails or answers badly', function (int $status, string|array $answer, string $reason) {
    $organization = Organization::factory()->create();
    inTenant($organization, fn () => Task::factory()->overdue()->create(['organization_id' => $organization->id, 'assignee_id' => $organization->owner_id]));

    freewayAnswer($answer, $status);

    $brief = writeBriefFor($organization->owner, $organization);

    expect($brief->status)->toBe(AiBriefStatus::Completed)
        ->and($brief->used_fallback)->toBeTrue()
        ->and($brief->error)->toContain($reason)
        ->and($brief->content['headline'] ?? null)->toBe('One thing needs your attention.')
        ->and($brief->content['items'][0]['severity'] ?? null)->toBe('high');
})->with([
    'gateway error' => [502, '', 'could not be reached'],
    'not json' => [200, 'Sorry, I cannot help with that.', 'not JSON'],
    'invented records' => [200, ['headline' => 'Hi', 'items' => [['title' => 'A', 'detail' => 'B', 'severity' => 'high', 'facts' => ['task:T-9999']]]], 'did not point at any'],
]);

it('does not call the model when nothing needs attention', function () {
    Http::fake();
    $organization = Organization::factory()->create();

    $brief = writeBriefFor($organization->owner, $organization);

    expect($brief->status)->toBe(AiBriefStatus::Completed)
        ->and($brief->content['headline'] ?? null)->toBe('Nothing needs your attention right now.');
    Http::assertNothingSent();
});

it('limits how many briefs a member can ask for each hour', function () {
    Queue::fake();
    config(['ai.brief.per_member_per_hour' => 2]);
    $organization = Organization::factory()->create();
    RateLimiter::clear("ai-brief:member:{$organization->id}:{$organization->owner_id}");

    foreach ([1, 2] as $attempt) {
        actingAs($organization->owner)->post(route('ai.brief.store', $organization))->assertSessionHasNoErrors();
        inTenant($organization, fn () => AiBrief::query()->update(['status' => AiBriefStatus::Completed->value]));
    }

    actingAs($organization->owner)->post(route('ai.brief.store', $organization))->assertSessionHasErrors('brief');
});

it('does not write a brief for a member who has lost access to AI', function () {
    Http::fake();
    $organization = Organization::factory()->create();
    $manager = memberIn($organization, Role::Manager);
    $brief = inTenant($organization, fn () => AiBrief::query()->create(['user_id' => $manager->id]));
    inTenant($organization, fn () => $organization->memberships()->where('user_id', $manager->id)->update(['role' => Role::Employee]));

    dispatch_sync(new GenerateOperationsBrief($organization->id, $brief->id));

    expect(inTenant($organization, fn () => $brief->refresh()->status))->toBe(AiBriefStatus::Failed);
    Http::assertNothingSent();
});

it('gives up on stuck briefs and deletes old ones', function () {
    $organization = Organization::factory()->create();

    [$stuck, $old] = inTenant($organization, fn () => [
        AiBrief::factory()->create(['organization_id' => $organization->id, 'user_id' => $organization->owner_id, 'created_at' => now()->subHour()]),
        AiBrief::factory()->completed()->create(['organization_id' => $organization->id, 'user_id' => $organization->owner_id, 'created_at' => now()->subDays(40)]),
    ]);

    artisan('ai:prune-briefs')->assertSuccessful();

    expect(AiBrief::withoutOrganizationScope()->find($stuck->id)?->status)->toBe(AiBriefStatus::Failed)
        ->and(AiBrief::withoutOrganizationScope()->find($old->id))->toBeNull();
});

it('tells the model a member cannot approve their own purchase request', function () {
    $organization = Organization::factory()->create();

    inTenant($organization, fn () => PurchaseRequest::factory()->create([
        'organization_id' => $organization->id,
        'status' => PurchaseRequestStatus::Submitted,
        'requester_id' => $organization->owner_id,
        'item_name' => 'Torque wrench',
    ]));

    freewayAnswer(['headline' => 'One request is waiting.', 'items' => [['title' => 'Torque wrench', 'detail' => 'Waiting.', 'severity' => 'medium', 'facts' => ['purchase:PR-'.inTenant($organization, fn () => PurchaseRequest::query()->value('number'))]]], 'actions' => []]);

    writeBriefFor($organization->owner, $organization);

    Http::assertSent(fn (Request $request) => str_contains($request->data()['messages'][1]['content'], 'another inventory manager to approve it'));
});
