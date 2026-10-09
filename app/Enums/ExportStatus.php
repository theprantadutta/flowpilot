<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ExportStatus: string
{
    use HasOptions;

    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Waiting to start',
            self::Processing => 'Preparing',
            self::Completed => 'Ready',
            self::Failed => 'Failed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Queued => 'neutral',
            self::Processing => 'flow',
            self::Completed => 'success',
            self::Failed => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Queued => 'clock',
            self::Processing => 'loader',
            self::Completed => 'circle-check',
            self::Failed => 'circle-x',
        };
    }

    public function isFinished(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }
}
