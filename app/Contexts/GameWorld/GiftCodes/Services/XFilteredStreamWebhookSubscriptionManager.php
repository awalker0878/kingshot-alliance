<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Adapters\OfficialXGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceSubscription;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

final class XFilteredStreamWebhookSubscriptionManager
{
    public function subscribe(GiftCodeSourceRegistry $source): GiftCodeSourceSubscription
    {
        $this->assertAvailable($source);
        $token = $this->bearerToken();
        $username = trim((string) (($source->provenance_policy ?? [])['x_username'] ?? ''));
        if (preg_match('/^[A-Za-z0-9_]{1,30}$/D', $username) !== 1) {
            throw new UnexpectedValueException('X Filtered Stream subscription requires the configured official username.');
        }

        $callback = route('api.gift-code-sources.x-webhook.receive', ['source' => (string) $source->id], true);
        if (! str_starts_with($callback, 'https://') || parse_url($callback, PHP_URL_PORT) !== null) {
            throw new UnexpectedValueException('X webhook callback must be a public HTTPS URL without an explicit port.');
        }

        $webhookId = $this->ensureWebhook($token, $callback);
        $ruleTag = 'kingshot-gift-code:'.substr($source->source_key, 0, 180);
        $this->ensureRule($token, 'from:'.$username, $ruleTag);
        $this->ensureStreamLink($token, $webhookId);

        return GiftCodeSourceSubscription::query()->updateOrCreate(
            [
                'gift_code_source_id' => (string) $source->id,
                'provider' => 'x',
                'transport' => 'filtered_stream_webhook',
            ],
            [
                'provider_subscription_id' => $webhookId,
                'topic_or_rule' => 'from:'.$username,
                'configured_identity' => [
                    'user_id' => ($source->provenance_policy ?? [])['x_user_id'] ?? null,
                    'username' => $username,
                    'rule_tag' => $ruleTag,
                ],
                'status' => 'active',
                'activated_at' => now(),
                'last_verified_at' => now(),
                'secret_version' => hash('sha256', $this->consumerSecret()),
                'last_error_code' => null,
            ],
        );
    }

    public function unsubscribe(GiftCodeSourceRegistry $source): GiftCodeSourceSubscription
    {
        $this->assertAvailable($source);
        $subscription = GiftCodeSourceSubscription::query()
            ->where('gift_code_source_id', $source->id)
            ->where('provider', 'x')
            ->where('transport', 'filtered_stream_webhook')
            ->first();
        if (! $subscription instanceof GiftCodeSourceSubscription
            || ! is_string($subscription->provider_subscription_id)
            || preg_match('/^[0-9]{1,32}$/D', $subscription->provider_subscription_id) !== 1) {
            throw new UnexpectedValueException('No provisioned X Filtered Stream webhook exists for this source.');
        }

        $response = Http::withToken($this->bearerToken())
            ->acceptJson()
            ->timeout($this->timeout())
            ->withOptions(['allow_redirects' => false])
            ->delete('https://api.x.com/2/tweets/search/webhooks/'.rawurlencode($subscription->provider_subscription_id));
        $this->assertSuccess($response, 'X Filtered Stream webhook unlink');

        $subscription->forceFill([
            'status' => 'disabled',
            'last_error_code' => null,
        ])->save();

        return $subscription->refresh();
    }

    private function ensureWebhook(string $token, string $callback): string
    {
        $list = Http::withToken($token)
            ->acceptJson()
            ->timeout($this->timeout())
            ->withOptions(['allow_redirects' => false])
            ->get('https://api.x.com/2/webhooks');
        $this->assertSuccess($list, 'X webhook list');
        $payload = $list->json();
        $rows = is_array($payload) ? ($payload['data'] ?? []) : [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (! is_array($row) || ($row['url'] ?? null) !== $callback) {
                    continue;
                }
                $id = is_string($row['id'] ?? null) ? trim($row['id']) : '';
                if (preg_match('/^[0-9]{1,32}$/D', $id) === 1) {
                    return $id;
                }
            }
        }

