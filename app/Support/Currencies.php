<?php

namespace App\Support;

/**
 * The currencies an organization can work in, read from config/flowpilot.php.
 */
class Currencies
{
    /**
     * @return array<string, array{name: string, decimals: int}>
     */
    public static function all(): array
    {
        $currencies = [];

        foreach (config()->array('flowpilot.currencies') as $code => $currency) {
            if (! is_string($code) || ! is_array($currency)) {
                continue;
            }

            $currencies[$code] = [
                'name' => is_string($currency['name'] ?? null) ? $currency['name'] : $code,
                'decimals' => is_int($currency['decimals'] ?? null) ? $currency['decimals'] : 2,
            ];
        }

        return $currencies;
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::all());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::all() as $code => $currency) {
            $options[] = ['value' => $code, 'label' => "{$code} · {$currency['name']}"];
        }

        return $options;
    }

    /**
     * Digits after the decimal point in the currency's minor unit (2 for USD, 0 for JPY).
     */
    public static function decimals(string $code): int
    {
        return self::all()[strtoupper($code)]['decimals'] ?? 2;
    }
}
