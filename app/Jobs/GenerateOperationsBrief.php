<?php

namespace App\Jobs;

use App\Enums\AiBriefStatus;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Jobs\Concerns\RunsOnLongQueue;
use App\Models\AiBrief;
use App\Models\Organization;
use App\Support\Ai\AiOperationsService;
use App\Support\Billing\Entitlements;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Writes an operations brief in the background, as the member who asked.
 * One attempt only: AI calls cost money, and the service already falls back
 * to a plain brief when the provider fails.
 */
class GenerateOperationsBrief implements ShouldQueue
{
    use Queueable, RunsOnLongQueue;

    public int $tries = 1;

    /**
     * Longer than the provider's own timeout, so the HTTP call gives up first.
     */
    public int $timeout;

    public function __construct(
        public string $organizationId,
        public string $briefId,
    ) {
        $this->timeout = (int) config('ai.providers.freeway.timeout', 180) + 60;
        $this->onLongQueue();
    }

    public function handle(Tenancy $tenancy, AiOperationsService $ai): void
    {
        $organization = Organization::query()->find($this->organizationId);

        if ($organization === null || ! $organization->isActive()) {
            return;
        }

        $tenancy->run($organization, function () use ($tenancy, $ai, $organization): void {
            $brief = AiBrief::query()->with('user')->find($this->briefId);

            if ($brief === null || $brief->status !== AiBriefStatus::Pending) {
                return;
            }

            $membership = $tenancy->membershipFor($brief->user);

            if ($membership === null || ! $membership->isActive() || ! $membership->allows(Permission::AiUse)) {
                $brief->forceFill(['status' => AiBriefStatus::Failed, 'error' => 'The member can no longer use AI in this organization.', 'completed_at' => now()])->save();

                return;
            }

            if (! app(Entitlements::class)->allows(Feature::AiInsights, $organization)) {
                $brief->forceFill(['status' => AiBriefStatus::Failed, 'error' => 'The organization\'s plan no longer includes AI briefs.', 'completed_at' => now()])->save();

                return;
            }

            // Read as the member, so the brief only holds what they may see.
            $tenancy->run($organization, fn (): AiBrief => $ai->writeBrief($brief), $membership);
        });
    }

    public function failed(?Throwable $exception): void
    {
        $organization = Organization::query()->find($this->organizationId);

        if ($organization === null) {
            return;
        }

        app(Tenancy::class)->run($organization, function (): void {
            AiBrief::query()
                ->whereKey($this->briefId)
                ->where('status', AiBriefStatus::Pending->value)
                ->update(['status' => AiBriefStatus::Failed->value, 'error' => 'The brief could not be written.', 'completed_at' => now()]);
        });
    }
}
