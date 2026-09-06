<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Adapters\Concerns\HandlesGiftCodeProviderResponses;
use App\Contexts\GameWorld\GiftCodes\Adapters\FacebookPageGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

final class FacebookPagePostGiftCodeFetcher
{
    use HandlesGiftCodeProviderResponses;

    public function fetch(GiftCodeSourceRegistry $source, string $postId): GiftCodeProviderPublication
    {
        if ($source->adapter_key !== FacebookPageGiftCodeSourceAdapter::KEY) {
            throw new UnexpectedValueException('Facebook canonical fetch requires the Facebook Page source adapter.');
        }

        $policy = $source->provenance_policy ?? [];
        $pageId = is_string($policy['facebook_page_id'] ?? null) ? trim($policy['facebook_page_id']) : '';
        if ($pageId === '' || preg_match('/^[0-9]{1,64}$/D', $pageId) !== 1) {
            throw new UnexpectedValueException('The Facebook Page source requires a configured numeric Page id.');
        }
        if (preg_match('/^[A-Za-z0-9_:-]{2,180}$/D', $postId) !== 1) {
            throw new UnexpectedValueException('The Facebook Page webhook referenced an invalid post id.');
        }

        $token = trim((string) config('game_world.gift_codes.facebook_access_token', ''));
        $version = trim((string) config('game_world.gift_codes.meta_graph_api_version', 'v26.0'));
        if ($token === '') {
            throw new UnexpectedValueException('The Facebook Page canonical fetch requires a configured Page access token.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(max(1, min(30, (int) config('game_world.gift_codes.ingestion_timeout_seconds', 10))))
            ->withOptions(['allow_redirects' => false])
            ->get(sprintf('https://graph.facebook.com/%s/%s', $version, rawurlencode($postId)), [
                'fields' => 'id,message,created_time,updated_time,permalink_url,from',
            ]);

        $this->assertJsonSuccess($response, 'Facebook Page canonical post lookup');
        $payload = $response->json();
        if (! is_array($payload)) {
            throw new UnexpectedValueException('Facebook Page canonical post lookup did not return an object.');
        }

        $actualId = $this->requiredString($payload['id'] ?? null, 'post id', 180);
        if (! hash_equals($postId, $actualId)) {
            throw new UnexpectedValueException('Facebook Page canonical post id did not match the webhook delivery.');
        }
        $from = $payload['from'] ?? null;
        if (is_array($from)) {
            $fromId = $this->requiredString($from['id'] ?? null, 'Page author id', 64);
            if (! hash_equals($pageId, $fromId)) {
                throw new UnexpectedValueException('Facebook Page canonical post was not authored by the configured Page.');
            }
        } elseif (! str_starts_with($actualId, $pageId.'_')) {
            throw new UnexpectedValueException('Facebook Page canonical post identity cannot be tied to the configured Page.');
        }

        $permalink = $this->requiredString($payload['permalink_url'] ?? null, 'permalink', 2048);
        $this->assertFacebookUrl($permalink);
        $message = $this->optionalString($payload['message'] ?? null, 40_000) ?? '';

        return new GiftCodeProviderPublication(
            provider: 'facebook',
            providerItemId: $actualId,
            sourceUrl: $permalink,
            content: $message,
            publishedAt: $this->optionalString($payload['created_time'] ?? null, 120),
            updatedAt: $this->optionalString($payload['updated_time'] ?? null, 120),
            retrievalVersion: $this->giftCodeRetrievalVersion($response),
        );
    }

    private function assertJsonSuccess(Response $response, string $operation): void
    {
        $this->assertGiftCodeProviderSuccess($response, $operation);
        if (! str_contains(mb_strtolower((string) $response->header('Content-Type')), 'json')) {
            throw new UnexpectedValueException($operation.' did not return JSON content.');
        }
    }

    private function assertFacebookUrl(string $url): void
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        $host = is_string($host) ? mb_strtolower(rtrim($host, '.')) : null;
        if ($scheme !== 'https'
            || $host === null
            || ($host !== 'facebook.com' && ! str_ends_with($host, '.facebook.com'))) {
            throw new UnexpectedValueException('Facebook evidence links must remain on facebook.com over HTTPS.');
        }
    }

    private function requiredString(mixed $value, string $field, int $maximum): string
    {
        $value = $this->optionalString($value, $maximum);
        if ($value === null) {
            throw new UnexpectedValueException('Facebook Page canonical post requires '.$field.'.');
        }

        return $value;
    }

    private function optionalString(mixed $value, int $maximum): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw new UnexpectedValueException('Facebook Graph API scalar fields must be strings.');
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $maximum) {
            throw new UnexpectedValueException('A Facebook Graph API scalar field exceeded its maximum length.');
        }

        return $value;
    }
}
