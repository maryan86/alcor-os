<?php

declare(strict_types=1);

namespace App\Shared\Domain\EventSourcing;

abstract class EventSourcedAggregateRoot
{
    private int $version = 0;

    /** @var list<DomainEvent> */
    private array $pendingEvents = [];

    final protected function __construct() {}

    /** @param iterable<DomainEvent> $history */
    public static function reconstituteFromHistory(iterable $history): static
    {
        $aggregate = new static();

        foreach ($history as $event) {
            $aggregate->apply($event);
            $aggregate->version++;
        }

        return $aggregate;
    }

    public function persistedVersion(): int
    {
        return $this->version - count($this->pendingEvents);
    }

    /** @return list<DomainEvent> */
    public function releaseEvents(): array
    {
        $events = $this->pendingEvents;
        $this->pendingEvents = [];

        return $events;
    }

    protected function recordThat(DomainEvent $event): void
    {
        $this->apply($event);
        $this->version++;
        $this->pendingEvents[] = $event;
    }

    abstract protected function apply(DomainEvent $event): void;
}
