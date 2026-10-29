<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\EventSourcing\Store;

use App\Shared\Domain\EventSourcing\ConcurrencyConflict;
use App\Shared\Domain\EventSourcing\DomainEvent;

interface EventStore
{
    /**
     * @param list<DomainEvent> $events
     *
     * @throws ConcurrencyConflict
     */
    public function append(string $streamId, int $expectedVersion, array $events): void;

    /** @return list<DomainEvent> */
    public function load(string $streamId): array;

    /** @return iterable<DomainEvent> */
    public function loadAll(): iterable;
}
