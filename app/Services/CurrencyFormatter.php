<?php

namespace App\Services;

class CurrencyFormatter
{
    public static function rupiah(int|float|string|null $amount): string
    {
        $normalizedAmount = round((float) ($amount ?? 0), 2);
        $hasFraction = abs($normalizedAmount - round($normalizedAmount)) > 0.00001;

        return 'Rp. '.number_format($normalizedAmount, $hasFraction ? 2 : 0, ',', '.');
    }
}
