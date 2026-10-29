<?php

declare(strict_types=1);

namespace App\Shared\Domain\EventSourcing;

use App\Shared\Domain\Money\Currency;
use App\Shared\Domain\Money\Money;
use DateTimeImmutable;
use UnexpectedValueException;

final class Payload
{
    public const string DATE_FORMAT = 'Y-m-d\TH:i:s.uP';

    /** @param array<string, mixed> $payload */
    public static function string(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        if (!is_string($value)) {
            throw self::invalid($key, 'string');
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    public static function int(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;

        if (!is_int($value)) {
            throw self::invalid($key, 'int');
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    public static function nullableInt(array $payload, string $key): ?int
    {
        if (!isset($payload[$key])) {
            return null;
        }

        return self::int($payload, $key);
    }

    /** @param array<string, mixed> $payload */
    public static function dateTime(array $payload, string $key): DateTimeImmutable
    {
        $value = DateTimeImmutable::createFromFormat(self::DATE_FORMAT, self::string($payload, $key));

        if (!$value instanceof DateTimeImmutable) {
            throw self::invalid($key, 'date-time');
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    public static function currency(array $payload, string $key): Currency
    {
        $currency = Currency::tryFrom(self::string($payload, $key));

        if ($currency === null) {
            throw self::invalid($key, 'ISO currency code');
        }

        return $currency;
    }

    /** @param array<string, mixed> $payload */
    public static function money(array $payload, string $amountKey, string $currencyKey = 'currency'): Money
    {
        return Money::ofMinor(self::int($payload, $amountKey), self::currency($payload, $currencyKey));
    }

    public static function formatDateTime(DateTimeImmutable $dateTime): string
    {
        return $dateTime->format(self::DATE_FORMAT);
    }

    private static function invalid(string $key, string $type): UnexpectedValueException
    {
        return new UnexpectedValueException(sprintf('Event payload key "%s" must be a valid %s.', $key, $type));
    }
}
