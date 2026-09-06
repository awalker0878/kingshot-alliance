<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Adapters\YouTubeChannelGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceSubscription;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

final class YouTubeWebSubSubscriptionManager
{
    public function subscribe(GiftCodeSourceRegistry $source): GiftCodeSourceSubscription
    {
        return $this->change($source, 'subscribe');
    }

    public function unsubscribe(GiftCodeSourceRegistry $source): GiftCodeSourceSubscription
    {
        return $this->change($source, 'unsubscribe');
    }

    private function change(GiftCodeSourceRegistry $source, string $mode): GiftCodeSourceSubscription
    {
        if ($source->adapter_key !== YouTubeChannelGiftCodeSourceAdapter::KEY) {
            throw new UnexpectedValueException('YouTube WebSub subscriptions require the YouTube source adapter.');
        }
        $policy = $source->provenance_policy ?? [];
        $channelId = is_string($policy['youtube_channel_id'] ?? null) ? trim($policy['youtube_channel_id']) : '';
        $secret = trim((string) config('game_world.gift_codes.youtube_websub_secret', ''));
        if ($channelId === '' || strlen($secret) < 32) {
            throw new UnexpectedValueException('YouTube WebSub source identity and secret must be configured before subscription.');
        }

        $topic = 'https://www.youtube.com/feeds/videos.xml?channel_id='.$channelId;
        $callback = route('api.gift-code-sources.youtube-websub.receive', ['source' => (string) $source->id], true);
        $response = Http::asForm()
            ->timeout(max(1, min(30, (int) config('game_world.gift_codes.ingestion_timeout_seconds', 10))))
            ->withOptions(['allow_redirects' => false])
            ->post('https://pubsubhubbub.appspot.com/subscribe', [
                'hub.callback' => $callback,
                'hub.mode' => $mode,
                'hub.topic' => $topic,
                'hub.verify' => 'async',
                'hub.secret' => $secret,
            ]);
        if (! in_array($response->status(), [202, 204], true)) {
            throw new UnexpectedValueException('YouTube WebSub hub did not accept the subscription request.');
        }

        return GiftCodeSourceSubscription::query()->updateOrCreate(
            [
                'gift_code_source_id' => (string) $source->id,
                'provider' => 'youtube',
                'transport' => 'websub',
            ],
            [
                'topic_or_rule' => $topic,
                'configured_identity' => ['channel_id' => $channelId],
                'status' => $mode === 'subscribe' ? 'pending' : 'disabled',
                'secret_version' => hash('sha256', $secret),
                'last_error_code' => null,
            ],
        );
    }
}
