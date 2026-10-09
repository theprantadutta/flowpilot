<?php

namespace App\Support\Ai;

/**
 * A language model behind an API. Features never call a provider directly;
 * they go through AiOperationsService, so the provider can be swapped.
 */
interface AiProvider
{
    /**
     * Short name stored with every AI call, e.g. "freeway".
     */
    public function name(): string;

    /**
     * Whether the provider has what it needs (URL, key) to be called.
     */
    public function isConfigured(): bool;

    /**
     * @throws AiUnavailable when the provider cannot be reached or refuses the request.
     */
    public function complete(AiPrompt $prompt): AiCompletion;
}
