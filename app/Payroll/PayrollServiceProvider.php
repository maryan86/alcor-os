<?php

declare(strict_types=1);

namespace App\Payroll;

use App\Payroll\Application\Command\AddManualAdjustment\AddManualAdjustment;
use App\Payroll\Application\Command\AddManualAdjustment\AddManualAdjustmentHandler;
use App\Payroll\Application\Command\RecordSystemCalculation\RecordSystemCalculation;
use App\Payroll\Application\Command\RecordSystemCalculation\RecordSystemCalculationHandler;
use App\Payroll\Application\Query\GetEarningLine\GetEarningLine;
use App\Payroll\Application\Query\GetEarningLine\GetEarningLineHandler;
use App\Payroll\Application\ReadModel\EarningLineReadModel;
use App\Payroll\Domain\EarningLine\EarningLineRepository;
use App\Payroll\Domain\EarningLine\Event\ManualAdjustmentAdded;
use App\Payroll\Domain\EarningLine\Event\SystemRecalculationIgnored;
use App\Payroll\Domain\EarningLine\Event\SystemValueCalculated;
use App\Payroll\Domain\EarningLine\Event\SystemValueRecalculated;
use App\Payroll\Infrastructure\EarningLineProjector;
use App\Payroll\Infrastructure\EventSourcedEarningLineRepository;
use App\Payroll\Infrastructure\ReadModel\EloquentEarningLineReadModel;
use App\Payroll\UserInterface\Console\Command\AddManualAdjustmentConsoleCommand;
use App\Payroll\UserInterface\Console\Command\RebuildProjectionsConsoleCommand;
use App\Payroll\UserInterface\Console\Command\RecordSystemCalculationConsoleCommand;
use App\Payroll\UserInterface\Console\Command\RunAssignmentScenarioConsoleCommand;
use App\Payroll\UserInterface\Console\Command\ShowEarningLineConsoleCommand;
use App\Shared\Infrastructure\Bus\DispatcherCommandBus;
use App\Shared\Infrastructure\Bus\DispatcherQueryBus;
use App\Shared\Infrastructure\EventSourcing\EventSerializer;
use App\Shared\Infrastructure\EventSourcing\Projector;
use Illuminate\Support\ServiceProvider;

final class PayrollServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        EarningLineRepository::class => EventSourcedEarningLineRepository::class,
        EarningLineReadModel::class => EloquentEarningLineReadModel::class,
        Projector::class => EarningLineProjector::class,
    ];

    public function register(): void
    {
        $this->app->afterResolving(EventSerializer::class, static function (EventSerializer $serializer): void {
            // Stable, versionable names persisted in the event store. Never rename an existing one.
            $serializer->register([
                'payroll.earning_line.system_value_calculated.v1' => SystemValueCalculated::class,
                'payroll.earning_line.system_value_recalculated.v1' => SystemValueRecalculated::class,
                'payroll.earning_line.system_recalculation_ignored.v1' => SystemRecalculationIgnored::class,
                'payroll.earning_line.manual_adjustment_added.v1' => ManualAdjustmentAdded::class,
            ]);
        });

        $this->app->afterResolving(DispatcherCommandBus::class, static function (DispatcherCommandBus $bus): void {
            $bus->map([
                RecordSystemCalculation::class => RecordSystemCalculationHandler::class,
                AddManualAdjustment::class => AddManualAdjustmentHandler::class,
            ]);
        });

        $this->app->afterResolving(DispatcherQueryBus::class, static function (DispatcherQueryBus $bus): void {
            $bus->map([
                GetEarningLine::class => GetEarningLineHandler::class,
            ]);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RecordSystemCalculationConsoleCommand::class,
                AddManualAdjustmentConsoleCommand::class,
                ShowEarningLineConsoleCommand::class,
                RebuildProjectionsConsoleCommand::class,
                RunAssignmentScenarioConsoleCommand::class,
            ]);
        }
    }
}
