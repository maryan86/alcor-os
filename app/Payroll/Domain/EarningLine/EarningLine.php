<?php

declare(strict_types=1);

namespace App\Payroll\Domain\EarningLine;

use App\Payroll\Domain\EarningLine\Event\ManualAdjustmentAdded;
use App\Payroll\Domain\EarningLine\Event\SystemRecalculationIgnored;
use App\Payroll\Domain\EarningLine\Event\SystemValueCalculated;
use App\Payroll\Domain\EarningLine\Event\SystemValueRecalculated;
use App\Payroll\Domain\EarningLine\Exception\UnknownAdjustment;
use App\Payroll\Domain\EarningLine\Exception\ZeroAdjustmentNotAllowed;
use App\Payroll\Domain\EarningLine\ValueObject\AdjustmentComment;
use App\Payroll\Domain\EarningLine\ValueObject\EarningLineId;
use App\Payroll\Domain\EarningLine\ValueObject\ManualAdjustment;
use App\Payroll\Domain\EarningLine\ValueObject\SpecialistId;
use App\Shared\Domain\EventSourcing\DomainEvent;
use App\Shared\Domain\EventSourcing\EventSourcedAggregateRoot;
use App\Shared\Domain\Money\Exception\CurrencyMismatch;
use App\Shared\Domain\Money\Money;
use DateTimeImmutable;
use LogicException;

final class EarningLine extends EventSourcedAggregateRoot
{
    private EarningLineId $id;

    private Money $systemValue;

    /** @var list<ManualAdjustment> */
    private array $adjustments = [];

    public static function calculate(EarningLineId $id, Money $systemValue, DateTimeImmutable $at): self
    {
        $line = new self();
        $line->recordThat(new SystemValueCalculated($id, $systemValue, $at));

        return $line;
    }

    public function recalculate(Money $newSystemValue, DateTimeImmutable $at): void
    {
        $this->assertLineCurrency($newSystemValue);

        if ($this->isManuallyAdjusted()) {
            $this->recordThat(new SystemRecalculationIgnored($this->id, $newSystemValue, $at));

            return;
        }

        if ($newSystemValue->equals($this->systemValue)) {
            return;
        }

        $this->recordThat(new SystemValueRecalculated($this->id, $this->systemValue, $newSystemValue, $at));
    }

    public function addManualAdjustment(
        Money $amount,
        AdjustmentComment $comment,
        SpecialistId $adjustedBy,
        DateTimeImmutable $at,
        ?int $corrects = null,
    ): void {
        $this->assertLineCurrency($amount);

        if ($amount->isZero()) {
            throw ZeroAdjustmentNotAllowed::create();
        }

        if ($corrects !== null && !$this->hasAdjustment($corrects)) {
            throw UnknownAdjustment::withNumber($corrects);
        }

        $this->currentValue()->add($amount);

        $this->recordThat(new ManualAdjustmentAdded(
            $this->id,
            count($this->adjustments) + 1,
            $amount,
            $comment,
            $adjustedBy,
            $at,
            $corrects,
        ));
    }

    public function id(): EarningLineId
    {
        return $this->id;
    }

    public function systemValue(): Money
    {
        return $this->systemValue;
    }

    public function currentValue(): Money
    {
        return array_reduce(
            $this->adjustments,
            static fn(Money $total, ManualAdjustment $adjustment): Money => $total->add($adjustment->amount),
            $this->systemValue,
        );
    }

    /** @return list<ManualAdjustment> */
    public function adjustments(): array
    {
        return $this->adjustments;
    }

    public function isManuallyAdjusted(): bool
    {
        return $this->adjustments !== [];
    }

    protected function apply(DomainEvent $event): void
    {
        match (true) {
            $event instanceof SystemValueCalculated => $this->applySystemValueCalculated($event),
            $event instanceof SystemValueRecalculated => $this->systemValue = $event->newAmount,
            $event instanceof ManualAdjustmentAdded => $this->adjustments[] = new ManualAdjustment(
                $event->adjustmentNumber,
                $event->amount,
                $event->comment,
                $event->adjustedBy,
                $event->occurredAt,
                $event->correctsAdjustmentNumber,
            ),
            $event instanceof SystemRecalculationIgnored => null, // audit-only fact, state is unchanged
            default => throw new LogicException(sprintf('%s cannot apply %s.', self::class, $event::class)),
        };
    }

    private function applySystemValueCalculated(SystemValueCalculated $event): void
    {
        $this->id = $event->lineId;
        $this->systemValue = $event->amount;
    }

    private function assertLineCurrency(Money $amount): void
    {
        if (!$amount->isSameCurrencyAs($this->systemValue)) {
            throw CurrencyMismatch::between($this->systemValue->currency, $amount->currency);
        }
    }

    private function hasAdjustment(int $number): bool
    {
        return $number >= 1 && $number <= count($this->adjustments);
    }
}
