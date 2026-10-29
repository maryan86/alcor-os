<?php

declare(strict_types=1);

namespace App\Payroll\UserInterface\Console\Presenter;

use App\Payroll\Application\ReadModel\View\AuditEntryType;
use App\Payroll\Application\ReadModel\View\AuditEntryView;
use App\Payroll\Application\ReadModel\View\EarningLineView;
use Illuminate\Console\Command;

final class EarningLinePresenter
{
    /** @return list<array{string, string, string, string}> */
    public static function auditHistoryRows(EarningLineView $line): array
    {
        $rows = [[
            $line->isManuallyAdjusted ? 'System value (frozen)' : 'System value',
            MoneyFormatter::format($line->systemValue),
            '',
            '',
        ]];

        $adjustments = $line->adjustments();

        foreach ($adjustments as $adjustment) {
            $rows[] = [
                self::adjustmentLabel($adjustment),
                MoneyFormatter::format($adjustment->amount, signed: true),
                $adjustment->comment ?? '',
                $adjustment->adjustedBy ?? '',
            ];
        }

        $rows[] = ['Current value', MoneyFormatter::format($line->currentValue), '', ''];

        return $rows;
    }

    public static function render(EarningLineView $line, Command $output): void
    {
        $output->info(sprintf('Earning line %s', $line->lineId));

        $output->line('Audit history');
        $output->table(['Entry', 'Value', 'Comment', 'By'], self::auditHistoryRows($line));

        $output->line('Event timeline');
        $output->table(
            ['#', 'Occurred at (UTC)', 'Event', 'Amount', 'Value after'],
            array_map(static fn(AuditEntryView $entry): array => [
                (string) $entry->sequence,
                $entry->occurredAt->format('Y-m-d H:i:s.u'),
                self::describe($entry->type),
                MoneyFormatter::format($entry->amount, signed: $entry->type === AuditEntryType::ManualAdjustment),
                MoneyFormatter::format($entry->valueAfter),
            ], $line->history),
        );
    }

    private static function describe(AuditEntryType $type): string
    {
        return match ($type) {
            AuditEntryType::SystemCalculated => 'System calculated',
            AuditEntryType::SystemRecalculated => 'System recalculated',
            AuditEntryType::SystemRecalculationIgnored => 'System recalculation ignored (line is manually adjusted)',
            AuditEntryType::ManualAdjustment => 'Manual adjustment',
        };
    }

    private static function adjustmentLabel(AuditEntryView $adjustment): string
    {
        $label = sprintf('Adjustment %d', $adjustment->adjustmentNumber ?? 0);

        if ($adjustment->correctsAdjustmentNumber === null) {
            return $label;
        }

        return sprintf('%s (corrects #%d)', $label, $adjustment->correctsAdjustmentNumber);
    }
}
