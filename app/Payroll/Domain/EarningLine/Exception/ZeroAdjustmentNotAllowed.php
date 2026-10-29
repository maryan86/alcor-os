<?php

declare(strict_types=1);

namespace App\Payroll\Domain\EarningLine\Exception;

use DomainException;

final class ZeroAdjustmentNotAllowed extends DomainException
{
    public static function create(): self
    {
        return new self('A manual adjustment must be a non-zero positive or negative amount.');
    }
}
