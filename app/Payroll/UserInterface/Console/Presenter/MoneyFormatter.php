<?php

declare(strict_types=1);

namespace App\Payroll\UserInterface\Console\Presenter;

use App\Shared\Domain\Money\Money;

final class MoneyFormatter
{
    public static function format(Money $money, bool $signed = false): string
    {
        $parts = explode('.', ltrim($money->toDecimalString(), '-'));
        $units = strrev(implode(',', str_split(strrev($parts[0]), 3)));
        $fraction = isset($parts[1]) ? '.' . $parts[1] : '';
        $sign = match (true) {
            $money->isNegative() => '-',
            $signed && !$money->isZero() => '+',
            default => '',
        };

        return $sign . $money->currency->symbol() . $units . $fraction;
    }
}
