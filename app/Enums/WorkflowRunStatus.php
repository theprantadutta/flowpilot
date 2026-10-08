<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum WorkflowRunStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Running = 'running';
    case Waiting = 'waiting';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Queued',
            self::Running => 'Running',
            self::Waiting => 'Waiting',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'neutral',
            self::Running => 'flow',
            self::Waiting => 'warning',
            self::Completed => 'success',
            self::Failed => 'danger',
            self::Cancelled => 'neutral',
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
            self::Cancelled => 'circle-slash',
        };
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Cancelled], true);
    }

    /**
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Pending, self::Running, self::Waiting];
    }
}
