<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Converts between what people type ("12,500.50") and what is stored
 * (1250050 minor units). Works on strings throughout; floats never touch money.
 */
class Money
{
    /**
     * Matches a non-negative amount with optional thousands separators and
     * up to four decimal places, e.g. "8420", "8,420.00", "0.5".
     */
    public const string PATTERN = '/^\d{1,3}(,?\d{3})*(\.\d{1,4})?$|^\d+(\.\d{1,4})?$/';

    public static function toMinorUnits(string $amount, string $currency): int
    {
        $amount = trim(str_replace([' ', "\u{00A0}"], '', $amount));

        if (! preg_match(self::PATTERN, $amount)) {
            throw new InvalidArgumentException("[{$amount}] is not a valid amount.");
        }

        $amount = str_replace(',', '', $amount);
        $decimals = Currencies::decimals($currency);

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        if (strlen($fraction) > $decimals && rtrim(substr($fraction, $decimals), '0') !== '') {
            throw new InvalidArgumentException("{$currency} amounts have at most {$decimals} decimal places.");
        }

        $fraction = str_pad(substr($fraction, 0, $decimals), $decimals, '0');

        return (int) ltrim($whole.$fraction, '0') ?: 0;
    }

    /**
     * Minor units back to a plain decimal string for form fields: 1250050 → "12500.50".
     */
    public static function toDecimalString(?int $minorUnits, string $currency): ?string
    {
        if ($minorUnits === null) {
            return null;
        }

        $decimals = Currencies::decimals($currency);

        if ($decimals === 0) {
            return (string) $minorUnits;
        }

        $digits = str_pad((string) abs($minorUnits), $decimals + 1, '0', STR_PAD_LEFT);

        return ($minorUnits < 0 ? '-' : '').substr($digits, 0, -$decimals).'.'.substr($digits, -$decimals);
    }
}
