<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum IssueSeverity: string
{
    use HasOptions;

    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Critical',
            self::High => 'High',
            self::Medium => 'Medium',
            self::Low => 'Low',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Critical => 'danger',
            self::High => 'warning',
            self::Medium => 'info',
            self::Low => 'neutral',
        };
    }

    /**
     * Each severity has its own shape, so it reads without colour.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Critical => 'octagon-alert',
            self::High => 'triangle-alert',
            self::Medium => 'circle-alert',
            self::Low => 'info',
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::Critical => 4,
            self::High => 3,
            self::Medium => 2,
            self::Low => 1,
        };
    }
}
