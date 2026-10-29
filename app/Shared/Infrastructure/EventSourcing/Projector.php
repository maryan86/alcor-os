<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\EventSourcing;

use App\Shared\Domain\EventSourcing\DomainEvent;

interface Projector
{
    public function project(DomainEvent $event): void;
}
