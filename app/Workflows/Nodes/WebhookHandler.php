<?php

namespace App\Workflows\Nodes;

use App\Enums\NodeType;
use App\Workflows\Definition\ValidationScope;
use App\Workflows\Webhooks\WebhookSender;
use App\Workflows\Webhooks\WebhookUrlGuard;

/**
 * Sends the run's details to another system as signed JSON.
 *
 *   { url: "https://…", fields: [{ key: "po_number", value: "{{ input.po }}" }] }
 *
 * The body always carries the workflow, run, record snapshot and input; the
 * fields add values of the author's choosing under "data".
 */
class WebhookHandler extends BaseHandler
{
    public const string EVENT = 'workflow.webhook';

    public const int MAX_FIELDS = 20;

    public function __construct(
        private readonly WebhookSender $sender,
        private readonly WebhookUrlGuard $guard,
    ) {}

    public function type(): NodeType
    {
        return NodeType::Webhook;
    }

    public function transactional(): bool
    {
        return false;
    }

    public function validate(array $config, ValidationScope $scope): array
    {
        $url = self::text($config, 'url');

        if ($url === '') {
            return ['Enter the address to send to.'];
        }

        $errors = [];

        if (($problem = $this->guard->problem($url)) !== null) {
            $errors[] = $problem;
        }

        $fields = is_array($config['fields'] ?? null) ? $config['fields'] : [];

        if (count($fields) > self::MAX_FIELDS) {
            $errors[] = 'Send at most '.self::MAX_FIELDS.' extra values.';
        }

        $keys = [];

        foreach ($fields as $field) {
            $key = is_array($field) && is_string($field['key'] ?? null) ? $field['key'] : '';
            $value = is_array($field) && is_string($field['value'] ?? null) ? $field['value'] : '';

            if (! preg_match('/^[A-Za-z][A-Za-z0-9_]{0,39}$/', $key)) {
                $errors[] = 'Value names use letters, numbers and underscores, starting with a letter.';
            } elseif (isset($keys[$key])) {
                $errors[] = "Two values are named \"{$key}\".";
            }

            $keys[$key] = true;

            if (mb_strlen($value) > 1000) {
                $errors[] = 'Keep each value under 1,000 characters.';
            }

            $errors = [...$errors, ...self::templateErrors($value, $scope, "value \"{$key}\"")];
        }

        return array_values(array_unique($errors));
    }

    public function execute(StepContext $step): StepResult
    {
        $config = $step->config();
        $data = [];

        foreach (is_array($config['fields'] ?? null) ? $config['fields'] : [] as $field) {
            if (is_array($field) && is_string($field['key'] ?? null)) {
                $data[$field['key']] = $step->render(is_string($field['value'] ?? null) ? $field['value'] : '', 1000);
            }
        }

        $payload = [
            'event' => self::EVENT,
            'sent_at' => now()->toIso8601String(),
            'organization' => ['id' => $step->organization->id, 'name' => $step->organization->name],
            'workflow' => [
                'id' => $step->run->workflow_id,
                'name' => $step->workflowName(),
                'version' => data_get($step->context, 'workflow.version'),
            ],
            'run' => [
                'id' => $step->run->id,
                'reference' => $step->run->reference(),
                'url' => data_get($step->context, 'run.url'),
            ],
            'step' => ['id' => $step->node['id'], 'label' => $step->label()],
            'subject' => $step->context['subject'] ?? null,
            'input' => $step->context['input'] ?? [],
            'data' => $data === [] ? null : $data,
        ];

        $result = $this->sender->send($step->organization, self::EVENT, self::text($config, 'url'), $payload, $step->step);

        if (! $result->delivered) {
            throw new StepFailed($result->error ?? 'The webhook could not be delivered.', $result->retryable);
        }

        return StepResult::complete('next', [
            'status' => $result->status,
            'delivery_id' => $result->delivery?->id,
            'delivery_key' => $result->delivery?->delivery_key,
        ]);
    }
}
