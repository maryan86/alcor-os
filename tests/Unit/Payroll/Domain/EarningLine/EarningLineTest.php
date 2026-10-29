<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll\Domain\EarningLine;

use App\Payroll\Domain\EarningLine\EarningLine;
use App\Payroll\Domain\EarningLine\Event\ManualAdjustmentAdded;
use App\Payroll\Domain\EarningLine\Event\SystemRecalculationIgnored;
use App\Payroll\Domain\EarningLine\Event\SystemValueCalculated;
use App\Payroll\Domain\EarningLine\Event\SystemValueRecalculated;
use App\Payroll\Domain\EarningLine\Exception\UnknownAdjustment;
use App\Payroll\Domain\EarningLine\Exception\ZeroAdjustmentNotAllowed;
use App\Payroll\Domain\EarningLine\ValueObject\AdjustmentComment;
use App\Payroll\Domain\EarningLine\ValueObject\EarningLineId;
use App\Payroll\Domain\EarningLine\ValueObject\SpecialistId;
use App\Shared\Domain\EventSourcing\DomainEvent;
use App\Shared\Domain\Money\Currency;
use App\Shared\Domain\Money\Exception\CurrencyMismatch;
use App\Shared\Domain\Money\Exception\InvalidMoneyAmount;
use App\Shared\Domain\Money\Money;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EarningLineTest extends TestCase
{
    private const string LINE_ID = '0b9a5c34-2d0e-4b8f-9a51-6f1d2c3e4a5b';

    private EarningLineId $id;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->id = EarningLineId::fromString(self::LINE_ID);
        $this->at = new DateTimeImmutable('2026-01-15 09:00:00');
    }

    #[Test]
    public function system_calculation_starts_the_line(): void
    {
        $line = EarningLine::calculate($this->id, self::usd('1000.00'), $this->at);

        self::assertEquals([new SystemValueCalculated($this->id, self::usd('1000.00'), $this->at)], $line->releaseEvents());
        self::assertTrue($line->currentValue()->equals(self::usd('1000.00')));
        self::assertFalse($line->isManuallyAdjusted());
    }

    #[Test]
    public function recalculation_replaces_the_value_while_the_line_is_not_adjusted(): void
    {
        $line = $this->given(new SystemValueCalculated($this->id, self::usd('1000.00'), $this->at));

        $line->recalculate(self::usd('1050.00'), $this->at);

        self::assertEquals(
            [new SystemValueRecalculated($this->id, self::usd('1000.00'), self::usd('1050.00'), $this->at)],
            $line->releaseEvents(),
        );
        self::assertTrue($line->currentValue()->equals(self::usd('1050.00')));
    }

    #[Test]
    public function recalculation_to_the_same_value_records_nothing(): void
    {
        $line = $this->given(new SystemValueCalculated($this->id, self::usd('1000.00'), $this->at));

        $line->recalculate(self::usd('1000.00'), $this->at);

        self::assertSame([], $line->releaseEvents());
    }

    #[Test]
    public function manual_adjustment_changes_the_current_value_but_keeps_the_system_value(): void
    {
        $line = $this->given(new SystemValueCalculated($this->id, self::usd('1050.00'), $this->at));

        $line->addManualAdjustment(self::usd('-45.55'), self::comment('Declined dental benefit'), self::specialist(), $this->at);

        self::assertEquals(
            [new ManualAdjustmentAdded($this->id, 1, self::usd('-45.55'), self::comment('Declined dental benefit'), self::specialist(), $this->at)],
            $line->releaseEvents(),
        );
        self::assertTrue($line->systemValue()->equals(self::usd('1050.00')));
        self::assertTrue($line->currentValue()->equals(self::usd('1004.45')));
        self::assertTrue($line->isManuallyAdjusted());
    }

    #[Test]
    public function recalculation_of_a_manually_adjusted_line_is_ignored_but_recorded(): void
    {
        $line = $this->given(
            new SystemValueCalculated($this->id, self::usd('1050.00'), $this->at),
            new ManualAdjustmentAdded($this->id, 1, self::usd('-45.55'), self::comment('Declined dental benefit'), self::specialist(), $this->at),
        );

        $line->recalculate(self::usd('1100.00'), $this->at);

        self::assertEquals([new SystemRecalculationIgnored($this->id, self::usd('1100.00'), $this->at)], $line->releaseEvents());
        self::assertTrue($line->systemValue()->equals(self::usd('1050.00')), 'system value is frozen');
        self::assertTrue($line->currentValue()->equals(self::usd('1004.45')));
    }

    #[Test]
    public function the_freeze_is_permanent_even_if_adjustments_net_to_zero(): void
    {
        $line = $this->given(
            new SystemValueCalculated($this->id, self::usd('1000.00'), $this->at),
            new ManualAdjustmentAdded($this->id, 1, self::usd('-10.00'), self::comment('Mistake'), self::specialist(), $this->at),
            new ManualAdjustmentAdded($this->id, 2, self::usd('10.00'), self::comment('Revert mistake'), self::specialist(), $this->at),
        );

        $line->recalculate(self::usd('2000.00'), $this->at);

        self::assertInstanceOf(SystemRecalculationIgnored::class, $line->releaseEvents()[0]);
        self::assertTrue($line->currentValue()->equals(self::usd('1000.00')));
    }

    #[Test]
    public function adjustments_are_numbered_sequentially_and_kept_in_order(): void
    {
        $line = $this->given(new SystemValueCalculated($this->id, self::usd('1000.00'), $this->at));

        $line->addManualAdjustment(self::usd('-0.20'), self::comment('Rounding'), self::specialist(), $this->at);
        $line->addManualAdjustment(self::usd('+0.20'), self::comment('Correcting mistake in adjustment #1'), self::specialist(), $this->at);

        self::assertSame([1, 2], array_map(static fn($adjustment): int => $adjustment->number, $line->adjustments()));
        self::assertTrue($line->currentValue()->equals(self::usd('1000.00')));
    }

    #[Test]
    public function compensating_adjustment_records_which_adjustment_it_corrects(): void
    {
        $line = $this->given(
            new SystemValueCalculated($this->id, self::usd('1000.00'), $this->at),
            new ManualAdjustmentAdded($this->id, 1, self::usd('-0.20'), self::comment('Rounding'), self::specialist(), $this->at),
        );

        $line->addManualAdjustment(self::usd('+0.20'), self::comment('Correcting mistake in adjustment #1'), self::specialist(), $this->at, corrects: 1);

        self::assertEquals(
            [new ManualAdjustmentAdded($this->id, 2, self::usd('+0.20'), self::comment('Correcting mistake in adjustment #1'), self::specialist(), $this->at, 1)],
            $line->releaseEvents(),
        );
        self::assertSame(1, $line->adjustments()[1]->corrects);
    }

    /** @return iterable<string, array{int}> */
    public static function unknownAdjustments(): iterable
    {
        yield 'not added yet' => [2];
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    #[Test]
    #[DataProvider('unknownAdjustments')]
    public function correcting_an_unknown_adjustment_is_rejected(int $corrects): void
    {
        $line = $this->given(
            new SystemValueCalculated($this->id, self::usd('1000.00'), $this->at),
            new ManualAdjustmentAdded($this->id, 1, self::usd('-0.20'), self::comment('Rounding'), self::specialist(), $this->at),
        );

        $this->expectException(UnknownAdjustment::class);

        $line->addManualAdjustment(self::usd('+0.20'), self::comment('Fix'), self::specialist(), $this->at, corrects: $corrects);
    }

    #[Test]
    public function zero_adjustment_is_rejected(): void
    {
        $line = $this->given(new SystemValueCalculated($this->id, self::usd('1000.00'), $this->at));

        $this->expectException(ZeroAdjustmentNotAllowed::class);

        $line->addManualAdjustment(self::usd('0.00'), self::comment('Nothing'), self::specialist(), $this->at);
    }

    #[Test]
    public function adjustment_in_a_foreign_currency_is_rejected(): void
    {
        $line = $this->given(new SystemValueCalculated($this->id, self::usd('1000.00'), $this->at));

        $this->expectException(CurrencyMismatch::class);

        $line->addManualAdjustment(Money::of('5.00', Currency::EUR), self::comment('Wrong currency'), self::specialist(), $this->at);
    }

    #[Test]
    public function adjustment_that_would_overflow_is_rejected_before_anything_is_recorded(): void
    {
        $line = $this->given(new SystemValueCalculated($this->id, Money::ofMinor(PHP_INT_MAX, Currency::USD), $this->at));

        try {
            $line->addManualAdjustment(self::usd('0.01'), self::comment('Overflow'), self::specialist(), $this->at);
            self::fail('Overflow was not detected.');
        } catch (InvalidMoneyAmount) {
            self::assertSame([], $line->releaseEvents());
        }
    }

    #[Test]
    public function reconstitution_tracks_the_persisted_version_for_optimistic_concurrency(): void
    {
        $line = $this->given(
            new SystemValueCalculated($this->id, self::usd('1000.00'), $this->at),
            new SystemValueRecalculated($this->id, self::usd('1000.00'), self::usd('1050.00'), $this->at),
        );

        $line->addManualAdjustment(self::usd('1.00'), self::comment('Bonus'), self::specialist(), $this->at);

        self::assertSame(2, $line->persistedVersion());
        self::assertCount(1, $line->releaseEvents());
        self::assertSame(3, $line->persistedVersion());
    }

    #[Test]
    public function the_assignment_example_produces_the_expected_values(): void
    {
        $line = EarningLine::calculate($this->id, self::usd('1000.00'), $this->at);
        $line->recalculate(self::usd('1050.00'), $this->at);
        $line->addManualAdjustment(self::usd('-45.55'), self::comment('Employee declined dental benefit; reversing deduction'), self::specialist(), $this->at);
        $line->recalculate(self::usd('1100.00'), $this->at);
        $line->addManualAdjustment(self::usd('+100.10'), self::comment('Late correction: missed approved overtime bonus'), self::specialist(), $this->at);
        $line->addManualAdjustment(self::usd('-0.10'), self::comment('Minor rounding adjustment'), self::specialist(), $this->at);
        $line->addManualAdjustment(self::usd('-0.20'), self::comment('Second minor rounding adjustment'), self::specialist(), $this->at);
        $line->addManualAdjustment(self::usd('+0.20'), self::comment('Correcting mistake in adjustment #4'), self::specialist(), $this->at);

        self::assertSame('1050.00', $line->systemValue()->toDecimalString());
        self::assertSame(
            ['-45.55', '100.10', '-0.10', '-0.20', '0.20'],
            array_map(static fn($adjustment): string => $adjustment->amount->toDecimalString(), $line->adjustments()),
        );
        self::assertSame('1104.45', $line->currentValue()->toDecimalString());

        // Replaying the recorded history must yield exactly the same state.
        $replayed = EarningLine::reconstituteFromHistory($line->releaseEvents());
        self::assertSame('1104.45', $replayed->currentValue()->toDecimalString());
        self::assertSame(8, $replayed->persistedVersion());
    }

    private function given(DomainEvent ...$history): EarningLine
    {
        return EarningLine::reconstituteFromHistory($history);
    }

    private static function usd(string $amount): Money
    {
        return Money::of($amount, Currency::USD);
    }

    private static function comment(string $text): AdjustmentComment
    {
        return AdjustmentComment::fromString($text);
    }

    private static function specialist(): SpecialistId
    {
        return SpecialistId::fromString('specialist-1');
    }
}
