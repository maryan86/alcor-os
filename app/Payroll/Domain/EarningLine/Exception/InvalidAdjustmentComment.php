<?php

declare(strict_types=1);

namespace App\Payroll\Domain\EarningLine\Exception;

use DomainException;

final class InvalidAdjustmentComment extends DomainException
{
    public static function empty(): self
    {
        return new self('A manual adjustment requires a comment explaining why it was made.');
    }

    public static function tooLong(int $maxLength): self
    {
        return new self(sprintf('Adjustment comment must not exceed %d characters.', $maxLength));
    }
}
