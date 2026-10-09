<?php

namespace App\Workflows\Nodes;

use App\Enums\NodeType;
use App\Models\User;
use App\Notifications\WorkflowMessageNotification;
use App\Workflows\Definition\ValidationScope;
use App\Workflows\Support\People;
use Illuminate\Support\Facades\Notification;

/**
 * Tells people something in their notification center (and by email when
 * their preferences say so).
 *
 *   { recipients: [...], title: "…", message: "…" }
 */
class NotificationHandler extends BaseHandler
{
    public function __construct(private readonly People $people) {}

    public function type(): NodeType
    {
        return NodeType::Notification;
    }

    public function validate(array $config, ValidationScope $scope): array
    {
        $errors = People::validate($config['recipients'] ?? null, $scope->memberIds, $scope->personFields(), $scope->subjectIsWork());
        $title = self::text($config, 'title');
        $message = self::text($config, 'message');

        if ($title === '') {
            $errors[] = 'Write a title for the notification.';
        } elseif (mb_strlen($title) > 150) {
            $errors[] = 'Keep the title under 150 characters.';
        }

        if (mb_strlen($message) > 2000) {
            $errors[] = 'Keep the message under 2,000 characters.';
        }

        return [
            ...$errors,
            ...self::templateErrors($title, $scope, 'title'),
            ...self::templateErrors($message, $scope, 'message'),
        ];
    }

    public function execute(StepContext $step): StepResult
    {
        $config = $step->config();
        $recipients = $this->people->resolve($step->organization, $config['recipients'] ?? [], $step->context);

        $title = $step->render(self::text($config, 'title'), 150);
        $message = $step->render(self::text($config, 'message'), 2000);
        $link = data_get($step->context, 'subject.url') ?? data_get($step->context, 'run.url');

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new WorkflowMessageNotification(
                $title !== '' ? $title : $step->label(),
                $message !== '' ? $message : null,
                is_string($link) ? $link : null,
                $step->workflowName(),
            ));
        }

        return StepResult::complete('next', [
            'title' => $title,
            'sent_to' => $recipients->map(fn (User $user): string => $user->name)->values()->all(),
            ...($recipients->isEmpty() ? ['note' => 'Nobody matched the recipients, so no one was notified.'] : []),
        ]);
    }
}
