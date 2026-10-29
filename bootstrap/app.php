<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;

// Console-only application: no HTTP routes, the CLI (Artisan) is the delivery mechanism.
return Application::configure(basePath: dirname(__DIR__))
    ->withExceptions()
    ->create();
