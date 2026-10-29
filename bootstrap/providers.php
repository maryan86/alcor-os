<?php

declare(strict_types=1);

use App\Payroll\PayrollServiceProvider;
use App\Shared\SharedServiceProvider;

return [
    SharedServiceProvider::class,
    PayrollServiceProvider::class,
];
