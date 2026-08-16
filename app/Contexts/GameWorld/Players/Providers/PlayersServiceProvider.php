<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Providers;

use App\Contexts\GameWorld\Players\Services\PlayerContext;
use Illuminate\Support\ServiceProvider;

final class PlayersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(PlayerContext::class);
    }
}
