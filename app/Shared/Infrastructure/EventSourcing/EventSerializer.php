<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\EventSourcing;

use App\Shared\Domain\EventSourcing\DomainEvent;
use JsonException;
use UnexpectedValueException;

final class EventSerializer
{
    /** @var array<string, class-string<DomainEvent>> */
    private array $classByType = [];

    /** @var array<class-string<DomainEvent>, string> */
    private array $typeByClass = [];

    /** @param array<string, class-string<DomainEvent>> $eventTypes */
    public function __construct(array $eventTypes = [])
    {
        $this->register($eventTypes);
    }

    /** @param array<string, class-string<DomainEvent>> $eventTypes */
    public function register(array $eventTypes): void
    {
        foreach ($eventTypes as $type => $class) {
            $this->classByType[$type] = $class;
            $this->typeByClass[$class] = $type;
        }
    }

    public function typeOf(DomainEvent $event): string
    {
        if (!isset($this->typeByClass[$event::class])) {
            throw new UnexpectedValueException(sprintf('Event %s has no registered type name.', $event::class));
        }

        return $this->typeByClass[$event::class];
    }

    /** @throws JsonException */
    public function serializePayload(DomainEvent $event): string
    {
        return json_encode($event->toPayload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /** @throws JsonException */
    public function deserialize(string $type, string $payload): DomainEvent
    {
        if (!isset($this->classByType[$type])) {
            throw new UnexpectedValueException(sprintf('Unknown event type "%s".', $type));
        }

        $class = $this->classByType[$type];
        $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new UnexpectedValueException(sprintf('Payload of event type "%s" is not an object.', $type));
        }

        /** @var array<string, mixed> $data */
        return $class::fromPayload($data);
    }
}
