<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\Money;

use App\Shared\Domain\Money\Currency;
use App\Shared\Domain\Money\Exception\CurrencyMismatch;
use App\Shared\Domain\Money\Exception\InvalidMoneyAmount;
use App\Shared\Domain\Money\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    /** @return iterable<string, array{string, int}> */
    public static function validAmounts(): iterable
    {
        yield 'integer' => ['1050', 105000];
        yield 'two decimals' => ['1050.00', 105000];
        yield 'one decimal' => ['0.5', 50];
        yield 'negative' => ['-45.55', -4555];
        yield 'explicit plus' => ['+100.10', 10010];
        yield 'smallest unit' => ['-0.01', -1];
        yield 'surrounding whitespace' => [' 12.30 ', 1230];
        yield 'negative zero' => ['-0.00', 0];
    }

    #[Test]
    #[DataProvider('validAmounts')]
    public function it_parses_decimal_strings_into_exact_minor_units(string $amount, int $expectedMinor): void
    {
        self::assertSame($expectedMinor, Money::of($amount, Currency::USD)->minorAmount);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidAmounts(): iterable
    {
        yield 'empty' => [''];
        yield 'more decimals than the currency has' => ['1.005'];
        yield 'comma separator' => ['1,05'];
        yield 'thousands separator' => ['1,000.00'];
        yield 'scientific notation' => ['1e3'];
        yield 'text' => ['ten dollars'];
        yield 'double sign' => ['--1'];
        yield 'beyond integer range' => ['99999999999999999999'];
    }

    #[Test]
    #[DataProvider('invalidAmounts')]
    public function it_rejects_anything_that_is_not_an_exact_amount(string $amount): void
    {
        $this->expectException(InvalidMoneyAmount::class);

        Money::of($amount, Currency::USD);
    }

    #[Test]
    public function it_adds_without_floating_point_errors(): void
    {
        // 0.1 + 0.2 !== 0.3 with floats; with minor units it is exact.
        $sum = Money::of('0.10', Currency::USD)->add(Money::of('0.20', Currency::USD));

        self::assertTrue($sum->equals(Money::of('0.30', Currency::USD)));
    }

    #[Test]
    public function it_refuses_to_mix_currencies(): void
    {
        $this->expectException(CurrencyMismatch::class);

        Money::of('1', Currency::USD)->add(Money::of('1', Currency::EUR));
    }

    #[Test]
    public function it_refuses_to_overflow(): void
    {
        $this->expectException(InvalidMoneyAmount::class);

        Money::ofMinor(PHP_INT_MAX, Currency::USD)->add(Money::ofMinor(1, Currency::USD));
    }

    #[Test]
    public function it_refuses_to_underflow(): void
    {
        $this->expectException(InvalidMoneyAmount::class);

        Money::ofMinor(PHP_INT_MIN, Currency::USD)->add(Money::ofMinor(-1, Currency::USD));
    }

    #[Test]
    public function it_compares_by_value_and_currency(): void
    {
        self::assertTrue(Money::of('1.00', Currency::USD)->equals(Money::ofMinor(100, Currency::USD)));
        self::assertFalse(Money::of('1.00', Currency::USD)->equals(Money::of('1.00', Currency::EUR)));
    }

    /** @return iterable<string, array{int, string}> */
    public static function decimalRepresentations(): iterable
    {
        yield 'positive' => [110445, '1104.45'];
        yield 'negative' => [-4555, '-45.55'];
        yield 'below one unit' => [-5, '-0.05'];
        yield 'zero' => [0, '0.00'];
    }

    #[Test]
    #[DataProvider('decimalRepresentations')]
    public function it_renders_a_canonical_decimal_string(int $minor, string $expected): void
    {
        self::assertSame($expected, Money::ofMinor($minor, Currency::USD)->toDecimalString());
    }

    #[Test]
    public function it_resolves_currency_codes_case_insensitively(): void
    {
        self::assertSame(Currency::USD, Currency::fromCode(' usd '));

        $this->expectException(InvalidArgumentException::class);
        Currency::fromCode('XYZ');
    }
}
