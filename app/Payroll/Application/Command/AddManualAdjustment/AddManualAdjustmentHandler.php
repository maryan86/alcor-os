<?php

declare(strict_types=1);

namespace App\Payroll\Application\Command\AddManualAdjustment;

use App\Payroll\Domain\EarningLine\EarningLineRepository;
use App\Payroll\Domain\EarningLine\ValueObject\AdjustmentComment;
use App\Payroll\Domain\EarningLine\ValueObject\EarningLineId;
use App\Payroll\Domain\EarningLine\ValueObject\SpecialistId;
use App\Shared\Domain\Money\Money;
use Psr\Clock\ClockInterface;

final readonly class AddManualAdjustmentHandler
{
    public function __construct(
        private EarningLineRepository $lines,
        private ClockInterface $clock,
    ) {}

    public function __invoke(AddManualAdjustment $command): void
    {
        $line = $this->lines->get(EarningLineId::fromString($command->lineId));

        $line->addManualAdjustment(
            Money::of($command->amount, $line->currentValue()->currency),
            AdjustmentComment::fromString($command->comment),
            SpecialistId::fromString($command->specialistId),
            $this->clock->now(),
            $command->corrects,
        );

        $this->lines->save($line);
    }
}
