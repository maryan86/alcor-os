<?php

declare(strict_types=1);

namespace App\Payroll\Domain\EarningLine\Event;

use App\Payroll\Domain\EarningLine\ValueObject\EarningLineId;
use App\Shared\Domain\EventSourcing\DomainEvent;
use App\Shared\Domain\EventSourcing\Payload;
use App\Shared\Domain\Money\Money;
use DateTimeImmutable;

final readonly class SystemValueCalculated implements DomainEvent
{
    public function __construct(
        public EarningLineId $lineId,
        public Money $amount,
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
            'amount_minor' => $this->amount->minorAmount,
            'currency' => $this->amount->currency->value,
            'occurred_at' => Payload::formatDateTime($this->occurredAt),
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new self(
            EarningLineId::fromString(Payload::string($payload, 'line_id')),
            Payload::money($payload, 'amount_minor'),
            Payload::dateTime($payload, 'occurred_at'),
        );
    }
}
