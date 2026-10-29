<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Payroll\Domain\EarningLine\Event\ManualAdjustmentAdded;
use App\Payroll\Domain\EarningLine\Event\SystemRecalculationIgnored;
use App\Payroll\Domain\EarningLine\Event\SystemValueCalculated;
use App\Payroll\Domain\EarningLine\Event\SystemValueRecalculated;
use App\Payroll\Domain\EarningLine\ValueObject\AdjustmentComment;
use App\Payroll\Domain\EarningLine\ValueObject\EarningLineId;
use App\Payroll\Domain\EarningLine\ValueObject\SpecialistId;
use App\Shared\Domain\EventSourcing\DomainEvent;
use App\Shared\Domain\Money\Currency;
use App\Shared\Domain\Money\Money;
use DateTimeImmutable;
use DateTimeZone;

final class EarningLineEventSamples
{
    public const string LINE_ID = '0b9a5c34-2d0e-4b8f-9a51-6f1d2c3e4a5b';

    public static function streamStart(): SystemValueCalculated
    {
        return new SystemValueCalculated(self::lineId(), Money::of('1000.00', Currency::USD), self::at());
    }

    /** @return array<class-string<DomainEvent>, DomainEvent> */
    public static function all(): array
    {
        $samples = [
            self::streamStart(),
            new SystemValueRecalculated(self::lineId(), Money::of('1000.00', Currency::USD), Money::of('1050.00', Currency::USD), self::at()),
            new SystemRecalculationIgnored(self::lineId(), Money::of('1100.00', Currency::USD), self::at()),
            new ManualAdjustmentAdded(
                self::lineId(),
                2,
                Money::of('+45.55', Currency::USD),
                AdjustmentComment::fromString('Виправлення помилки в коригуванні #1'),
                SpecialistId::fromString('specialist-1'),
                self::at(),
                1,
            ),
        ];

        return array_combine(array_map(static fn(DomainEvent $event): string => $event::class, $samples), $samples);
    }

    private static function lineId(): EarningLineId
    {
        return EarningLineId::fromString(self::LINE_ID);
    }

    private static function at(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-01-15 09:00:00.123456', new DateTimeZone('UTC'));
    }
}
