<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

use LogicException;

final class HandlerNotFound extends LogicException
{
    public static function for(object $message): self
    {
        return new self(sprintf('No handler is registered for %s.', $message::class));
    }
}
