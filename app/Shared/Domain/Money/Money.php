<?php

declare(strict_types=1);

namespace App\Shared\Domain\Money;

use App\Shared\Domain\Money\Exception\CurrencyMismatch;
use App\Shared\Domain\Money\Exception\InvalidMoneyAmount;

final readonly class Money
{
    private function __construct(
        public int $minorAmount,
        public Currency $currency,
    ) {}

    public static function ofMinor(int $minorAmount, Currency $currency): self
    {
        return new self($minorAmount, $currency);
    }

    public static function zero(Currency $currency): self
    {
        return new self(0, $currency);
    }

    public static function of(string $amount, Currency $currency): self
    {
        $scale = $currency->minorUnitScale();
        $pattern = sprintf('/^(?<sign>[+-])?(?<units>\d+)(?:\.(?<fraction>\d{1,%d}))?$/', max(1, $scale));

        if (preg_match($pattern, trim($amount), $matches) !== 1) {
            throw InvalidMoneyAmount::cannotParse($amount, $currency);
        }

        // The pattern allows at least one decimal; a currency without minor units allows none.
        if ($scale === 0 && ($matches['fraction'] ?? '') !== '') {
            throw InvalidMoneyAmount::cannotParse($amount, $currency);
        }

        $digits = ltrim($matches['units'] . str_pad($matches['fraction'] ?? '', $scale, '0'), '0');
        $minorAmount = filter_var($digits === '' ? '0' : $digits, FILTER_VALIDATE_INT);

        if ($minorAmount === false) {
            throw InvalidMoneyAmount::outOfRange($amount);
        }

        return new self($matches['sign'] === '-' ? -$minorAmount : $minorAmount, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        // PHP silently promotes an overflowing int to float; money must never lose precision.
        if (
            ($other->minorAmount > 0 && $this->minorAmount > PHP_INT_MAX - $other->minorAmount)
            || ($other->minorAmount < 0 && $this->minorAmount < PHP_INT_MIN - $other->minorAmount)
        ) {
            throw InvalidMoneyAmount::overflow();
        }

        return new self($this->minorAmount + $other->minorAmount, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minorAmount === 0;
    }

    public function isNegative(): bool
    {
        return $this->minorAmount < 0;
    }

    public function isSameCurrencyAs(self $other): bool
    {
        return $this->currency === $other->currency;
    }

    public function equals(self $other): bool
    {
        return $this->isSameCurrencyAs($other) && $this->minorAmount === $other->minorAmount;
    }

    public function toDecimalString(): string
    {
        $scale = $this->currency->minorUnitScale();
        $absolute = str_pad((string) abs($this->minorAmount), $scale + 1, '0', STR_PAD_LEFT);
        $units = $scale === 0 ? $absolute : substr($absolute, 0, -$scale) . '.' . substr($absolute, -$scale);

        return ($this->isNegative() ? '-' : '') . $units;
    }

    private function assertSameCurrency(self $other): void
    {
        if (!$this->isSameCurrencyAs($other)) {
            throw CurrencyMismatch::between($this->currency, $other->currency);
        }
    }
}
