<?php

namespace App\Support\Ai;

/**
 * The model's answer and what it cost.
 */
final readonly class AiCompletion
{
    public function __construct(
        public string $content,
        public ?string $model,
        public ?int $promptTokens,
        public ?int $completionTokens,
        public int $durationMs,
    ) {}
}
