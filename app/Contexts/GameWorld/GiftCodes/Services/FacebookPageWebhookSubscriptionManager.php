<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Adapters\FacebookPageGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceSubscription;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

final class FacebookPageWebhookSubscriptionManager
{
    public function subscribe(GiftCodeSourceRegistry $source): GiftCodeSourceSubscription
    {
        if ($source->adapter_key !== FacebookPageGiftCodeSourceAdapter::KEY) {
            throw new UnexpectedValueException('Facebook webhook subscriptions require the Facebook Page source adapter.');
        }

        $policy = $source->provenance_policy ?? [];
        $pageId = is_string($policy['facebook_page_id'] ?? null) ? trim($policy['facebook_page_id']) : '';
        $token = trim((string) config('game_world.gift_codes.facebook_access_token', ''));
        $version = trim((string) config('game_world.gift_codes.meta_graph_api_version', 'v26.0'));
        $verifyToken = trim((string) config('game_world.gift_codes.meta_webhook_verify_token', ''));
        $appSecret = trim((string) config('game_world.gift_codes.meta_app_secret', ''));
        if ($pageId === '' || $token === '' || strlen($verifyToken) < 24 || strlen($appSecret) < 32) {
            throw new UnexpectedValueException('Facebook Page identity, access token, webhook verify token, and app secret must be configured before subscription.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(max(1, min(30, (int) config('game_world.gift_codes.ingestion_timeout_seconds', 10))))
            ->withOptions(['allow_redirects' => false])
            ->post(sprintf('https://graph.facebook.com/%s/%s/subscribed_apps', $version, rawurlencode($pageId)), [
                'subscribed_fields' => 'feed',
            ]);
        if (! $response->successful()) {
            throw new UnexpectedValueException(sprintf('Meta did not accept the Facebook Page webhook subscription (HTTP %d).', $response->status()));
        }
        $payload = $response->json();
        if (! is_array($payload) || ($payload['success'] ?? null) !== true) {
            throw new UnexpectedValueException('Meta did not confirm the Facebook Page webhook subscription.');
        }

        return GiftCodeSourceSubscription::query()->updateOrCreate(
            [
                'gift_code_source_id' => (string) $source->id,
                'provider' => 'facebook',
                'transport' => 'webhook',
            ],
            [
                'topic_or_rule' => 'page:feed',
                'configured_identity' => ['page_id' => $pageId, 'field' => 'feed'],
                'status' => 'pending',
                'secret_version' => hash('sha256', $appSecret.'|'.$verifyToken),
                'last_error_code' => null,
            ],
        );
    }

    public function unsubscribe(GiftCodeSourceRegistry $source): GiftCodeSourceSubscription
    {
        if ($source->adapter_key !== FacebookPageGiftCodeSourceAdapter::KEY) {
            throw new UnexpectedValueException('Facebook webhook subscriptions require the Facebook Page source adapter.');
        }
        $policy = $source->provenance_policy ?? [];
        $pageId = is_string($policy['facebook_page_id'] ?? null) ? trim($policy['facebook_page_id']) : '';
        $token = trim((string) config('game_world.gift_codes.facebook_access_token', ''));
        $version = trim((string) config('game_world.gift_codes.meta_graph_api_version', 'v26.0'));
        if ($pageId === '' || $token === '') {
            throw new UnexpectedValueException('Facebook Page identity and access token are required to remove the subscription.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(max(1, min(30, (int) config('game_world.gift_codes.ingestion_timeout_seconds', 10))))
            ->withOptions(['allow_redirects' => false])
            ->delete(sprintf('https://graph.facebook.com/%s/%s/subscribed_apps', $version, rawurlencode($pageId)));
        if (! $response->successful()) {
            throw new UnexpectedValueException(sprintf('Meta did not accept the Facebook Page webhook unsubscribe request (HTTP %d).', $response->status()));
        }

        return GiftCodeSourceSubscription::query()->updateOrCreate(
            [
                'gift_code_source_id' => (string) $source->id,
                'provider' => 'facebook',
                'transport' => 'webhook',
            ],
            [
                'topic_or_rule' => 'page:feed',
                'configured_identity' => ['page_id' => $pageId, 'field' => 'feed'],
                'status' => 'disabled',
                'last_error_code' => null,
            ],
        );
    }
}
