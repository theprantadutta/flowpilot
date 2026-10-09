<?php

namespace App\Enums;

/**
 * Countable things a plan caps.
 */
enum Limit: string
{
    case Members = 'members';
    case Workflows = 'workflows';
    case WorkflowRunsPerMonth = 'workflow_runs_per_month';
    case StorageMb = 'storage_mb';
    case AiBriefsPerDay = 'ai_briefs_per_day';

    public function label(): string
    {
        return match ($this) {
            self::Members => 'Members',
            self::Workflows => 'Workflows',
            self::WorkflowRunsPerMonth => 'Workflow runs this month',
            self::StorageMb => 'File storage',
            self::AiBriefsPerDay => 'AI briefs today',
        };
    }

    /**
     * How the limit is counted, for the pricing table: "3 members".
     */
    public function describe(?int $value): string
    {
        if ($value === null) {
            return match ($this) {
                self::Members => 'Unlimited members',
                self::Workflows => 'Unlimited workflows',
                self::WorkflowRunsPerMonth => 'Unlimited workflow runs',
                self::StorageMb => 'Unlimited storage',
                self::AiBriefsPerDay => 'Unlimited AI briefs',
            };
        }

        return match ($this) {
            self::Members => number_format($value).' '.($value === 1 ? 'member' : 'members'),
            self::Workflows => number_format($value).' '.($value === 1 ? 'workflow' : 'workflows'),
            self::WorkflowRunsPerMonth => number_format($value).' workflow runs a month',
            self::StorageMb => ($value >= 1024 ? number_format($value / 1024).' GB' : number_format($value).' MB').' of file storage',
            self::AiBriefsPerDay => $value === 0 ? 'No AI briefs' : number_format($value).' AI briefs a day',
        };
    }
}
