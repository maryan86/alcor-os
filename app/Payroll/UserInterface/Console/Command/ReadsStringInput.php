<?php

declare(strict_types=1);

namespace App\Payroll\UserInterface\Console\Command;

trait ReadsStringInput
{
    private function stringArgument(string $name): string
    {
        $value = $this->argument($name);

        if (!is_string($value)) {
            return '';
        }

        return $value;
    }

    private function stringOption(string $name): string
    {
        $value = $this->option($name);

        if (!is_string($value)) {
            return '';
        }

        return $value;
    }
}
