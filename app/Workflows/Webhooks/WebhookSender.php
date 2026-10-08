<?php

namespace App\Workflows\Webhooks;

use App\Models\Organization;
use App\Models\WebhookDelivery;
use App\Models\WorkflowStepRun;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Sends signed JSON to another system and records the delivery.
 *
 * Receivers verify a delivery with the organization's signing secret:
 *
 *   X-FlowPilot-Signature: t=<unix time>,v1=<hex HMAC-SHA256 of "<t>.<raw body>">
 *
 * Every attempt of one delivery carries the same Idempotency-Key, so a
 * receiver can ignore a retry it already processed.
 */
class WebhookSender
{
    public const string USER_AGENT = 'FlowPilot-Webhooks/1.0';

    public function __construct(private readonly WebhookUrlGuard $guard) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(Organization $organization, string $event, string $url, array $payload, ?WorkflowStepRun $step = null): WebhookResult
    {
        $delivery = $this->delivery($event, $url, $payload, $step);
        $delivery->forceFill(['attempts' => $delivery->attempts + 1])->save();

        try {
            $target = $this->guard->check($url);
        } catch (UnsafeWebhookUrl $exception) {
            return $this->finish($delivery, WebhookResult::rejected($exception->getMessage()));
        }

        $body = (string) json_encode([...$delivery->payload, 'id' => $delivery->delivery_key], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = now()->getTimestamp();
        $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $organization->webhookSecret());
        $started = hrtime(true);

        try {
            $response = Http::withOptions([
                'allow_redirects' => false,
                'curl' => [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$target['ip']}"]],
            ])
                ->timeout((int) config('flowpilot.webhooks.timeout_seconds', 10))
                ->connectTimeout((int) config('flowpilot.webhooks.connect_timeout_seconds', 5))
                ->withUserAgent(self::USER_AGENT)
                ->withHeaders([
                    'X-FlowPilot-Event' => $event,
                    'X-FlowPilot-Delivery' => $delivery->delivery_key,
                    'X-FlowPilot-Signature' => "t={$timestamp},v1={$signature}",
                    'Idempotency-Key' => $delivery->delivery_key,
                ])
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (ConnectionException $exception) {
            return $this->finish($delivery, WebhookResult::unreachable(Str::limit($exception->getMessage(), 300)), $started);
        }

        $excerpt = Str::limit($response->body(), (int) config('flowpilot.webhooks.max_response_bytes', 2000));

        return $this->finish($delivery, WebhookResult::fromStatus($response->status(), $excerpt), $started);
    }

    /**
     * One delivery row per step, reused by retries so the key stays the same.
     *
     * @param  array<string, mixed>  $payload
     */
    private function delivery(string $event, string $url, array $payload, ?WorkflowStepRun $step): WebhookDelivery
    {
        $existing = $step ? WebhookDelivery::query()->where('workflow_step_run_id', $step->id)->first() : null;

        return $existing ?? WebhookDelivery::query()->create([
            'workflow_step_run_id' => $step?->id,
            'event' => $event,
            'url' => $url,
            'payload' => $payload,
            'delivery_key' => (string) Str::uuid7(),
        ]);
    }

    private function finish(WebhookDelivery $delivery, WebhookResult $result, ?int $started = null): WebhookResult
    {
        $delivery->forceFill([
            'status' => $result->delivered ? WebhookDelivery::STATUS_DELIVERED : WebhookDelivery::STATUS_FAILED,
            'response_status' => $result->status,
            'response_body' => $result->body,
            'error' => $result->error,
            'duration_ms' => $started ? (int) ((hrtime(true) - $started) / 1_000_000) : null,
            'delivered_at' => $result->delivered ? now() : null,
        ])->save();

        return $result->forDelivery($delivery);
    }
}
