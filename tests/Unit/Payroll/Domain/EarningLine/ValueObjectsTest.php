<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll\Domain\EarningLine;

use App\Payroll\Domain\EarningLine\Exception\InvalidAdjustmentComment;
use App\Payroll\Domain\EarningLine\ValueObject\AdjustmentComment;
use App\Payroll\Domain\EarningLine\ValueObject\EarningLineId;
use App\Payroll\Domain\EarningLine\ValueObject\SpecialistId;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ValueObjectsTest extends TestCase
{
    #[Test]
    public function comment_is_mandatory(): void
    {
        $this->expectException(InvalidAdjustmentComment::class);

        AdjustmentComment::fromString("  \n ");
    }

    #[Test]
    public function comment_is_trimmed_and_length_limited(): void
    {
        self::assertSame('Late correction', AdjustmentComment::fromString('  Late correction ')->toString());

        $this->expectException(InvalidAdjustmentComment::class);
        AdjustmentComment::fromString(str_repeat('ж', AdjustmentComment::MAX_LENGTH + 1));
    }

    #[Test]
    public function line_id_must_be_a_uuid_and_is_normalized(): void
    {
        self::assertSame(
            '0b9a5c34-2d0e-4b8f-9a51-6f1d2c3e4a5b',
            EarningLineId::fromString('0B9A5C34-2D0E-4B8F-9A51-6F1D2C3E4A5B')->toString(),
        );

        $this->expectException(InvalidArgumentException::class);
        EarningLineId::fromString('line-1');
    }

    #[Test]
    public function specialist_id_must_not_be_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SpecialistId::fromString(' ');
    }
}
