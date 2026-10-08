<?php

use App\Support\Money;
use Tests\TestCase;

// Currency precision comes from configuration, so these need the application.
uses(TestCase::class);

test('amounts typed by people convert to minor units without floating point', function (string $input, string $currency, int $expected) {
    expect(Money::toMinorUnits($input, $currency))->toBe($expected);
})->with([
    'whole dollars' => ['8420', 'USD', 842000],
    'with separators and cents' => ['8,420.50', 'USD', 842050],
    'single decimal' => ['0.5', 'USD', 50],
    'value floats get wrong' => ['0.29', 'USD', 29],
    'trailing zeros beyond precision' => ['12.3400', 'USD', 1234],
    'zero-decimal currency' => ['1500', 'JPY', 1500],
]);

test('amounts with more precision than the currency allows are rejected', function () {
    Money::toMinorUnits('10.555', 'USD');
})->throws(InvalidArgumentException::class, 'USD amounts have at most 2 decimal places.');

test('malformed amounts are rejected', function (string $input) {
    Money::toMinorUnits($input, 'USD');
})->throws(InvalidArgumentException::class)->with(['-5', '1,00', '12k', '', '1.2.3']);

test('minor units format back to a plain decimal for editing', function () {
    expect(Money::toDecimalString(1250050, 'USD'))->toBe('12500.50')
        ->and(Money::toDecimalString(5, 'USD'))->toBe('0.05')
        ->and(Money::toDecimalString(1500, 'JPY'))->toBe('1500')
        ->and(Money::toDecimalString(null, 'USD'))->toBeNull();
});
