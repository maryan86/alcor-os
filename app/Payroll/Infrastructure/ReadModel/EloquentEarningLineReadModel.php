<?php

declare(strict_types=1);

namespace App\Payroll\Infrastructure\ReadModel;

use App\Payroll\Application\ReadModel\EarningLineReadModel;
use App\Payroll\Application\ReadModel\View\AuditEntryType;
use App\Payroll\Application\ReadModel\View\AuditEntryView;
use App\Payroll\Application\ReadModel\View\EarningLineView;
use App\Payroll\Infrastructure\ReadModel\Record\AuditEntryRecord;
use App\Payroll\Infrastructure\ReadModel\Record\EarningLineRecord;
use App\Shared\Domain\Money\Currency;
use App\Shared\Domain\Money\Money;
use DateTimeImmutable;

final readonly class EloquentEarningLineReadModel implements EarningLineReadModel
{
    public function find(string $lineId, ?DateTimeImmutable $asOf = null): ?EarningLineView
    {
        $line = $this->findLine($lineId);

        if ($line === null) {
            return null;
        }

        $history = $this->history($line, $asOf);

        // Before its first calculation the line did not exist yet.
        if ($history === []) {
            return null;
        }

        if ($asOf === null) {
            return new EarningLineView(
                lineId: $line->line_id,
                systemValue: Money::ofMinor($line->system_value_minor, $line->currency),
                currentValue: Money::ofMinor($line->current_value_minor, $line->currency),
                isManuallyAdjusted: $line->is_manually_adjusted,
                history: $history,
            );
        }

        return $this->pointInTimeView($line->line_id, $history);
    }

    /** @return list<AuditEntryView> */
    private function history(EarningLineRecord $line, ?DateTimeImmutable $asOf): array
    {
        $entries = $line->auditEntries;
        $history = [];

        foreach ($entries as $entry) {
            if ($asOf !== null && $entry->occurred_at > $asOf) {
                break;
            }

            $history[] = $this->toAuditEntryView($entry, $line->currency);
        }

        return $history;
    }

    /** @param non-empty-list<AuditEntryView> $history */
    private function pointInTimeView(string $lineId, array $history): EarningLineView
    {
        $systemValue = $history[0]->amount;
        $isManuallyAdjusted = false;

        foreach ($history as $entry) {
            if ($entry->type->setsSystemValue()) {
                $systemValue = $entry->amount;
            }

            if ($entry->type === AuditEntryType::ManualAdjustment) {
                $isManuallyAdjusted = true;
            }
        }

        return new EarningLineView(
            lineId: $lineId,
            systemValue: $systemValue,
            currentValue: $history[array_key_last($history)]->valueAfter,
            isManuallyAdjusted: $isManuallyAdjusted,
            history: $history,
        );
    }

    private function toAuditEntryView(AuditEntryRecord $entry, Currency $currency): AuditEntryView
    {
        return new AuditEntryView(
            sequence: $entry->sequence,
            type: $entry->type,
            amount: Money::ofMinor($entry->amount_minor, $currency),
            valueAfter: Money::ofMinor($entry->value_after_minor, $currency),
            occurredAt: $entry->occurred_at,
            adjustmentNumber: $entry->adjustment_number,
            comment: $entry->comment,
            adjustedBy: $entry->adjusted_by,
            correctsAdjustmentNumber: $entry->corrects_adjustment_number,
        );
    }

    private function findLine(string $lineId): ?EarningLineRecord
    {
        return EarningLineRecord::query()
            ->with('auditEntries')
            ->find($lineId);
    }
}
