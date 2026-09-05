<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Adapters;

use App\Contexts\GameWorld\GiftCodes\Adapters\Concerns\HandlesGiftCodeProviderResponses;
use App\Contexts\GameWorld\GiftCodes\Contracts\GiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionObservation;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionPage;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeSourceCheckpoint;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

final class OfficialXGiftCodeSourceAdapter implements GiftCodeSourceAdapter
{
    use HandlesGiftCodeProviderResponses;

    public const KEY = 'x-api-v2-kingshot-v1';

    public function key(): string
    {
        return self::KEY;
    }

    public function acquire(GiftCodeSourceRegistry $source, ?string $cursor, int $limit): GiftCodeIngestionPage
    {
        $policy = $source->provenance_policy ?? [];
        if (($policy['platform_api_access_confirmed'] ?? false) !== true) {
            throw new UnexpectedValueException('The official X adapter requires confirmed X API access.');
        }
        $userId = $this->requiredPolicyString($policy, 'x_user_id', 32);
        $username = $this->requiredPolicyString($policy, 'x_username', 30);
        $token = trim((string) config('game_world.gift_codes.x_bearer_token', ''));

        if (mb_strtolower(rtrim(trim((string) $source->canonical_domain), '.')) !== 'x.com') {
            throw new UnexpectedValueException('The official X adapter requires x.com as the canonical source domain.');
        }
        if (preg_match('/^[0-9]{1,32}$/D', $userId) !== 1) {
            throw new UnexpectedValueException('The official X adapter requires a numeric X user id.');
        }
        if (preg_match('/^[A-Za-z0-9_]{1,30}$/D', $username) !== 1) {
            throw new UnexpectedValueException('The official X adapter requires a valid expected X username.');
        }
        if ($token === '') {
            throw new UnexpectedValueException('The official X adapter requires a configured X API bearer token.');
        }
        $cursor = $cursor === null ? null : trim($cursor);
        if ($cursor !== null && $cursor !== '' && preg_match('/^[0-9]{1,32}$/D', $cursor) !== 1) {
            throw new UnexpectedValueException('The X API cursor must be a post id high-water mark.');
        }
        $cursor = $cursor === '' ? null : $cursor;

        $pageSize = max(5, min(100, $limit));
        $url = sprintf('https://api.x.com/2/users/%s/tweets', rawurlencode($userId));
        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(max(1, min(30, (int) config('game_world.gift_codes.ingestion_timeout_seconds', 10))))
            ->withOptions(['allow_redirects' => false])
            ->get($url, array_filter([
                'max_results' => $pageSize,
                'tweet.fields' => 'created_at,author_id',
                'expansions' => 'author_id',
                'user.fields' => 'username',
                'since_id' => $cursor,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''));

        $this->assertGiftCodeProviderSuccess($response, 'The X API');
        if (! str_contains(mb_strtolower((string) $response->header('Content-Type')), 'json')) {
            throw new UnexpectedValueException('The X API did not return JSON content.');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new UnexpectedValueException('The X API response must be a JSON object.');
        }
        $posts = $payload['data'] ?? [];
        if (! is_array($posts) || ! array_is_list($posts) || count($posts) > $pageSize) {
            throw new UnexpectedValueException('The X API returned an invalid or unbounded post collection.');
        }

        if ($posts !== []) {
            $this->assertExpectedAccount($payload, $userId, $username);
        }

        $retrievalVersion = $this->giftCodeRetrievalVersion($response);
        $observations = [];
        $highWater = $cursor;
        foreach ($posts as $position => $post) {
            if (! is_array($post)) {
                throw new UnexpectedValueException(sprintf('X post %d must be an object.', $position + 1));
            }
            $postId = $this->requiredString($post['id'] ?? null, 'post id', $position + 1, 32);
            $highWater = $this->greaterNumericId($highWater, $postId);
            $authorId = $this->requiredString($post['author_id'] ?? null, 'author id', $position + 1, 32);
            $text = $this->requiredString($post['text'] ?? null, 'text', $position + 1, 20_000);
            if (! hash_equals($userId, $authorId)) {
                throw new UnexpectedValueException(sprintf('X post %d was not authored by the configured account.', $position + 1));
            }

            foreach ($this->extractExplicitCodes($text) as $code) {
                $sourceUrl = sprintf('https://x.com/%s/status/%s', $username, $postId);
                $fingerprint = hash('sha256', implode('|', [$postId, $code, $text]));
                $observations[] = new GiftCodeIngestionObservation(
                    code: $code,
                    assertion: 'available',
                    assertionPayload: null,
                    sourceUrl: $sourceUrl,
                    claimedExpiresAt: null,
                    expiryPrecision: null,
                    expiryTimezone: null,
                    publishedAt: $this->optionalString($post['created_at'] ?? null, 120),
                    sourceVersion: 'x-post:'.$postId,
                    retrievalVersion: $retrievalVersion,
                    parserVersion: self::KEY,
                    contentFingerprint: $fingerprint,
                    rawEvidenceRef: $sourceUrl.'#gift-code='.rawurlencode($code),
                    verificationPassed: true,
                );
            }
        }

        $providerRequestId = $this->giftCodeProviderRequestId($response);

        return new GiftCodeIngestionPage(
            observations: $observations,
            nextCursor: $highWater,
            retrievalVersion: $retrievalVersion,
            providerRequestId: $providerRequestId,
            rateLimit: $this->giftCodeRateLimit($response),
            checkpoint: new GiftCodeSourceCheckpoint(
                cursor: $highWater,
                retrievalVersion: $retrievalVersion,
                providerRequestId: $providerRequestId,
                providerState: ['post_high_water' => $highWater],
            ),
        );
    }

    /** @param array<string, mixed> $payload */
    private function assertExpectedAccount(array $payload, string $userId, string $username): void
    {
        $includes = $payload['includes'] ?? null;
        $users = is_array($includes) ? ($includes['users'] ?? null) : null;
        if (! is_array($users) || ! array_is_list($users)) {
            throw new UnexpectedValueException('The X API response did not include the configured author identity.');
        }

        foreach ($users as $user) {
            if (! is_array($user)) {
                continue;
            }
            $includedId = $this->optionalString($user['id'] ?? null, 32);
            $includedUsername = $this->optionalString($user['username'] ?? null, 30);
            if ($includedId === $userId
                && $includedUsername !== null
                && mb_strtolower($includedUsername) === mb_strtolower($username)) {
                return;
            }
        }

        throw new UnexpectedValueException('The X API author identity did not match the configured source policy.');
    }

    /** @return list<string> */
    private function extractExplicitCodes(string $text): array
    {
        $codes = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            if (! is_string($line)) {
                continue;
            }
            if (preg_match(
                '/^\s*(?:🎁\s*)?(?:gift\s*code|redeem\s*code)\s*[:：]\s*([A-Za-z0-9_-]{3,64})\s*[.!]?\s*$/iu',
                $line,
                $matches,
            ) !== 1) {
                continue;
            }
            $codes[] = $matches[1];
        }

        return array_values(array_unique($codes));
    }

    /** @param array<string, mixed> $policy */
    private function requiredPolicyString(array $policy, string $key, int $maximum): string
    {
        $value = $this->optionalString($policy[$key] ?? null, $maximum);
        if ($value === null) {
            throw new UnexpectedValueException(sprintf('The official X adapter requires source policy %s.', $key));
        }

        return $value;
    }

    private function requiredString(mixed $value, string $field, int $position, int $maximum): string
    {
        $value = $this->optionalString($value, $maximum);
        if ($value === null) {
            throw new UnexpectedValueException(sprintf('X post %d requires a non-empty %s.', $position, $field));
        }

        return $value;
    }

    private function optionalString(mixed $value, int $maximum): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw new UnexpectedValueException('X API scalar fields must be strings.');
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $maximum) {
            throw new UnexpectedValueException('An X API scalar field exceeded its maximum length.');
        }

        return $value;
    }

    private function greaterNumericId(?string $current, string $candidate): string
    {
        if ($current === null || strlen($candidate) > strlen($current)) {
            return $candidate;
        }
        if (strlen($candidate) < strlen($current)) {
            return $current;
        }

        return strcmp($candidate, $current) > 0 ? $candidate : $current;
    }
}
