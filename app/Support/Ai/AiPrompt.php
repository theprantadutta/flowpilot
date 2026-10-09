<?php

namespace App\Support\Ai;

/**
 * What is sent to the model: fixed instructions plus the data to work from.
 */
final readonly class AiPrompt
{
    public function __construct(
        public string $instructions,
        public string $input,
        public int $maxTokens = 1200,
    ) {}
}
