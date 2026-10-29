<?php

declare(strict_types=1);

namespace App\Payroll\Infrastructure;

use App\Payroll\Domain\EarningLine\EarningLine;
use App\Payroll\Domain\EarningLine\EarningLineRepository;
use App\Payroll\Domain\EarningLine\Exception\EarningLineNotFound;
use App\Payroll\Domain\EarningLine\ValueObject\EarningLineId;
use App\Shared\Infrastructure\EventSourcing\Projector;
use App\Shared\Infrastructure\EventSourcing\Store\EventStore;

final readonly class EventSourcedEarningLineRepository implements EarningLineRepository
{
    private const string STREAM_PREFIX = 'earning_line-';

    public function __construct(
        private EventStore $eventStore,
        private Projector $projector,
    ) {}

    public function get(EarningLineId $id): EarningLine
    {
        $line = $this->find($id);

        if ($line === null) {
            throw EarningLineNotFound::withId($id);
        }

        return $line;
    }

    public function find(EarningLineId $id): ?EarningLine
    {
        $history = $this->eventStore->load(self::streamId($id));

        if ($history === []) {
            return null;
        }

        return EarningLine::reconstituteFromHistory($history);
    }

    public function save(EarningLine $line): void
    {
        $expectedVersion = $line->persistedVersion();
        $events = $line->releaseEvents();

        $this->eventStore->append(self::streamId($line->id()), $expectedVersion, $events);

        // Same transaction as the append: the read model never lags behind the event store.
        foreach ($events as $event) {
            $this->projector->project($event);
        }
    }

    private static function streamId(EarningLineId $id): string
    {
        return self::STREAM_PREFIX . $id->toString();
    }
}
