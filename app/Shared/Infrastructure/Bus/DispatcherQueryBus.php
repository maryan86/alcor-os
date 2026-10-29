<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\HandlerNotFound;
use App\Shared\Application\Bus\Query\Query;
use App\Shared\Application\Bus\Query\QueryBus;
use Illuminate\Bus\Dispatcher;

final readonly class DispatcherQueryBus implements QueryBus
{
    public function __construct(private Dispatcher $dispatcher) {}

    /** @param array<class-string<Query<mixed>>, class-string> $handlers */
    public function map(array $handlers): void
    {
        $this->dispatcher->map($handlers);
    }

    public function ask(Query $query): mixed
    {
        if (!$this->dispatcher->hasCommandHandler($query)) {
            throw HandlerNotFound::for($query);
        }

        return $this->dispatcher->dispatchNow($query);
    }
}
