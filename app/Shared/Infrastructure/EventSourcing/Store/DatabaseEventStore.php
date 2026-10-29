<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\EventSourcing\Store;

use App\Shared\Domain\EventSourcing\ConcurrencyConflict;
use App\Shared\Domain\EventSourcing\DomainEvent;
use App\Shared\Infrastructure\EventSourcing\EventSerializer;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Psr\Clock\ClockInterface;
use stdClass;

final readonly class DatabaseEventStore implements EventStore
{
    public const string TABLE = 'event_store';

    private const string DATE_FORMAT = 'Y-m-d H:i:s.u';

    public function __construct(
        private ConnectionInterface $connection,
        private EventSerializer $serializer,
        private ClockInterface $clock,
    ) {}

    public function append(string $streamId, int $expectedVersion, array $events): void
    {
        if ($events === []) {
            return;
        }

        try {
            $this->connection->transaction(fn() => $this->insert($streamId, $expectedVersion, $events));
        } catch (UniqueConstraintViolationException) {
            // PostgreSQL aborts the transaction on a constraint violation, so the actual version can be read
            // only here, after the (save)point of this append has been rolled back.
            throw ConcurrencyConflict::onStream($streamId, $expectedVersion, $this->currentVersion($streamId));
        }
    }

    public function load(string $streamId): array
    {
        $rows = $this->findStreamRows($streamId);
        $events = [];

        foreach ($rows as $row) {
            $events[] = $this->toEvent($row);
        }

        return $events;
    }

    public function loadAll(): iterable
    {
        $rows = $this->findAllRowsInGlobalOrder();

        foreach ($rows as $row) {
            yield $this->toEvent($row);
        }
    }

    /** @param list<DomainEvent> $events */
    private function insert(string $streamId, int $expectedVersion, array $events): void
    {
        $actualVersion = $this->currentVersion($streamId);

        if ($actualVersion !== $expectedVersion) {
            throw ConcurrencyConflict::onStream($streamId, $expectedVersion, $actualVersion);
        }

        $recordedAt = self::formatUtc($this->clock->now());
        $rows = [];

        foreach ($events as $offset => $event) {
            $rows[] = [
                'stream_id' => $streamId,
                'version' => $expectedVersion + $offset + 1,
                'event_type' => $this->serializer->typeOf($event),
                'payload' => $this->serializer->serializePayload($event),
                'occurred_at' => self::formatUtc($event->occurredAt()),
                'recorded_at' => $recordedAt,
            ];
        }

        $this->connection->table(self::TABLE)->insert($rows);
    }

    /** @return Collection<int, stdClass> */
    private function findStreamRows(string $streamId): Collection
    {
        return $this->connection
            ->table(self::TABLE)
            ->where('stream_id', $streamId)
            ->orderBy('version')
            ->get();
    }

    /** @return LazyCollection<int, stdClass> */
    private function findAllRowsInGlobalOrder(): LazyCollection
    {
        return $this->connection
            ->table(self::TABLE)
            ->orderBy('id')
            ->lazyById(500);
    }

    private function currentVersion(string $streamId): int
    {
        $version = $this->connection
            ->table(self::TABLE)
            ->where('stream_id', $streamId)
            ->max('version');

        if (!is_numeric($version)) {
            return 0;
        }

        return (int) $version;
    }

    private function toEvent(object $row): DomainEvent
    {
        /** @var object{event_type: string, payload: string} $row */
        return $this->serializer->deserialize($row->event_type, $row->payload);
    }

    private static function formatUtc(DateTimeImmutable $dateTime): string
    {
        return $dateTime->setTimezone(new DateTimeZone('UTC'))->format(self::DATE_FORMAT);
    }
}
