<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Payroll\Application\Command\AddManualAdjustment\AddManualAdjustment;
use App\Payroll\Application\Command\RecordSystemCalculation\RecordSystemCalculation;
use App\Payroll\Application\Query\GetEarningLine\GetEarningLine;
use App\Payroll\Application\ReadModel\View\AuditEntryType;
use App\Payroll\Application\ReadModel\View\AuditEntryView;
use App\Payroll\Application\ReadModel\View\EarningLineView;
use App\Payroll\Domain\EarningLine\Exception\EarningLineNotFound;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Clock\ClockInterface;
use Tests\Support\FrozenClock;
use Tests\TestCase;

final class AssignmentScenarioTest extends TestCase
{
    use RefreshDatabase;

    private const string LINE_ID = '0b9a5c34-2d0e-4b8f-9a51-6f1d2c3e4a5b';

    #[Test]
    public function each_step_yields_the_expected_current_value(): void
    {
        $steps = [
            [new RecordSystemCalculation(self::LINE_ID, '1000.00', 'USD'), '1000.00'],
            [new RecordSystemCalculation(self::LINE_ID, '1050.00', 'USD'), '1050.00'],
            [$this->adjustment('-45.55', 'Employee declined dental benefit; reversing deduction'), '1004.45'],
            [new RecordSystemCalculation(self::LINE_ID, '1100.00', 'USD'), '1004.45'],
            [$this->adjustment('+100.10', 'Late correction: missed approved overtime bonus'), '1104.55'],
            [$this->adjustment('-0.10', 'Minor rounding adjustment'), '1104.45'],
            [$this->adjustment('-0.20', 'Second minor rounding adjustment'), '1104.25'],
            [$this->adjustment('+0.20', 'Correcting mistake in adjustment #4', corrects: 4), '1104.45'],
        ];

        foreach ($steps as $index => [$command, $expectedValue]) {
            $this->commandBus()->dispatch($command);

            self::assertSame($expectedValue, $this->lineView()->currentValue->toDecimalString(), sprintf('Step %d', $index + 1));
        }
    }

    #[Test]
    public function final_audit_history_matches_the_expected_one(): void
    {
        $this->runScenario();
        $view = $this->lineView();

        self::assertTrue($view->isManuallyAdjusted);
        self::assertSame('1050.00', $view->systemValue->toDecimalString(), 'system value frozen at step 3');
        self::assertSame(
            [
                [1, '-45.55', 'Employee declined dental benefit; reversing deduction', null],
                [2, '100.10', 'Late correction: missed approved overtime bonus', null],
                [3, '-0.10', 'Minor rounding adjustment', null],
                [4, '-0.20', 'Second minor rounding adjustment', null],
                [5, '0.20', 'Correcting mistake in adjustment #4', 4],
            ],
            array_map(
                static fn(AuditEntryView $entry): array => [
                    $entry->adjustmentNumber,
                    $entry->amount->toDecimalString(),
                    $entry->comment,
                    $entry->correctsAdjustmentNumber,
                ],
                $view->adjustments(),
            ),
        );
        self::assertSame('1104.45', $view->currentValue->toDecimalString());
    }

    #[Test]
    public function full_timeline_keeps_every_system_event_including_the_ignored_recalculation(): void
    {
        $this->runScenario();

        self::assertSame(
            [
                [AuditEntryType::SystemCalculated, '1000.00', '1000.00'],
                [AuditEntryType::SystemRecalculated, '1050.00', '1050.00'],
                [AuditEntryType::ManualAdjustment, '-45.55', '1004.45'],
                [AuditEntryType::SystemRecalculationIgnored, '1100.00', '1004.45'],
                [AuditEntryType::ManualAdjustment, '100.10', '1104.55'],
                [AuditEntryType::ManualAdjustment, '-0.10', '1104.45'],
                [AuditEntryType::ManualAdjustment, '-0.20', '1104.25'],
                [AuditEntryType::ManualAdjustment, '0.20', '1104.45'],
            ],
            array_map(
                static fn(AuditEntryView $entry): array => [$entry->type, $entry->amount->toDecimalString(), $entry->valueAfter->toDecimalString()],
                $this->lineView()->history,
            ),
        );
    }

    #[Test]
    public function line_can_be_viewed_as_it_was_at_any_moment(): void
    {
        $clock = new FrozenClock('2026-01-15 09:00:00');
        $this->app->instance(ClockInterface::class, $clock);

        // Steps happen one minute apart: step 1 at 09:00, step 3 at 09:02, step 4 at 09:03, ...
        $this->runScenario(static fn() => $clock->advance('+1 minute'));

        $afterStep2 = $this->lineView('2026-01-15 09:01:30');
        self::assertSame('1050.00', $afterStep2->currentValue->toDecimalString());
        self::assertFalse($afterStep2->isManuallyAdjusted);

        $afterStep4 = $this->lineView('2026-01-15 09:03:30');
        self::assertSame('1004.45', $afterStep4->currentValue->toDecimalString());
        self::assertSame('1050.00', $afterStep4->systemValue->toDecimalString(), 'frozen, the attempted 1100.00 is ignored');
        self::assertCount(1, $afterStep4->adjustments());

        self::assertSame('1104.45', $this->lineView()->currentValue->toDecimalString());

        $this->expectException(EarningLineNotFound::class);
        $this->lineView('2026-01-15 08:59:59');
    }

    private function runScenario(?Closure $afterEachStep = null): void
    {
        foreach ([
            new RecordSystemCalculation(self::LINE_ID, '1000.00', 'USD'),
            new RecordSystemCalculation(self::LINE_ID, '1050.00', 'USD'),
            $this->adjustment('-45.55', 'Employee declined dental benefit; reversing deduction'),
            new RecordSystemCalculation(self::LINE_ID, '1100.00', 'USD'),
            $this->adjustment('+100.10', 'Late correction: missed approved overtime bonus'),
            $this->adjustment('-0.10', 'Minor rounding adjustment'),
            $this->adjustment('-0.20', 'Second minor rounding adjustment'),
            $this->adjustment('+0.20', 'Correcting mistake in adjustment #4', corrects: 4),
        ] as $command) {
            $this->commandBus()->dispatch($command);

            if ($afterEachStep !== null) {
                $afterEachStep();
            }
        }
    }

    private function adjustment(string $amount, string $comment, ?int $corrects = null): AddManualAdjustment
    {
        return new AddManualAdjustment(self::LINE_ID, $amount, $comment, 'specialist-1', $corrects);
    }

    private function lineView(?string $asOf = null): EarningLineView
    {
        return $this->app->make(QueryBus::class)->ask(new GetEarningLine(self::LINE_ID, $asOf));
    }

    private function commandBus(): CommandBus
    {
        return $this->app->make(CommandBus::class);
    }
}
