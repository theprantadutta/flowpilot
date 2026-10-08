<?php

namespace App\Workflows\Webhooks;

use App\Models\WebhookDelivery;

/**
 * How a delivery went, and whether trying again could help.
 */
final readonly class WebhookResult
{
    public function __construct(
        public bool $delivered,
        public bool $retryable,
        public ?int $status = null,
        public ?string $body = null,
        public ?string $error = null,
        public ?WebhookDelivery $delivery = null,
    ) {}

    public static function fromStatus(int $status, string $body): self
    {
        if ($status >= 200 && $status < 300) {
            return new self(true, false, $status, $body);
        }

        // Timeouts, rate limits and server errors may clear up; other client errors will not.
        $retryable = $status >= 500 || in_array($status, [408, 425, 429], true);

        return new self(false, $retryable, $status, $body, "The receiving system answered {$status}.");
    }

    public static function unreachable(string $reason): self
    {
        return new self(false, true, error: "The receiving system could not be reached: {$reason}");
    }

    public static function rejected(string $reason): self
    {
        return new self(false, false, error: $reason);
    }

    public function forDelivery(WebhookDelivery $delivery): self
    {
        return new self($this->delivered, $this->retryable, $this->status, $this->body, $this->error, $delivery);
    }
}
