<?php

declare(strict_types=1);

namespace App\Payroll\Domain\EarningLine\Exception;

use DomainException;

final class UnknownAdjustment extends DomainException
{
    public static function withNumber(int $number): self
    {
        return new self(sprintf('Adjustment #%d does not exist on this line, so it cannot be corrected.', $number));
    }
}
