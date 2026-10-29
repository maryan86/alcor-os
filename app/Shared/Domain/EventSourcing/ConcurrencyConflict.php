<?php

declare(strict_types=1);

namespace App\Shared\Domain\EventSourcing;

use RuntimeException;

final class ConcurrencyConflict extends RuntimeException
{
    public static function onStream(string $streamId, int $expectedVersion, int $actualVersion): self
    {
        return new self(sprintf(
            'Stream "%s" was expected at version %d but is at version %d.',
            $streamId,
            $expectedVersion,
            $actualVersion,
        ));
    }
}
