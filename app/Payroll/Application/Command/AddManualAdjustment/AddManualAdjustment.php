<?php

declare(strict_types=1);

namespace App\Payroll\Application\Command\AddManualAdjustment;

use App\Shared\Application\Bus\Command\Command;

final readonly class AddManualAdjustment implements Command
{
    public function __construct(
        public string $lineId,
        public string $amount,
        public string $comment,
        public string $specialistId,
        public ?int $corrects = null,
    ) {}
}
