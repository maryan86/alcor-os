<?php

declare(strict_types=1);

namespace App\Payroll\Domain\EarningLine\ValueObject;

use InvalidArgumentException;

final readonly class EarningLineId
{
    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));

        if (preg_match(self::UUID_PATTERN, $normalized) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid earning line id (UUID expected).', $value));
        }

        return new self($normalized);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function toString(): string
    {
        return $this->value;
    }
}
