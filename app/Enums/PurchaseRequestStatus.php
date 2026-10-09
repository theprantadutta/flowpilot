<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PurchaseRequestStatus: string
{
    use HasOptions;

    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Ordered = 'ordered';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Waiting for approval',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Ordered => 'Ordered',
            self::Received => 'Received',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Submitted => 'warning',
            self::Approved => 'info',
            self::Ordered => 'flow',
            self::Received => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'neutral',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Submitted => 'hourglass',
            self::Approved => 'circle-check',
            self::Ordered => 'truck',
            self::Received => 'package-check',
            self::Rejected => 'circle-x',
            self::Cancelled => 'circle-slash',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Submitted, self::Approved, self::Ordered], true);
    }
}
