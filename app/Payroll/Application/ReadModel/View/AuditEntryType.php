<?php

declare(strict_types=1);

namespace App\Payroll\Application\ReadModel\View;

enum AuditEntryType: string
{
    case SystemCalculated = 'system_calculated';
    case SystemRecalculated = 'system_recalculated';
    case SystemRecalculationIgnored = 'system_recalculation_ignored';
    case ManualAdjustment = 'manual_adjustment';

    public function setsSystemValue(): bool
    {
        return in_array($this, [self::SystemCalculated, self::SystemRecalculated], true);
    }
}
