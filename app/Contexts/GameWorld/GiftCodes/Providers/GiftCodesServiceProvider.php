<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Providers;

use App\Contexts\GameWorld\GiftCodes\Contracts\GiftCodeRedemptionProvider;
use App\Contexts\GameWorld\GiftCodes\Services\OfficialGiftCodeHandoff;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceAdapterRegistry;
use Illuminate\Support\ServiceProvider;

final class GiftCodesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            GiftCodeSourceAdapterRegistry::class,
            fn (): GiftCodeSourceAdapterRegistry => new GiftCodeSourceAdapterRegistry(
                $this->app->tagged('gift-code-source-adapter'),
            ),
        );
        $this->app->bind(
            GiftCodeRedemptionProvider::class,
            static fn (): OfficialGiftCodeHandoff => new OfficialGiftCodeHandoff(
                (string) config('game_world.gift_code_redemption_url'),
            ),
        );
    }
}
