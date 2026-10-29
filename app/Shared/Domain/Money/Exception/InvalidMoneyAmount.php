<?php

declare(strict_types=1);

namespace App\Shared\Domain\Money\Exception;

use App\Shared\Domain\Money\Currency;
use DomainException;

final class InvalidMoneyAmount extends DomainException
{
    public static function cannotParse(string $amount, Currency $currency): self
    {
        return new self(sprintf(
            'Cannot parse "%s" as a %s amount with at most %d decimal place(s).',
            $amount,
            $currency->value,
            $currency->minorUnitScale(),
        ));
    }

    public static function outOfRange(string $amount): self
    {
        return new self(sprintf('Amount "%s" is out of the supported range.', $amount));
    }

    public static function overflow(): self
    {
        return new self('Monetary arithmetic overflowed the supported range.');
    }
}
