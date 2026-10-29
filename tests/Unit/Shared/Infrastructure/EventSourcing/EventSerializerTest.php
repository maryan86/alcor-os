<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\EventSourcing;

use App\Payroll\Domain\EarningLine\Event\ManualAdjustmentAdded;
use App\Payroll\Domain\EarningLine\Event\SystemRecalculationIgnored;
use App\Payroll\Domain\EarningLine\Event\SystemValueCalculated;
use App\Payroll\Domain\EarningLine\Event\SystemValueRecalculated;
use App\Shared\Domain\EventSourcing\DomainEvent;
use App\Shared\Infrastructure\EventSourcing\EventSerializer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\EarningLineEventSamples;
use UnexpectedValueException;

final class EventSerializerTest extends TestCase
{
    /** @return iterable<string, array{DomainEvent}> */
    public static function events(): iterable
    {
        $samples = EarningLineEventSamples::all();

        foreach ($samples as $class => $event) {
            yield $class => [$event];
        }
    }

    #[Test]
    #[DataProvider('events')]
    public function every_event_survives_a_round_trip_unchanged(DomainEvent $event): void
    {
        $serializer = self::serializer();

        $restored = $serializer->deserialize($serializer->typeOf($event), $serializer->serializePayload($event));

        self::assertEquals($event, $restored);
    }

    #[Test]
    public function unknown_type_is_rejected(): void
    {
        $this->expectException(UnexpectedValueException::class);

        self::serializer()->deserialize('unknown.v1', '{}');
    }

    #[Test]
    public function adjustment_stored_without_a_corrects_link_is_still_readable(): void
    {
        $payload = '{"line_id": "0b9a5c34-2d0e-4b8f-9a51-6f1d2c3e4a5b", "adjustment_number": 1, "amount_minor": -4555,'
            . ' "currency": "USD", "comment": "Declined benefit", "adjusted_by": "specialist-1",'
            . ' "occurred_at": "2026-01-15T09:00:00.000000+00:00"}';

        $event = self::serializer()->deserialize('adjusted', $payload);

        self::assertInstanceOf(ManualAdjustmentAdded::class, $event);
        self::assertNull($event->correctsAdjustmentNumber);
    }

    #[Test]
    public function corrupted_payload_is_rejected(): void
    {
        $this->expectException(UnexpectedValueException::class);

        self::serializer()->deserialize('calculated', '{"line_id": "0b9a5c34-2d0e-4b8f-9a51-6f1d2c3e4a5b", "amount_minor": "100"}');
    }

    private static function serializer(): EventSerializer
    {
        return new EventSerializer([
            'calculated' => SystemValueCalculated::class,
            'recalculated' => SystemValueRecalculated::class,
            'ignored' => SystemRecalculationIgnored::class,
            'adjusted' => ManualAdjustmentAdded::class,
        ]);
    }
}
