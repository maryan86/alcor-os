<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Domain\EventSourcing\DomainEvent;
use App\Shared\Infrastructure\EventSourcing\Projector;

final class RecordingProjector implements Projector
{
    /** @var list<DomainEvent> */
    public array $projected = [];

    public function project(DomainEvent $event): void
    {
        $this->projected[] = $event;
    }
}
