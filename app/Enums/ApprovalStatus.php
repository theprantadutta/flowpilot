<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ApprovalStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for decision',
            self::ChangesRequested => 'Changes requested',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Expired => 'Expired',
            self::Cancelled => 'Withdrawn',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending, self::ChangesRequested => 'warning',
            self::Approved => 'success',
            self::Rejected, self::Expired => 'danger',
            self::Cancelled => 'neutral',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'hourglass',
            self::ChangesRequested => 'file-pen',
            self::Approved => 'circle-check',
            self::Rejected => 'circle-x',
            self::Expired => 'clock',
            self::Cancelled => 'circle-slash',
        };
    }

    /**
     * Still open: someone has to act.
     */
    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::ChangesRequested;
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [self::Pending->value, self::ChangesRequested->value];
    }
}
