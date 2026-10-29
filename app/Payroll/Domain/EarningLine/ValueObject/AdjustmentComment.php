<?php

declare(strict_types=1);

namespace App\Payroll\Domain\EarningLine\ValueObject;

use App\Payroll\Domain\EarningLine\Exception\InvalidAdjustmentComment;

final readonly class AdjustmentComment
{
    public const int MAX_LENGTH = 500;

    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw InvalidAdjustmentComment::empty();
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw InvalidAdjustmentComment::tooLong(self::MAX_LENGTH);
        }

        return new self($trimmed);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
