<?php

declare(strict_types=1);

namespace App\Payroll\Application\ReadModel\View;

use App\Shared\Domain\Money\Money;
use DateTimeImmutable;

final readonly class AuditEntryView
{
    public function __construct(
        public int $sequence,
        public AuditEntryType $type,
        public Money $amount,
        public Money $valueAfter,
        public DateTimeImmutable $occurredAt,
        public ?int $adjustmentNumber = null,
        public ?string $comment = null,
        public ?string $adjustedBy = null,
        public ?int $correctsAdjustmentNumber = null,
    ) {}
}
