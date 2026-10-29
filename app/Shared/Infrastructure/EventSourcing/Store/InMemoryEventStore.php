<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\EventSourcing\Store;

use App\Shared\Domain\EventSourcing\ConcurrencyConflict;
use App\Shared\Domain\EventSourcing\DomainEvent;

final class InMemoryEventStore implements EventStore
{
    /** @var array<string, list<DomainEvent>> */
    private array $streams = [];

    /** @var list<DomainEvent> */
    private array $log = [];

    public function append(string $streamId, int $expectedVersion, array $events): void
    {
        $actualVersion = count($this->streams[$streamId] ?? []);

        if ($actualVersion !== $expectedVersion) {
            throw ConcurrencyConflict::onStream($streamId, $expectedVersion, $actualVersion);
        }

        foreach ($events as $event) {
            $this->streams[$streamId][] = $event;
            $this->log[] = $event;
        }
    }

    public function load(string $streamId): array
    {
        return $this->streams[$streamId] ?? [];
    }

    public function loadAll(): iterable
    {
        return $this->log;
    }
}
