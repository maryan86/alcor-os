<?php

declare(strict_types=1);

namespace App\Payroll\Application\ReadModel;

use App\Payroll\Application\ReadModel\View\EarningLineView;
use DateTimeImmutable;

interface EarningLineReadModel
{
    public function find(string $lineId, ?DateTimeImmutable $asOf = null): ?EarningLineView;
}
