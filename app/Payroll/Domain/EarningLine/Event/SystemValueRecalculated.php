<?php

declare(strict_types=1);

namespace App\Payroll\Domain\EarningLine\Event;

use App\Payroll\Domain\EarningLine\ValueObject\EarningLineId;
use App\Shared\Domain\EventSourcing\DomainEvent;
use App\Shared\Domain\EventSourcing\Payload;
use App\Shared\Domain\Money\Money;
use DateTimeImmutable;

final readonly class SystemValueRecalculated implements DomainEvent
{
    public function __construct(
        public EarningLineId $lineId,
        public Money $previousAmount,
        public Money $newAmount,
        public DateTimeImmutable $occurredAt,
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
            'previous_amount_minor' => $this->previousAmount->minorAmount,
            'new_amount_minor' => $this->newAmount->minorAmount,
            'currency' => $this->newAmount->currency->value,
            'occurred_at' => Payload::formatDateTime($this->occurredAt),
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new self(
            EarningLineId::fromString(Payload::string($payload, 'line_id')),
            Payload::money($payload, 'previous_amount_minor'),
            Payload::money($payload, 'new_amount_minor'),
            Payload::dateTime($payload, 'occurred_at'),
        );
    }
}
