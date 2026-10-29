<?php

declare(strict_types=1);

namespace App\Payroll\Application\Query\GetEarningLine;

use App\Payroll\Application\ReadModel\EarningLineReadModel;
use App\Payroll\Application\ReadModel\View\EarningLineView;
use App\Payroll\Domain\EarningLine\Exception\EarningLineNotFound;
use App\Payroll\Domain\EarningLine\ValueObject\EarningLineId;
use DateTimeImmutable;
use DateTimeZone;

final readonly class GetEarningLineHandler
{
    public function __construct(private EarningLineReadModel $readModel) {}

    public function __invoke(GetEarningLine $query): EarningLineView
    {
        $id = EarningLineId::fromString($query->lineId);

        $view = $this->readModel->find($id->toString(), $this->asOf($query));

        if ($view === null) {
            throw EarningLineNotFound::withId($id);
        }

        return $view;
    }

    private function asOf(GetEarningLine $query): ?DateTimeImmutable
    {
        if ($query->asOf === null) {
            return null;
        }

        return new DateTimeImmutable($query->asOf, new DateTimeZone('UTC'));
    }
}
