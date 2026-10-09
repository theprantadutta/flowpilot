<?php

namespace App\Actions\Ai;

use App\Enums\AiBriefStatus;
use App\Enums\Feature;
use App\Enums\Limit;
use App\Jobs\GenerateOperationsBrief;
use App\Models\AiBrief;
use App\Models\User;
use App\Support\Ai\AiOperationsService;
use App\Support\Billing\Entitlements;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class RequestOperationsBrief
{
    public function __construct(
        private readonly AiOperationsService $ai,
        private readonly Tenancy $tenancy,
        private readonly Entitlements $entitlements,
    ) {}

    /**
     * Queue a new brief for the member, within the hourly and daily limits.
     * Asking again while one is being written returns that one.
     *
     * @throws ValidationException
     */
    public function handle(User $user): AiBrief
    {
        $organization = $this->tenancy->currentOrFail();

        $this->entitlements->ensure(Feature::AiInsights, 'brief');

        if (! $this->ai->isEnabled()) {
            throw ValidationException::withMessages(['brief' => 'AI is not set up for this organization.']);
        }

        $pending = AiBrief::query()
            ->where('user_id', $user->id)
            ->where('status', AiBriefStatus::Pending->value)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->latest()
            ->first();

        if ($pending !== null) {
            return $pending;
        }

        $memberKey = "ai-brief:member:{$organization->id}:{$user->id}";

        if (RateLimiter::tooManyAttempts($memberKey, (int) config('ai.brief.per_member_per_hour', 6))) {
            throw ValidationException::withMessages(['brief' => 'You have asked for several briefs this hour. Try again in '.$this->minutes(RateLimiter::availableIn($memberKey)).'.']);
        }

        // The plan sets how many briefs the whole organization gets each day.
        $this->entitlements->ensureRoom(Limit::AiBriefsPerDay, 1, 'brief');

        RateLimiter::hit($memberKey, 3600);

        $brief = AiBrief::query()->create(['user_id' => $user->id, 'status' => AiBriefStatus::Pending]);

        GenerateOperationsBrief::dispatch($organization->id, $brief->id)->afterCommit();

        return $brief;
    }

    private function minutes(int $seconds): string
    {
        $minutes = max(1, (int) ceil($seconds / 60));

        return $minutes >= 120 ? intdiv($minutes, 60).' hours' : $minutes.' '.($minutes === 1 ? 'minute' : 'minutes');
    }
}
