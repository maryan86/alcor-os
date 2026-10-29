<?php

declare(strict_types=1);

namespace App\Shared;

use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Infrastructure\Bus\DispatcherCommandBus;
use App\Shared\Infrastructure\Bus\DispatcherQueryBus;
use App\Shared\Infrastructure\Bus\TransactionalCommandBus;
use App\Shared\Infrastructure\EventSourcing\EventSerializer;
use App\Shared\Infrastructure\EventSourcing\Store\DatabaseEventStore;
use App\Shared\Infrastructure\EventSourcing\Store\EventStore;
use App\Shared\Infrastructure\SystemClock;
use Illuminate\Bus\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\ServiceProvider;
use Psr\Clock\ClockInterface;

final class SharedServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        ClockInterface::class => SystemClock::class,
        EventSerializer::class => EventSerializer::class,
        EventStore::class => DatabaseEventStore::class,
    ];

    public function register(): void
    {
        $this->app->singleton(
            ConnectionInterface::class,
            static fn(Application $app): ConnectionInterface => $app->make('db')->connection(),
        );

        // Separate dispatcher instances: commands and queries can never be routed to each other's handlers.
        $this->app->singleton(
            DispatcherCommandBus::class,
            static fn(Application $app): DispatcherCommandBus => new DispatcherCommandBus(new Dispatcher($app)),
        );
        $this->app->singleton(
            DispatcherQueryBus::class,
            static fn(Application $app): DispatcherQueryBus => new DispatcherQueryBus(new Dispatcher($app)),
        );

        $this->app->singleton(
            CommandBus::class,
            static fn(Application $app): CommandBus => new TransactionalCommandBus(
                $app->make(DispatcherCommandBus::class),
                $app->make(ConnectionInterface::class),
            ),
        );
        $this->app->singleton(QueryBus::class, DispatcherQueryBus::class);
    }
}
