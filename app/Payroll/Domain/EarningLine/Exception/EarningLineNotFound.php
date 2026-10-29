<?php

declare(strict_types=1);

namespace App\Payroll\Domain\EarningLine\Exception;

use App\Payroll\Domain\EarningLine\ValueObject\EarningLineId;
use DomainException;

final class EarningLineNotFound extends DomainException
{
    public static function withId(EarningLineId $id): self
    {
        return new self(sprintf('Earning line "%s" does not exist; it must be calculated by the system first.', $id->toString()));
    }
}
