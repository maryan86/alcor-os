<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Payroll\Domain\EarningLine\Exception\EarningLineNotFound;
use App\Payroll\Domain\EarningLine\Exception\InvalidAdjustmentComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ConsoleCommandsTest extends TestCase
{
    use RefreshDatabase;

    private const string LINE_ID = '0b9a5c34-2d0e-4b8f-9a51-6f1d2c3e4a5b';

    #[Test]
    public function demo_runs_the_assignment_scenario(): void
    {
        $this->console('payroll:demo')
            ->expectsOutputToContain('System value (frozen)')
            ->expectsOutputToContain('$1,104.45')
            ->expectsOutputToContain('Adjustment 5 (corrects #4)')
            ->expectsOutputToContain('All 8 steps match')
            ->assertSuccessful();
    }

    #[Test]
    public function line_can_be_calculated_adjusted_and_shown(): void
    {
        $this->console('payroll:calculate', ['line' => self::LINE_ID, 'amount' => '1050.00'])->assertSuccessful();
        $this->console('payroll:adjust', ['line' => self::LINE_ID, '--amount' => '-45.55', '--comment' => 'Declined benefit'])->assertSuccessful();

        $this->console('payroll:show', ['line' => self::LINE_ID])
            ->expectsOutputToContain('-$45.55')
            ->expectsOutputToContain('$1,004.45')
            ->assertSuccessful();
    }

    #[Test]
    public function compensating_adjustment_names_the_one_it_corrects(): void
    {
        $this->console('payroll:calculate', ['line' => self::LINE_ID, 'amount' => '1000.00'])->assertSuccessful();
        $this->console('payroll:adjust', ['line' => self::LINE_ID, '--amount' => '-0.20', '--comment' => 'Rounding'])->assertSuccessful();
        $this->console('payroll:adjust', ['line' => self::LINE_ID, '--amount' => '0.20', '--comment' => 'Fix #1', '--corrects' => '1'])->assertSuccessful();

        $this->console('payroll:show', ['line' => self::LINE_ID])
            ->expectsOutputToContain('Adjustment 2 (corrects #1)')
            ->assertSuccessful();
    }

    #[Test]
    public function line_can_be_shown_as_of_a_moment(): void
    {
        $this->console('payroll:calculate', ['line' => self::LINE_ID, 'amount' => '1000.00'])->assertSuccessful();

        $this->console('payroll:show', ['line' => self::LINE_ID, '--as-of' => 'now'])
            ->expectsOutputToContain('$1,000.00')
            ->assertSuccessful();
    }

    #[Test]
    public function non_numeric_corrects_option_is_rejected(): void
    {
        $this->console('payroll:calculate', ['line' => self::LINE_ID, 'amount' => '1000.00']);

        $this->expectException(InvalidArgumentException::class);

        $this->console('payroll:adjust', ['line' => self::LINE_ID, '--amount' => '1', '--comment' => 'x', '--corrects' => 'four'])->run();
    }

    #[Test]
    public function adjustment_without_comment_is_rejected(): void
    {
        $this->console('payroll:calculate', ['line' => self::LINE_ID, 'amount' => '1050.00']);

        $this->expectException(InvalidAdjustmentComment::class);

        $this->console('payroll:adjust', ['line' => self::LINE_ID, '--amount' => '-45.55'])->run();
    }

    #[Test]
    public function unknown_line_cannot_be_shown(): void
    {
        $this->expectException(EarningLineNotFound::class);

        $this->console('payroll:show', ['line' => self::LINE_ID])->run();
    }
}
