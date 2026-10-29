<?php

declare(strict_types=1);

namespace App\Shared\Domain\EventSourcing;

use DateTimeImmutable;

interface DomainEvent
{
    public function aggregateId(): string;

    public function occurredAt(): DateTimeImmutable;

    /** @return array<string, scalar|null> */
    public function toPayload(): array;

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): static;
}
