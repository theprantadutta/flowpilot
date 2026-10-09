<?php

namespace App\Support\Ai;

use App\Enums\AiBriefStatus;
use App\Models\AiBrief;
use App\Support\Ai\Brief\BriefFacts;
use App\Support\Ai\Brief\BriefFallback;
use App\Support\Ai\Brief\BriefParser;
use App\Support\Ai\Brief\BriefPrompt;
use App\Support\Ai\Brief\InvalidBrief;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\Log;

/**
 * The one door to AI in FlowPilot. Features ask it for results; it decides
 * what data the model may see (only what the member may see), calls the
 * provider, checks the answer and records what each call cost. A failing
 * provider never breaks a feature: the brief falls back to plain text.
 */
class AiOperationsService
{
    public function __construct(
        private readonly AiProvider $provider,
        private readonly BriefFacts $facts,
        private readonly BriefPrompt $prompts,
        private readonly BriefParser $parser,
        private readonly BriefFallback $fallback,
        private readonly Tenancy $tenancy,
    ) {}

    public function isEnabled(): bool
    {
        return $this->provider->isConfigured();
    }

    /**
     * Write a pending brief for the member it belongs to. Runs inside that
     * member's organization, with their membership, so every fact is one
     * they may open.
     */
    public function writeBrief(AiBrief $brief): AiBrief
    {
        $user = $brief->user;
        $organization = $this->tenancy->currentOrFail();
        $membership = $this->tenancy->membershipFor($user);
        $facts = $this->facts->for($user);

        $snapshot = [];

        foreach ($facts as $fact) {
            $snapshot[$fact['id']] = ['label' => $fact['label'], 'url' => $fact['url'], 'area' => $fact['area']];
        }

        if ($facts === []) {
            // Nothing to say, so nothing to pay for.
            $brief->forceFill([
                'status' => AiBriefStatus::Completed,
                'content' => BriefFallback::calm(),
                'facts' => [],
                'completed_at' => now(),
            ])->save();

            return $brief;
        }

        $completion = null;

        try {
            $completion = $this->provider->complete($this->prompts->for($user, $membership?->role->label() ?? 'Member', $organization, $facts));
            $content = $this->parser->parse($completion->content, $facts);
            $fallbackReason = null;
        } catch (AiUnavailable|InvalidBrief $exception) {
            $content = $this->fallback->from($facts);
            $fallbackReason = $exception->getMessage();
        }

        $brief->forceFill([
            'status' => AiBriefStatus::Completed,
            'content' => $content,
            'facts' => $snapshot,
            'used_fallback' => $fallbackReason !== null,
            'provider' => $this->provider->name(),
            'model' => $completion?->model,
            'prompt_tokens' => $completion?->promptTokens,
            'completion_tokens' => $completion?->completionTokens,
            'duration_ms' => $completion?->durationMs,
            'error' => $fallbackReason !== null ? mb_substr($fallbackReason, 0, 250) : null,
            'completed_at' => now(),
        ])->save();

        // Usage only: never the prompt, the answer or the key.
        Log::info('AI operations brief written', [
            'organization' => $organization->id,
            'brief' => $brief->id,
            'provider' => $this->provider->name(),
            'model' => $completion?->model,
            'facts' => count($facts),
            'prompt_tokens' => $completion?->promptTokens,
            'completion_tokens' => $completion?->completionTokens,
            'duration_ms' => $completion?->durationMs,
            'fallback' => $fallbackReason,
        ]);

        return $brief;
    }
}
