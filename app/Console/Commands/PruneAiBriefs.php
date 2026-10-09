<?php

namespace App\Console\Commands;

use App\Enums\AiBriefStatus;
use App\Models\AiBrief;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Gives up on briefs a stopped worker left half written, and deletes old
 * briefs. Crosses organizations on purpose: it only touches stale records.
 */
#[Signature('ai:prune-briefs')]
#[Description('Fail stuck AI briefs and delete old ones')]
class PruneAiBriefs extends Command
{
    public function handle(): int
    {
        $stuck = AiBrief::withoutOrganizationScope()
            ->where('status', AiBriefStatus::Pending->value)
            ->where('created_at', '<=', now()->subMinutes(30))
            ->update(['status' => AiBriefStatus::Failed->value, 'error' => 'The brief took too long to write.', 'completed_at' => now()]);

        $deleted = AiBrief::withoutOrganizationScope()
            ->where('created_at', '<=', now()->subDays(max(1, (int) config('ai.brief.keep_days', 30))))
            ->delete();

        $this->components->info("Marked {$stuck} stuck and deleted {$deleted} old AI briefs.");

        return self::SUCCESS;
    }
}
