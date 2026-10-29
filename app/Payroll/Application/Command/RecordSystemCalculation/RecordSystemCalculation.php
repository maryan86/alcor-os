<?php

declare(strict_types=1);

namespace App\Payroll\Application\Command\RecordSystemCalculation;

use App\Shared\Application\Bus\Command\Command;

final readonly class RecordSystemCalculation implements Command
{
    public function __construct(
        public string $lineId,
        public string $amount,
        public string $currency,
    ) {}
}
