<?php

declare(strict_types=1);

namespace App\Payroll\UserInterface\Console\Command;

use App\Payroll\Application\Command\AddManualAdjustment\AddManualAdjustment;
use App\Shared\Application\Bus\Command\CommandBus;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class AddManualAdjustmentConsoleCommand extends Command
{
    use ReadsStringInput;

    // Amount is an option, not an argument: a leading "-" would otherwise be parsed as a CLI flag.
    protected $signature = 'payroll:adjust
        {line : Earning line id (UUID)}
        {--amount= : Signed correction, e.g. --amount=-45.55 or --amount=100.10}
        {--comment= : Mandatory reason of the correction}
        {--by=payroll-specialist : Id of the specialist making the correction}
        {--corrects= : Number of the earlier adjustment this one compensates, e.g. --corrects=4}';

    protected $description = 'Add a manual correction to an earning line (append-only)';

    public function handle(CommandBus $commands): int
    {
        $commands->dispatch(new AddManualAdjustment(
            $this->stringArgument('line'),
            $this->stringOption('amount'),
            $this->stringOption('comment'),
            $this->stringOption('by'),
            $this->correctsOption(),
        ));

        $this->info('Manual adjustment added.');

        return self::SUCCESS;
    }

    private function correctsOption(): ?int
    {
        $corrects = $this->stringOption('corrects');

        if ($corrects === '') {
            return null;
        }

        $number = filter_var($corrects, FILTER_VALIDATE_INT);

        if ($number === false) {
            throw new InvalidArgumentException(sprintf('--corrects must be an adjustment number, "%s" given.', $corrects));
        }

        return $number;
    }
}
