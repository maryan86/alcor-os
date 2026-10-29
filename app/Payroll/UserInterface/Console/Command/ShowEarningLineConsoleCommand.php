<?php

declare(strict_types=1);

namespace App\Payroll\UserInterface\Console\Command;

use App\Payroll\Application\Query\GetEarningLine\GetEarningLine;
use App\Payroll\UserInterface\Console\Presenter\EarningLinePresenter;
use App\Shared\Application\Bus\Query\QueryBus;
use Illuminate\Console\Command;

final class ShowEarningLineConsoleCommand extends Command
{
    use ReadsStringInput;

    protected $signature = 'payroll:show
        {line : Earning line id (UUID)}
        {--as-of= : Show the line as it was at this moment (UTC), e.g. --as-of="2026-10-29 12:00"}';

    protected $description = 'Show the current value and the full audit history of an earning line';

    public function handle(QueryBus $queries): int
    {
        $asOf = $this->stringOption('as-of');
        $query = new GetEarningLine($this->stringArgument('line'), $asOf === '' ? null : $asOf);

        EarningLinePresenter::render($queries->ask($query), $this);

        return self::SUCCESS;
    }
}
