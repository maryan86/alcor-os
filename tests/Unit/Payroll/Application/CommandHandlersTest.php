<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll\Application;

use App\Payroll\Application\Command\AddManualAdjustment\AddManualAdjustment;
use App\Payroll\Application\Command\AddManualAdjustment\AddManualAdjustmentHandler;
use App\Payroll\Application\Command\RecordSystemCalculation\RecordSystemCalculation;
use App\Payroll\Application\Command\RecordSystemCalculation\RecordSystemCalculationHandler;
use App\Payroll\Domain\EarningLine\Event\ManualAdjustmentAdded;
use App\Payroll\Domain\EarningLine\Event\SystemRecalculationIgnored;
use App\Payroll\Domain\EarningLine\Event\SystemValueCalculated;
use App\Payroll\Domain\EarningLine\Event\SystemValueRecalculated;
use App\Payroll\Domain\EarningLine\Exception\EarningLineNotFound;
use App\Payroll\Domain\EarningLine\Exception\InvalidAdjustmentComment;
use App\Payroll\Domain\EarningLine\ValueObject\EarningLineId;
use App\Payroll\Infrastructure\EventSourcedEarningLineRepository;
use App\Shared\Domain\EventSourcing\DomainEvent;
use App\Shared\Infrastructure\EventSourcing\Store\InMemoryEventStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\FrozenClock;
use Tests\Support\RecordingProjector;

final class CommandHandlersTest extends TestCase
{
    private const string LINE_ID = '0b9a5c34-2d0e-4b8f-9a51-6f1d2c3e4a5b';

    private InMemoryEventStore $store;

    private RecordingProjector $projector;

    private EventSourcedEarningLineRepository $repository;

    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->store = new InMemoryEventStore();
        $this->projector = new RecordingProjector();
        $this->repository = new EventSourcedEarningLineRepository($this->store, $this->projector);
        $this->clock = new FrozenClock();
    }

    #[Test]
    public function first_system_calculation_creates_the_line_and_next_ones_recalculate_it(): void
    {
        $this->calculate('1000.00');
        $this->calculate('1050.00');

        self::assertSame(
            [SystemValueCalculated::class, SystemValueRecalculated::class],
            $this->storedEventClasses(),
        );
    }

    #[Test]
    public function adjustment_is_stored_with_who_when_and_why(): void
    {
        $this->calculate('1050.00');
        $this->clock->advance('+1 hour');

        $this->adjust('-45.55', 'Employee declined dental benefit; reversing deduction');

        $event = $this->store->load('earning_line-' . self::LINE_ID)[1];
        self::assertInstanceOf(ManualAdjustmentAdded::class, $event);
        self::assertSame('-45.55', $event->amount->toDecimalString());
        self::assertSame('Employee declined dental benefit; reversing deduction', $event->comment->toString());
        self::assertSame('specialist-1', $event->adjustedBy->toString());
        self::assertEquals($this->clock->now(), $event->occurredAt);
    }

    #[Test]
    public function recalculation_after_adjustment_is_recorded_as_ignored(): void
    {
        $this->calculate('1050.00');
        $this->adjust('-45.55', 'Declined benefit');
        $this->calculate('1100.00');

        self::assertSame(
            [SystemValueCalculated::class, ManualAdjustmentAdded::class, SystemRecalculationIgnored::class],
            $this->storedEventClasses(),
        );
        self::assertSame('1004.45', $this->repository->get(EarningLineId::fromString(self::LINE_ID))->currentValue()->toDecimalString());
    }

    #[Test]
    public function persisted_events_are_projected(): void
    {
        $this->calculate('1000.00');
        $this->adjust('5.00', 'Bonus');

        self::assertEquals($this->store->load('earning_line-' . self::LINE_ID), $this->projector->projected);
    }

    #[Test]
    public function line_must_exist_before_it_can_be_adjusted(): void
    {
        $this->expectException(EarningLineNotFound::class);

        $this->adjust('5.00', 'Bonus');
    }

    #[Test]
    public function rejected_adjustment_leaves_no_trace(): void
    {
        $this->calculate('1000.00');

        try {
            $this->adjust('5.00', '   ');
            self::fail('An adjustment without a comment was accepted.');
        } catch (InvalidAdjustmentComment) {
            self::assertSame([SystemValueCalculated::class], $this->storedEventClasses());
        }
    }

    private function calculate(string $amount): void
    {
        (new RecordSystemCalculationHandler($this->repository, $this->clock))(new RecordSystemCalculation(self::LINE_ID, $amount, 'USD'));
    }

    private function adjust(string $amount, string $comment): void
    {
        (new AddManualAdjustmentHandler($this->repository, $this->clock))(new AddManualAdjustment(self::LINE_ID, $amount, $comment, 'specialist-1'));
    }

    /** @return list<class-string<DomainEvent>> */
    private function storedEventClasses(): array
    {
        return array_map(static fn(DomainEvent $event): string => $event::class, $this->store->load('earning_line-' . self::LINE_ID));
    }
}
