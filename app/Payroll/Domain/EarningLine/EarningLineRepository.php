<?php

declare(strict_types=1);

namespace App\Payroll\Domain\EarningLine;

use App\Payroll\Domain\EarningLine\Exception\EarningLineNotFound;
use App\Payroll\Domain\EarningLine\ValueObject\EarningLineId;
use App\Shared\Domain\EventSourcing\ConcurrencyConflict;

interface EarningLineRepository
{
    /** @throws EarningLineNotFound */
    public function get(EarningLineId $id): EarningLine;

    public function find(EarningLineId $id): ?EarningLine;

    /** @throws ConcurrencyConflict */
    public function save(EarningLine $line): void;
}
