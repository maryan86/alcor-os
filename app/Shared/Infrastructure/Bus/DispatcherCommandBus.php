<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\HandlerNotFound;
use Illuminate\Bus\Dispatcher;

final readonly class DispatcherCommandBus implements CommandBus
{
    public function __construct(private Dispatcher $dispatcher) {}

    /** @param array<class-string<Command>, class-string> $handlers */
    public function map(array $handlers): void
    {
        $this->dispatcher->map($handlers);
    }

    public function dispatch(Command $command): void
    {
        if (!$this->dispatcher->hasCommandHandler($command)) {
            throw HandlerNotFound::for($command);
        }

        $this->dispatcher->dispatchNow($command);
    }
}
