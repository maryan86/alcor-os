<?php

declare(strict_types=1);

namespace App\Payroll\Domain\EarningLine\Event;

use App\Payroll\Domain\EarningLine\ValueObject\AdjustmentComment;
use App\Payroll\Domain\EarningLine\ValueObject\EarningLineId;
use App\Payroll\Domain\EarningLine\ValueObject\SpecialistId;
use App\Shared\Domain\EventSourcing\DomainEvent;
use App\Shared\Domain\EventSourcing\Payload;
use App\Shared\Domain\Money\Money;
use DateTimeImmutable;

final readonly class ManualAdjustmentAdded implements DomainEvent
{
    public function __construct(
        public EarningLineId $lineId,
        public int $adjustmentNumber,
        public Money $amount,
        public AdjustmentComment $comment,
        public SpecialistId $adjustedBy,
        public DateTimeImmutable $occurredAt,
        public ?int $correctsAdjustmentNumber = null,
    ) {}

    public function aggregateId(): string
    {
        return $this->lineId->toString();
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function toPayload(): array
    {
        return [
            'line_id' => $this->lineId->toString(),
            'adjustment_number' => $this->adjustmentNumber,
            'amount_minor' => $this->amount->minorAmount,
            'currency' => $this->amount->currency->value,
            'comment' => $this->comment->toString(),
            'adjusted_by' => $this->adjustedBy->toString(),
            'occurred_at' => Payload::formatDateTime($this->occurredAt),
            'corrects_adjustment_number' => $this->correctsAdjustmentNumber,
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new self(
            EarningLineId::fromString(Payload::string($payload, 'line_id')),
            Payload::int($payload, 'adjustment_number'),
            Payload::money($payload, 'amount_minor'),
            AdjustmentComment::fromString(Payload::string($payload, 'comment')),
            SpecialistId::fromString(Payload::string($payload, 'adjusted_by')),
            Payload::dateTime($payload, 'occurred_at'),
            Payload::nullableInt($payload, 'corrects_adjustment_number'),
        );
    }
}
