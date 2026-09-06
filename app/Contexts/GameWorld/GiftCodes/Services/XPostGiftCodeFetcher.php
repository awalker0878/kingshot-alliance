<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Adapters\Concerns\HandlesGiftCodeProviderResponses;
use App\Contexts\GameWorld\GiftCodes\Adapters\OfficialXGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

final class XPostGiftCodeFetcher
{
    use HandlesGiftCodeProviderResponses;

    public function fetch(GiftCodeSourceRegistry $source, string $postId): GiftCodeProviderPublication
    {
        if ($source->adapter_key !== OfficialXGiftCodeSourceAdapter::KEY) {
            throw new UnexpectedValueException('X canonical fetch requires the official X source adapter.');
        }
        if (preg_match('/^[0-9]{1,32}$/D', $postId) !== 1) {
            throw new UnexpectedValueException('X canonical fetch requires a numeric Post id.');
        }

        $policy = $source->provenance_policy ?? [];
        $userId = $this->requiredPolicyString($policy, 'x_user_id', 32);
        $username = $this->requiredPolicyString($policy, 'x_username', 30);
        $token = trim((string) config('game_world.gift_codes.x_bearer_token', ''));
        if ($token === '') {
            throw new UnexpectedValueException('X canonical fetch requires a configured bearer token.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(max(1, min(30, (int) config('game_world.gift_codes.ingestion_timeout_seconds', 10))))
            ->withOptions(['allow_redirects' => false])
            ->get('https://api.x.com/2/tweets/'.rawurlencode($postId), [
                'tweet.fields' => 'created_at,author_id,edit_history_tweet_ids',
                'expansions' => 'author_id',
                'user.fields' => 'username',
            ]);
        $this->assertJsonSuccess($response, 'X canonical Post lookup');
        $payload = $response->json();
        $post = is_array($payload) ? ($payload['data'] ?? null) : null;
        if (! is_array($post)) {
            throw new UnexpectedValueException('X canonical Post lookup did not return a Post object.');
        }

        $actualPostId = $this->requiredString($post['id'] ?? null, 'Post id', 32);
        $authorId = $this->requiredString($post['author_id'] ?? null, 'author id', 32);
        $text = $this->requiredString($post['text'] ?? null, 'Post text', 20_000);
        if (! hash_equals($postId, $actualPostId) || ! hash_equals($userId, $authorId)) {
            throw new UnexpectedValueException('X canonical Post identity did not match the configured official account.');
        }
        $this->assertExpectedAccount($payload, $userId, $username);

        return new GiftCodeProviderPublication(
            provider: 'x',
            providerItemId: $actualPostId,
            sourceUrl: sprintf('https://x.com/%s/status/%s', $username, $actualPostId),
            content: $text,
            publishedAt: $this->optionalString($post['created_at'] ?? null, 120),
            updatedAt: null,
            retrievalVersion: $this->giftCodeRetrievalVersion($response),
        );
    }

    /** @param array<string,mixed> $payload */
    private function assertExpectedAccount(array $payload, string $userId, string $username): void
    {
        $includes = $payload['includes'] ?? null;
        $users = is_array($includes) ? ($includes['users'] ?? null) : null;
        if (! is_array($users) || ! array_is_list($users)) {
            throw new UnexpectedValueException('X canonical Post response did not include the configured author identity.');
        }
        foreach ($users as $user) {
            if (! is_array($user)) {
                continue;
            }
            $id = $this->optionalString($user['id'] ?? null, 32);
            $name = $this->optionalString($user['username'] ?? null, 30);
            if ($id === $userId && $name !== null && mb_strtolower($name) === mb_strtolower($username)) {
                return;
            }
        }

        throw new UnexpectedValueException('X canonical Post author identity did not match the configured source policy.');
    }

    private function assertJsonSuccess(Response $response, string $operation): void
    {
        $this->assertGiftCodeProviderSuccess($response, $operation);
        if (! str_contains(mb_strtolower((string) $response->header('Content-Type')), 'json')) {
            throw new UnexpectedValueException($operation.' did not return JSON content.');
        }
    }

    /** @param array<string,mixed> $policy */
    private function requiredPolicyString(array $policy, string $key, int $maximum): string
    {
        $value = $this->optionalString($policy[$key] ?? null, $maximum);
        if ($value === null) {
            throw new UnexpectedValueException('The official X source requires policy '.$key.'.');
        }

        return $value;
    }

    private function requiredString(mixed $value, string $field, int $maximum): string
    {
        $value = $this->optionalString($value, $maximum);
        if ($value === null) {
            throw new UnexpectedValueException('X canonical Post requires '.$field.'.');
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
}
