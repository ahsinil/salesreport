<?php

use App\Services\CurrencyFormatter;

test('amounts are formatted as indonesian rupiah', function (int|float|string $amount, string $expected) {
    expect(CurrencyFormatter::rupiah($amount))->toBe($expected);
})->with([
    'whole amount' => [123456, 'Rp. 123.456'],
    'fractional amount' => [125.5, 'Rp. 125,50'],
    'whole decimal string' => ['100.00', 'Rp. 100'],
    'zero amount' => [0, 'Rp. 0'],
]);
