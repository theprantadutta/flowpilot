<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * How an item's stock compares with its reorder point.
 */
enum StockStatus: string
{
    use HasOptions;

    case InStock = 'in_stock';
    case Low = 'low';
    case Out = 'out';

    public static function for(int $stock, int $reorderPoint): self
    {
        return match (true) {
            $stock <= 0 => self::Out,
            $stock <= $reorderPoint => self::Low,
            default => self::InStock,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'In stock',
            self::Low => 'Low stock',
            self::Out => 'Out of stock',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::InStock => 'success',
            self::Low => 'warning',
            self::Out => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::InStock => 'circle-check',
            self::Low => 'triangle-alert',
            self::Out => 'circle-x',
        };
    }
}
