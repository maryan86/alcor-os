<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\EventSourcing\Store;

use App\Payroll\Domain\EarningLine\Event\SystemValueCalculated;
use App\Payroll\Domain\EarningLine\ValueObject\EarningLineId;
use App\Shared\Domain\EventSourcing\ConcurrencyConflict;
use App\Shared\Domain\Money\Currency;
use App\Shared\Domain\Money\Money;
use App\Shared\Infrastructure\EventSourcing\Store\InMemoryEventStore;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InMemoryEventStoreTest extends TestCase
{
    #[Test]
    public function stale_writer_is_rejected(): void
    {
        $store = new InMemoryEventStore();
        $event = new SystemValueCalculated(
            EarningLineId::fromString('0b9a5c34-2d0e-4b8f-9a51-6f1d2c3e4a5b'),
            Money::of('1', Currency::USD),
            new DateTimeImmutable(),
        );
        $store->append('stream', 0, [$event]);

        $this->expectException(ConcurrencyConflict::class);

        $store->append('stream', 0, [$event]);
    }
}
