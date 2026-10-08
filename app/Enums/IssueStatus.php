<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum IssueStatus: string
{
    use HasOptions;

    case Open = 'open';
    case Investigating = 'investigating';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Investigating => 'Investigating',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::Investigating => 'flow',
            self::Resolved => 'success',
            self::Closed => 'neutral',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Open => 'circle',
            self::Investigating => 'search',
            self::Resolved => 'circle-check',
            self::Closed => 'archive',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Open || $this === self::Investigating;
    }

    /**
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Open, self::Investigating];
    }
}
