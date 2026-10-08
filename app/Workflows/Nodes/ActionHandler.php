<?php

namespace App\Workflows\Nodes;

use App\Actions\Issues\UpdateIssue;
use App\Actions\Tasks\UpdateTask;
use App\Enums\NodeType;
use App\Models\Task;
use App\Models\User;
use App\Notifications\WorkflowEmailNotification;
use App\Workflows\Definition\ValidationScope;
use App\Workflows\Support\People;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Small, common actions:
 *
 *   send_email  { action: "send_email", recipients: [...], subject: "…", body: "…" }
 *   add_tag     { action: "add_tag", tag: "escalated" }
 */
class ActionHandler extends BaseHandler
{
    public const array ACTIONS = [
        'send_email' => 'Send an email',
        'add_tag' => 'Add a tag to the record',
    ];

    public function __construct(
        private readonly People $people,
        private readonly UpdateTask $updateTask,
        private readonly UpdateIssue $updateIssue,
    ) {}

    public function type(): NodeType
    {
        return NodeType::Action;
    }

    public function validate(array $config, ValidationScope $scope): array
    {
        return match ($config['action'] ?? null) {
            'send_email' => $this->validateEmail($config, $scope),
            'add_tag' => $this->validateTag($config, $scope),
            default => ['Choose what this step does.'],
        };
    }

    public function execute(StepContext $step): StepResult
    {
        $config = $step->config();

        return match ($config['action'] ?? null) {
            'send_email' => $this->sendEmail($config, $step),
            'add_tag' => $this->addTag($config, $step),
            default => throw StepFailed::permanent('This step has no action.'),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private function validateEmail(array $config, ValidationScope $scope): array
    {
        $errors = People::validate($config['recipients'] ?? null, $scope->memberIds, $scope->personFields(), $scope->hasSubject());
        $subject = self::text($config, 'subject');
        $body = self::text($config, 'body');

        if ($subject === '') {
            $errors[] = 'Write a subject for the email.';
        } elseif (mb_strlen($subject) > 150) {
            $errors[] = 'Keep the subject under 150 characters.';
        }

        if ($body === '') {
            $errors[] = 'Write the email.';
        } elseif (mb_strlen($body) > 5000) {
            $errors[] = 'Keep the email under 5,000 characters.';
        }

        return [
            ...$errors,
            ...self::templateErrors($subject, $scope, 'subject'),
            ...self::templateErrors($body, $scope, 'email'),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private function validateTag(array $config, ValidationScope $scope): array
    {
        if (! $scope->hasSubject()) {
            return ['This trigger has no record to tag. Use a trigger about a task or an issue.'];
        }

        $tag = self::text($config, 'tag');

        return match (true) {
            $tag === '' => ['Enter the tag to add.'],
            mb_strlen($tag) > 30 => ['Keep tags under 30 characters.'],
            default => self::templateErrors($tag, $scope, 'tag'),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function sendEmail(array $config, StepContext $step): StepResult
    {
        $recipients = $this->people->resolve($step->organization, $config['recipients'] ?? [], $step->context);
        $subject = $step->render(self::text($config, 'subject'), 150);
        $link = data_get($step->context, 'subject.url') ?? data_get($step->context, 'run.url');

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new WorkflowEmailNotification(
                $subject !== '' ? $subject : $step->label(),
                $step->render(self::text($config, 'body'), 5000),
                is_string($link) ? $link : null,
                $step->workflowName(),
            ));
        }

        return StepResult::complete('next', [
            'subject' => $subject,
            'emailed' => $recipients->map(fn (User $user): string => $user->name)->values()->all(),
            ...($recipients->isEmpty() ? ['note' => 'Nobody matched the recipients, so no email was sent.'] : []),
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function addTag(array $config, StepContext $step): StepResult
    {
        $subject = $step->subjectOrFail();
        $tag = Str::of($step->render(self::text($config, 'tag'), 60))->squish()->lower()->limit(30, '')->toString();

        if ($tag === '') {
            throw StepFailed::permanent('The tag came out empty for this record.');
        }

        $tags = $subject->tags ?? [];

        if (in_array($tag, $tags, true)) {
            return StepResult::complete('next', ['record' => $subject->reference(), 'tag' => $tag, 'note' => 'The record already had this tag.']);
        }

        if (count($tags) >= 10) {
            throw StepFailed::permanent("{$subject->reference()} already has 10 tags, the most a record can have.");
        }

        $attributes = ['tags' => [...$tags, $tag]];

        $subject instanceof Task
            ? $this->updateTask->handle($subject, null, $attributes, $step->run)
            : $this->updateIssue->handle($subject, null, $attributes, $step->run);

        return StepResult::complete('next', ['record' => $subject->reference(), 'tag' => $tag]);
    }
}
