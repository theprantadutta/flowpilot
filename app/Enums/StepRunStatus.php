<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum StepRunStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Running = 'running';
    case Waiting = 'waiting';
    case Completed = 'completed';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Running => 'Running',
            self::Waiting => 'Waiting',
            self::Completed => 'Done',
            self::Failed => 'Failed',
            self::Skipped => 'Skipped',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending, self::Skipped, self::Cancelled => 'neutral',
            self::Running => 'flow',
            self::Waiting => 'warning',
            self::Completed => 'success',
            self::Failed => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'circle-dashed',
            self::Running => 'circle-dot',
            self::Waiting => 'pause',
            self::Completed => 'circle-check',
            self::Failed => 'circle-x',
            self::Skipped, self::Cancelled => 'circle-slash',
        };
    }
}
