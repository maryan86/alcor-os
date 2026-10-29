<?php

declare(strict_types=1);

namespace App\Shared\Domain\Money;

use InvalidArgumentException;

enum Currency: string
{
    case USD = 'USD';
    case EUR = 'EUR';

    public static function fromCode(string $code): self
    {
        $currency = self::tryFrom(strtoupper(trim($code)));

        if ($currency === null) {
            throw new InvalidArgumentException(sprintf('Unsupported currency "%s".', $code));
        }

        return $currency;
    }

    public function minorUnitScale(): int
    {
        return match ($this) {
            self::USD, self::EUR => 2,
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::USD => '$',
            self::EUR => '€',
        };
    }
}
