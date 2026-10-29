<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Application\Bus\Command\CommandBus;
use Illuminate\Database\ConnectionInterface;

final readonly class TransactionalCommandBus implements CommandBus
{
    public function __construct(
        private CommandBus $inner,
        private ConnectionInterface $connection,
    ) {}

    public function dispatch(Command $command): void
    {
        $this->connection->transaction(fn() => $this->inner->dispatch($command));
    }
}
