<?php

namespace App\Enums;

/**
 * How an item is counted. Stock is kept in whole units of these.
 */
enum InventoryUnit: string
{
    case Each = 'each';
    case Pair = 'pair';
    case Box = 'box';
    case Pack = 'pack';
    case Set = 'set';
    case Roll = 'roll';
    case Kilogram = 'kg';
    case Litre = 'litre';
    case Metre = 'metre';

    public function label(): string
    {
        return match ($this) {
            self::Each => 'Each',
            self::Pair => 'Pair',
            self::Box => 'Box',
            self::Pack => 'Pack',
            self::Set => 'Set',
            self::Roll => 'Roll',
            self::Kilogram => 'Kilogram',
            self::Litre => 'Litre',
            self::Metre => 'Metre',
        };
    }

    /**
     * "12 boxes", "1 kg".
     */
    public function quantity(int $quantity): string
    {
        $plural = $quantity === 1 ? '' : 's';

        return match ($this) {
            self::Each => (string) $quantity,
            self::Kilogram => "{$quantity} kg",
            self::Litre => "{$quantity} l",
            self::Metre => "{$quantity} m",
            self::Box => $quantity === 1 ? '1 box' : "{$quantity} boxes",
            default => "{$quantity} ".strtolower($this->label()).$plural,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $unit): array => ['value' => $unit->value, 'label' => $unit->label()], self::cases());
    }
}
