<?php

declare(strict_types=1);

namespace App\Shared\Domain\Money\Exception;

use App\Shared\Domain\Money\Currency;
use DomainException;

final class CurrencyMismatch extends DomainException
{
    public static function between(Currency $expected, Currency $actual): self
    {
        return new self(sprintf('Currency mismatch: expected %s, got %s.', $expected->value, $actual->value));
    }
}
