<?php

declare(strict_types=1);

namespace App\Payroll\UserInterface\Console\Command;

use App\Payroll\Infrastructure\EarningLineProjector;
use App\Shared\Infrastructure\EventSourcing\Store\EventStore;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;

final class RebuildProjectionsConsoleCommand extends Command
{
    protected $signature = 'payroll:projections:rebuild';

    protected $description = 'Drop the earning line read model and rebuild it by replaying the event store';

    public function handle(
        EventStore $eventStore,
        EarningLineProjector $projector,
        ConnectionInterface $connection,
    ): int {
        $replayed = 0;

        $connection->transaction(static function () use ($eventStore, $projector, &$replayed): void {
            $projector->reset();

            $events = $eventStore->loadAll();

            foreach ($events as $event) {
                $projector->project($event);
                $replayed++;
            }
        });

        $this->info(sprintf('Read model rebuilt from %d event(s).', $replayed));

        return self::SUCCESS;
    }
}
