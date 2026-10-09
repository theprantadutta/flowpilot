<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Every change to stock is one of these, recorded in the movement ledger.
 */
enum MovementType: string
{
    use HasOptions;

    case Receipt = 'receipt';
    case Issue = 'issue';
    case Adjustment = 'adjustment';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'Received',
            self::Issue => 'Issued',
            self::Adjustment => 'Stock count',
            self::Transfer => 'Moved',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Receipt => 'success',
            self::Issue => 'flow',
            self::Adjustment => 'warning',
            self::Transfer => 'info',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Receipt => 'package-plus',
            self::Issue => 'package-minus',
            self::Adjustment => 'clipboard-check',
            self::Transfer => 'arrow-left-right',
        };
    }

    /**
     * What someone does to record this kind of movement.
     */
    public function action(): string
    {
        return match ($this) {
            self::Receipt => 'Receive stock',
            self::Issue => 'Issue stock',
            self::Adjustment => 'Record a count',
            self::Transfer => 'Move stock',
        };
    }
}
