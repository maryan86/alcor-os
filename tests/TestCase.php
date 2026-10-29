<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\PendingCommand;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    /** @param array<string, string> $parameters */
    protected function console(string $command, array $parameters = []): PendingCommand
    {
        $pending = $this->artisan($command, $parameters);

        if (!$pending instanceof PendingCommand) {
            throw new LogicException('Console output is not mocked.');
        }

        return $pending;
    }
}
