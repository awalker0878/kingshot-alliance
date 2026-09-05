<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Providers;

use App\Contexts\GameWorld\GiftCodes\Adapters\CenturyGamesKingshotNewsRssGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\DiscordChannelGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\FacebookPageGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\InstagramMediaGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\JsonFeedGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\OfficialXGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\RedditSubredditGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\RssAtomGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\StructuredHtmlGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\YouTubeChannelGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Contracts\GiftCodeRedemptionProvider;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceAdapterRegistry;
use App\Contexts\GameWorld\GiftCodes\Services\OfficialGiftCodeHandoff;
use Illuminate\Support\ServiceProvider;

final class GiftCodesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(JsonFeedGiftCodeSourceAdapter::class);
        $this->app->singleton(RssAtomGiftCodeSourceAdapter::class);
        $this->app->singleton(StructuredHtmlGiftCodeSourceAdapter::class);
        $this->app->singleton(OfficialXGiftCodeSourceAdapter::class);
        $this->app->singleton(CenturyGamesKingshotNewsRssGiftCodeSourceAdapter::class);
        $this->app->singleton(DiscordChannelGiftCodeSourceAdapter::class);
        $this->app->singleton(YouTubeChannelGiftCodeSourceAdapter::class);
        $this->app->singleton(RedditSubredditGiftCodeSourceAdapter::class);
        $this->app->singleton(FacebookPageGiftCodeSourceAdapter::class);
        $this->app->singleton(InstagramMediaGiftCodeSourceAdapter::class);
        $this->app->tag([
            JsonFeedGiftCodeSourceAdapter::class,
            RssAtomGiftCodeSourceAdapter::class,
            StructuredHtmlGiftCodeSourceAdapter::class,
            OfficialXGiftCodeSourceAdapter::class,
            CenturyGamesKingshotNewsRssGiftCodeSourceAdapter::class,
            DiscordChannelGiftCodeSourceAdapter::class,
            YouTubeChannelGiftCodeSourceAdapter::class,
            RedditSubredditGiftCodeSourceAdapter::class,
            FacebookPageGiftCodeSourceAdapter::class,
            InstagramMediaGiftCodeSourceAdapter::class,
        ], 'gift-code-source-adapter');
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
