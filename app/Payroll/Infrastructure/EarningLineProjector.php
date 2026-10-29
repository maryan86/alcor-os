<?php

declare(strict_types=1);

namespace App\Payroll\Infrastructure;

use App\Payroll\Application\ReadModel\View\AuditEntryType;
use App\Payroll\Domain\EarningLine\Event\ManualAdjustmentAdded;
use App\Payroll\Domain\EarningLine\Event\SystemRecalculationIgnored;
use App\Payroll\Domain\EarningLine\Event\SystemValueCalculated;
use App\Payroll\Domain\EarningLine\Event\SystemValueRecalculated;
use App\Payroll\Infrastructure\ReadModel\Record\AuditEntryRecord;
use App\Payroll\Infrastructure\ReadModel\Record\EarningLineRecord;
use App\Shared\Domain\EventSourcing\DomainEvent;
use App\Shared\Infrastructure\EventSourcing\Projector;

final readonly class EarningLineProjector implements Projector
{
    public function project(DomainEvent $event): void
    {
        match (true) {
            $event instanceof SystemValueCalculated => $this->whenSystemValueCalculated($event),
            $event instanceof SystemValueRecalculated => $this->whenSystemValueRecalculated($event),
            $event instanceof SystemRecalculationIgnored => $this->whenSystemRecalculationIgnored($event),
            $event instanceof ManualAdjustmentAdded => $this->whenManualAdjustmentAdded($event),
            default => null,
        };
    }

    public function reset(): void
    {
        AuditEntryRecord::query()->delete();
        EarningLineRecord::query()->delete();
    }

    private function whenSystemValueCalculated(SystemValueCalculated $event): void
    {
        EarningLineRecord::query()->create([
            'line_id' => $event->aggregateId(),
            'currency' => $event->amount->currency,
            'system_value_minor' => $event->amount->minorAmount,
            'current_value_minor' => $event->amount->minorAmount,
            'is_manually_adjusted' => false,
            'updated_at' => $event->occurredAt,
        ]);

        $this->appendAuditEntry(
            event: $event,
            type: AuditEntryType::SystemCalculated,
            amountMinor: $event->amount->minorAmount,
            valueAfterMinor: $event->amount->minorAmount,
        );
    }

    private function whenSystemValueRecalculated(SystemValueRecalculated $event): void
    {
        $line = $this->findLine($event);
        $line->system_value_minor = $event->newAmount->minorAmount;
        $line->current_value_minor = $event->newAmount->minorAmount;
        $line->updated_at = $event->occurredAt;
        $line->save();

        $this->appendAuditEntry(
            event: $event,
            type: AuditEntryType::SystemRecalculated,
            amountMinor: $event->newAmount->minorAmount,
            valueAfterMinor: $event->newAmount->minorAmount,
        );
    }

    private function whenSystemRecalculationIgnored(SystemRecalculationIgnored $event): void
    {
        $line = $this->findLine($event);

        $this->appendAuditEntry(
            event: $event,
            type: AuditEntryType::SystemRecalculationIgnored,
            amountMinor: $event->attemptedAmount->minorAmount,
            valueAfterMinor: $line->current_value_minor,
        );
    }

    private function whenManualAdjustmentAdded(ManualAdjustmentAdded $event): void
    {
        $line = $this->findLine($event);

        // The domain has already validated the sum (including overflow), so plain integer arithmetic is safe here.
        $line->current_value_minor += $event->amount->minorAmount;
        $line->is_manually_adjusted = true;
        $line->updated_at = $event->occurredAt;
        $line->save();

        $this->appendAuditEntry(
            event: $event,
            type: AuditEntryType::ManualAdjustment,
            amountMinor: $event->amount->minorAmount,
            valueAfterMinor: $line->current_value_minor,
            details: [
                'adjustment_number' => $event->adjustmentNumber,
                'corrects_adjustment_number' => $event->correctsAdjustmentNumber,
                'comment' => $event->comment->toString(),
                'adjusted_by' => $event->adjustedBy->toString(),
            ],
        );
    }

    /** @param array<string, scalar|null> $details */
    private function appendAuditEntry(
        DomainEvent $event,
        AuditEntryType $type,
        int $amountMinor,
        int $valueAfterMinor,
        array $details = [],
    ): void {
        AuditEntryRecord::query()->create([
            'line_id' => $event->aggregateId(),
            'sequence' => $this->nextSequence($event),
            'type' => $type,
            'amount_minor' => $amountMinor,
            'value_after_minor' => $valueAfterMinor,
            'adjustment_number' => $details['adjustment_number'] ?? null,
            'corrects_adjustment_number' => $details['corrects_adjustment_number'] ?? null,
            'comment' => $details['comment'] ?? null,
            'adjusted_by' => $details['adjusted_by'] ?? null,
            'occurred_at' => $event->occurredAt(),
        ]);
    }

    private function nextSequence(DomainEvent $event): int
    {
        $lastSequence = AuditEntryRecord::query()
            ->where('line_id', $event->aggregateId())
            ->max('sequence');

        if (!is_numeric($lastSequence)) {
            return 1;
        }

        return (int) $lastSequence + 1;
    }

    private function findLine(DomainEvent $event): EarningLineRecord
    {
        return EarningLineRecord::query()->findOrFail($event->aggregateId());
    }
}
