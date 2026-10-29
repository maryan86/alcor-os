<?php

declare(strict_types=1);

namespace App\Payroll\Domain\EarningLine\ValueObject;

use InvalidArgumentException;

final readonly class SpecialistId
{
    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Specialist id must not be empty.');
        }

        return new self($trimmed);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
