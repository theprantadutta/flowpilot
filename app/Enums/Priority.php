<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Priority: string
{
    use HasOptions;

    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::Urgent => 'Urgent',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Low => 'neutral',
            self::Medium => 'info',
            self::High => 'warning',
            self::Urgent => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Low => 'signal-low',
            self::Medium => 'signal-medium',
            self::High => 'signal-high',
            self::Urgent => 'siren',
        };
    }

    /**
     * Higher sorts first.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
            self::Urgent => 4,
        };
    }
}
