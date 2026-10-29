<?php

declare(strict_types=1);

namespace App\Payroll\Application\ReadModel\View;

use App\Shared\Domain\Money\Money;

final readonly class EarningLineView
{
    /** @param list<AuditEntryView> $history */
    public function __construct(
        public string $lineId,
        public Money $systemValue,
        public Money $currentValue,
        public bool $isManuallyAdjusted,
        public array $history,
    ) {}

    /** @return list<AuditEntryView> */
    public function adjustments(): array
    {
        return array_values(array_filter(
            $this->history,
            static fn(AuditEntryView $entry): bool => $entry->type === AuditEntryType::ManualAdjustment,
        ));
    }
}
