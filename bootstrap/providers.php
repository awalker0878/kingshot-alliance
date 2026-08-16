<?php

declare(strict_types=1);

use App\Contexts\Platform\Providers\AppServiceProvider;
use App\Shared\Providers\SharedServiceProvider;

return [
    SharedServiceProvider::class,
    AppServiceProvider::class,
];
