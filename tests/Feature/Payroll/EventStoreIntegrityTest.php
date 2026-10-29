<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Payroll\Application\Command\AddManualAdjustment\AddManualAdjustment;
use App\Payroll\Application\Command\RecordSystemCalculation\RecordSystemCalculation;
use App\Payroll\Application\Query\GetEarningLine\GetEarningLine;
use App\Payroll\Domain\EarningLine\EarningLineRepository;
use App\Payroll\Domain\EarningLine\Exception\ZeroAdjustmentNotAllowed;
use App\Payroll\Domain\EarningLine\ValueObject\AdjustmentComment;
use App\Payroll\Domain\EarningLine\ValueObject\EarningLineId;
use App\Payroll\Domain\EarningLine\ValueObject\SpecialistId;
use App\Payroll\Infrastructure\ReadModel\Record\AuditEntryRecord;
use App\Payroll\Infrastructure\ReadModel\Record\EarningLineRecord;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Domain\EventSourcing\ConcurrencyConflict;
use App\Shared\Domain\Money\Currency;
use App\Shared\Domain\Money\Money;
use App\Shared\Infrastructure\EventSourcing\Store\DatabaseEventStore;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class EventStoreIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private const string LINE_ID = '0b9a5c34-2d0e-4b8f-9a51-6f1d2c3e4a5b';

    #[Test]
    public function concurrent_writers_cannot_both_append_to_the_same_version(): void
    {
        $this->bus()->dispatch(new RecordSystemCalculation(self::LINE_ID, '1000.00', 'USD'));
        $repository = $this->app->make(EarningLineRepository::class);
        $id = EarningLineId::fromString(self::LINE_ID);

        // Two specialists load the same version of the line...
        $first = $repository->get($id);
        $second = $repository->get($id);

        $first->addManualAdjustment(Money::of('10.00', Currency::USD), AdjustmentComment::fromString('First'), SpecialistId::fromString('a'), new DateTimeImmutable());
        $second->addManualAdjustment(Money::of('20.00', Currency::USD), AdjustmentComment::fromString('Second'), SpecialistId::fromString('b'), new DateTimeImmutable());

        // ...the first save wins, the second one must not silently overwrite or interleave.
        $repository->save($first);
        $this->expectException(ConcurrencyConflict::class);
        $repository->save($second);
    }

    #[Test]
    public function unique_index_guards_versions_even_if_the_version_check_is_bypassed(): void
    {
        $this->bus()->dispatch(new RecordSystemCalculation(self::LINE_ID, '1000.00', 'USD'));

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table(DatabaseEventStore::TABLE)->insert([
            'stream_id' => 'earning_line-' . self::LINE_ID,
            'version' => 1,
            'event_type' => 'forged',
            'payload' => '{}',
            'occurred_at' => '2026-01-01 00:00:00',
            'recorded_at' => '2026-01-01 00:00:00',
        ]);
    }

    /** @return iterable<string, array{literal-string}> */
    public static function historyRewrites(): iterable
    {
        yield 'edit an event' => ["UPDATE event_store SET payload = '{}'"];
        yield 'delete an event' => ['DELETE FROM event_store'];
        yield 'truncate the store' => ['TRUNCATE event_store'];
    }

    /** @param literal-string $statement */
    #[Test]
    #[DataProvider('historyRewrites')]
    public function database_refuses_to_rewrite_history(string $statement): void
    {
        $this->bus()->dispatch(new RecordSystemCalculation(self::LINE_ID, '1000.00', 'USD'));

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('event_store is append-only');

        DB::unprepared($statement);
    }

    #[Test]
    public function failed_command_leaves_neither_events_nor_projection_changes(): void
    {
        $this->bus()->dispatch(new RecordSystemCalculation(self::LINE_ID, '1000.00', 'USD'));

        try {
            $this->bus()->dispatch(new AddManualAdjustment(self::LINE_ID, '0', 'Nothing', 'specialist-1'));
        } catch (ZeroAdjustmentNotAllowed) {
        }

        self::assertSame(1, DB::table(DatabaseEventStore::TABLE)->count());
        self::assertSame(1, AuditEntryRecord::query()->count());
    }

    #[Test]
    public function events_are_stored_append_only_with_stable_type_names_and_sequential_versions(): void
    {
        $this->bus()->dispatch(new RecordSystemCalculation(self::LINE_ID, '1000.00', 'USD'));
        $this->bus()->dispatch(new AddManualAdjustment(self::LINE_ID, '-1.00', 'Fix', 'specialist-1'));
        $this->bus()->dispatch(new RecordSystemCalculation(self::LINE_ID, '1200.00', 'USD'));

        self::assertEquals(
            [
                (object) ['version' => 1, 'event_type' => 'payroll.earning_line.system_value_calculated.v1'],
                (object) ['version' => 2, 'event_type' => 'payroll.earning_line.manual_adjustment_added.v1'],
                (object) ['version' => 3, 'event_type' => 'payroll.earning_line.system_recalculation_ignored.v1'],
            ],
            DB::table(DatabaseEventStore::TABLE)->orderBy('version')->get(['version', 'event_type'])->all(),
        );
    }

    #[Test]
    public function read_model_can_be_rebuilt_from_the_event_store(): void
    {
        $this->bus()->dispatch(new RecordSystemCalculation(self::LINE_ID, '1050.00', 'USD'));
        $this->bus()->dispatch(new AddManualAdjustment(self::LINE_ID, '-45.55', 'Declined benefit', 'specialist-1'));
        $this->bus()->dispatch(new RecordSystemCalculation(self::LINE_ID, '1100.00', 'USD'));
        $before = $this->app->make(QueryBus::class)->ask(new GetEarningLine(self::LINE_ID));

        AuditEntryRecord::query()->delete();
        EarningLineRecord::query()->delete();
        $this->console('payroll:projections:rebuild')->assertSuccessful();

        self::assertEquals($before, $this->app->make(QueryBus::class)->ask(new GetEarningLine(self::LINE_ID)));
    }

    private function bus(): CommandBus
    {
        return $this->app->make(CommandBus::class);
    }
}
