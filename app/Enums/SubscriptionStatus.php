<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum SubscriptionStatus: string
{
    use HasOptions;

    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::Trialing => 'Trial',
            self::Active => 'Active',
            self::PastDue => 'Payment overdue',
            self::Canceled => 'Canceled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Trialing => 'info',
            self::Active => 'success',
            self::PastDue => 'warning',
            self::Canceled => 'neutral',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Trialing => 'hourglass',
            self::Active => 'circle-check',
            self::PastDue => 'triangle-alert',
            self::Canceled => 'circle-slash',
        };
    }
}
