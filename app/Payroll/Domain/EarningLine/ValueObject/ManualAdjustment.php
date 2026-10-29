<?php

declare(strict_types=1);

namespace App\Payroll\Domain\EarningLine\ValueObject;

use App\Shared\Domain\Money\Money;
use DateTimeImmutable;

final readonly class ManualAdjustment
{
    public function __construct(
        public int $number,
        public Money $amount,
        public AdjustmentComment $comment,
        public SpecialistId $adjustedBy,
        public DateTimeImmutable $adjustedAt,
        public ?int $corrects = null,
    ) {}
}