        $created = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout())
            ->withOptions(['allow_redirects' => false])
            ->post('https://api.x.com/2/webhooks', ['url' => $callback]);
        $this->assertSuccess($created, 'X webhook registration');
        $createdPayload = $created->json();
        $id = is_array($createdPayload) && is_array($createdPayload['data'] ?? null)
            ? trim((string) ($createdPayload['data']['id'] ?? ''))
            : '';
        if (preg_match('/^[0-9]{1,32}$/D', $id) !== 1) {
            throw new UnexpectedValueException('X webhook registration did not return a webhook id.');
        }

        return $id;
    }

    private function ensureRule(string $token, string $rule, string $tag): void
    {
        $list = Http::withToken($token)
            ->acceptJson()
            ->timeout($this->timeout())
            ->withOptions(['allow_redirects' => false])
            ->get('https://api.x.com/2/tweets/search/stream/rules');
        $this->assertSuccess($list, 'X Filtered Stream rules lookup');
        $payload = $list->json();
        $rows = is_array($payload) ? ($payload['data'] ?? []) : [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (is_array($row) && trim((string) ($row['value'] ?? '')) === $rule) {
                    return;
                }
            }
        }

        $created = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout())
            ->withOptions(['allow_redirects' => false])
            ->post('https://api.x.com/2/tweets/search/stream/rules', [
                'add' => [[
                    'value' => $rule,
                    'tag' => $tag,
                ]],
            ]);
        $this->assertSuccess($created, 'X Filtered Stream rule creation');
    }

    private function ensureStreamLink(string $token, string $webhookId): void
    {
        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout($this->timeout())
            ->withOptions(['allow_redirects' => false])
            ->post(
                'https://api.x.com/2/tweets/search/webhooks/'.rawurlencode($webhookId),
                [
                    'tweet.fields' => 'created_at,author_id,edit_history_tweet_ids',
                    'expansions' => 'author_id',
                    'user.fields' => 'username,id',
                ],
            );
        $this->assertSuccess($response, 'X Filtered Stream webhook provisioning');
        $payload = $response->json();
        $provisioned = is_array($payload) && is_array($payload['data'] ?? null)
            ? ($payload['data']['provisioned'] ?? null)
            : null;
        if ($provisioned !== true) {
            throw new UnexpectedValueException('X did not confirm the Filtered Stream webhook link as provisioned.');
        }
    }

    private function assertAvailable(GiftCodeSourceRegistry $source): void
    {
        if ($source->adapter_key !== OfficialXGiftCodeSourceAdapter::KEY) {
            throw new UnexpectedValueException('X Filtered Stream webhook subscriptions require the official X source adapter.');
        }
        if (! (bool) config('game_world.gift_codes.x_realtime_transport', false)
            || ! (bool) config('game_world.gift_codes.x_filtered_stream_webhook_entitled', false)) {
            throw new UnexpectedValueException('X Filtered Stream webhooks require the explicitly configured Enterprise entitlement.');
        }
        $this->bearerToken();
        $this->consumerSecret();
    }

    private function bearerToken(): string
    {
        $token = trim((string) config('game_world.gift_codes.x_bearer_token', ''));
        if ($token === '') {
            throw new UnexpectedValueException('X Filtered Stream webhook management requires a bearer token.');
        }

        return $token;
    }

    private function consumerSecret(): string
    {
        $secret = trim((string) config('game_world.gift_codes.x_consumer_secret', ''));
        if (strlen($secret) < 20) {
            throw new UnexpectedValueException('X Filtered Stream webhooks require the application consumer secret for CRC and signature validation.');
        }

        return $secret;
    }

    private function timeout(): int
    {
        return max(1, min(30, (int) config('game_world.gift_codes.ingestion_timeout_seconds', 10)));
    }

    private function assertSuccess(Response $response, string $operation): void
    {
        if (! $response->successful()) {
            throw new UnexpectedValueException(sprintf('%s returned HTTP %d.', $operation, $response->status()));
        }
        if (! str_contains(mb_strtolower((string) $response->header('Content-Type')), 'json')) {
            throw new UnexpectedValueException($operation.' did not return JSON content.');
        }
    }
}
