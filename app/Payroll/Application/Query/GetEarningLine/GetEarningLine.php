<?php

declare(strict_types=1);

namespace App\Payroll\Application\Query\GetEarningLine;

use App\Payroll\Application\ReadModel\View\EarningLineView;
use App\Shared\Application\Bus\Query\Query;

/** @implements Query<EarningLineView> */
final readonly class GetEarningLine implements Query
{
    public function __construct(
        public string $lineId,
        public ?string $asOf = null,
    ) {}
}
