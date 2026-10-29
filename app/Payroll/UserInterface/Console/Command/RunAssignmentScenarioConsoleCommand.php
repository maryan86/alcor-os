<?php

declare(strict_types=1);

namespace App\Payroll\UserInterface\Console\Command;

use App\Payroll\Application\Command\AddManualAdjustment\AddManualAdjustment;
use App\Payroll\Application\Command\RecordSystemCalculation\RecordSystemCalculation;
use App\Payroll\Application\Query\GetEarningLine\GetEarningLine;
use App\Payroll\UserInterface\Console\Presenter\EarningLinePresenter;
use App\Payroll\UserInterface\Console\Presenter\MoneyFormatter;
use App\Shared\Application\Bus\Command\Command as BusCommand;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class RunAssignmentScenarioConsoleCommand extends Command
{
    private const string SPECIALIST = 'payroll-specialist';

    protected $signature = 'payroll:demo';

    protected $description = 'Run the example scenario from the assignment and check every value against the expected one';

    public function handle(CommandBus $commands, QueryBus $queries): int
    {
        $lineId = (string) Str::uuid();
        $steps = $this->steps($lineId);
        $rows = [];
        $mismatches = 0;

        foreach ($steps as $number => [$description, $command, $expected]) {
            $commands->dispatch($command);
            $actual = $queries->ask(new GetEarningLine($lineId))->currentValue;

            if ($actual->toDecimalString() !== $expected) {
                $mismatches++;
            }

            $rows[] = [
                (string) ($number + 1),
                $description,
                $command instanceof AddManualAdjustment ? $command->amount : '-',
                MoneyFormatter::format($actual),
                $actual->toDecimalString() === $expected ? 'ok' : sprintf('expected %s', $expected),
            ];
        }

        $this->table(['Step', 'Event', 'Amount', 'Current value', 'Check'], $rows);
        EarningLinePresenter::render($queries->ask(new GetEarningLine($lineId)), $this);

        if ($mismatches > 0) {
            $this->error(sprintf('%d step(s) do not match the assignment.', $mismatches));

            return self::FAILURE;
        }

        $this->info('All 8 steps match the values expected by the assignment.');

        return self::SUCCESS;
    }

    /** @return list<array{string, BusCommand, string}> */
    private function steps(string $lineId): array
    {
        return [
            [
                'System calculates the line',
                self::calculation($lineId, '1000.00'),
                '1000.00',
            ],
            [
                'Source data changes, system recalculates',
                self::calculation($lineId, '1050.00'),
                '1050.00',
            ],
            [
                'Specialist adds a manual correction',
                self::adjustment($lineId, '-45.55', 'Employee declined dental benefit; reversing deduction'),
                '1004.45',
            ],
            [
                'Source data changes again, system attempts to recalculate',
                self::calculation($lineId, '1100.00'),
                '1004.45',
            ],
            [
                'Specialist adds a second correction',
                self::adjustment($lineId, '+100.10', 'Late correction: missed approved overtime bonus'),
                '1104.55',
            ],
            [
                'Specialist adds a third correction',
                self::adjustment($lineId, '-0.10', 'Minor rounding adjustment'),
                '1104.45',
            ],
            [
                'Specialist adds a fourth correction',
                self::adjustment($lineId, '-0.20', 'Second minor rounding adjustment'),
                '1104.25',
            ],
            [
                'Specialist adds a compensating correction',
                self::adjustment($lineId, '+0.20', 'Correcting mistake in adjustment #4', corrects: 4),
                '1104.45',
            ],
        ];
    }

    private static function calculation(string $lineId, string $amount): RecordSystemCalculation
    {
        return new RecordSystemCalculation($lineId, $amount, 'USD');
    }

    private static function adjustment(
        string $lineId,
        string $amount,
        string $comment,
        ?int $corrects = null,
    ): AddManualAdjustment {
        return new AddManualAdjustment($lineId, $amount, $comment, self::SPECIALIST, $corrects);
    }
}
