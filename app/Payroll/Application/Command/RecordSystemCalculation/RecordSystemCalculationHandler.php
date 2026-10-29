<?php

declare(strict_types=1);

namespace App\Payroll\Application\Command\RecordSystemCalculation;

use App\Payroll\Domain\EarningLine\EarningLine;
use App\Payroll\Domain\EarningLine\EarningLineRepository;
use App\Payroll\Domain\EarningLine\ValueObject\EarningLineId;
use App\Shared\Domain\Money\Currency;
use App\Shared\Domain\Money\Money;
use Psr\Clock\ClockInterface;

final readonly class RecordSystemCalculationHandler
{
    public function __construct(
        private EarningLineRepository $lines,
        private ClockInterface $clock,
    ) {}

    public function __invoke(RecordSystemCalculation $command): void
    {
        $id = EarningLineId::fromString($command->lineId);
        $amount = Money::of($command->amount, Currency::fromCode($command->currency));
        $now = $this->clock->now();

        $line = $this->lines->find($id);

        if ($line === null) {
            $this->lines->save(EarningLine::calculate($id, $amount, $now));

            return;
        }

        $line->recalculate($amount, $now);
        $this->lines->save($line);
    }
}
