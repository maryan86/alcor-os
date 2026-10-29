<?php

declare(strict_types=1);

namespace App\Payroll\UserInterface\Console\Command;

use App\Payroll\Application\Command\RecordSystemCalculation\RecordSystemCalculation;
use App\Shared\Application\Bus\Command\CommandBus;
use Illuminate\Console\Command;

final class RecordSystemCalculationConsoleCommand extends Command
{
    use ReadsStringInput;

    protected $signature = 'payroll:calculate
        {line : Earning line id (UUID)}
        {amount : Calculated amount, e.g. 1050.00}
        {--currency=USD : ISO 4217 currency code}';

    protected $description = 'Record an automatic (re)calculation of an earning line by the payroll engine';

    public function handle(CommandBus $commands): int
    {
        $commands->dispatch(new RecordSystemCalculation(
            $this->stringArgument('line'),
            $this->stringArgument('amount'),
            $this->stringOption('currency'),
        ));

        $this->info('System calculation recorded.');

        return self::SUCCESS;
    }
}
