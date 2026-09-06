<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

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
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeSourceActivationReadiness;

final readonly class EvaluateGiftCodeSourceActivationReadiness
{
    public function __construct(private GiftCodeSourceAdapterRegistry $adapters) {}

    public function forSource(GiftCodeSourceRegistry $source): GiftCodeSourceActivationReadiness
    {
        $adapterKey = trim((string) $source->adapter_key);
        $domain = mb_strtolower(rtrim(trim((string) $source->canonical_domain), '.'));
        $policy = $source->provenance_policy ?? [];
        $checks = [];

        $checks['source_active'] = $this->check(
            $source->is_active && $source->revoked_at === null,
            'Source is active and not revoked.',
            'Source is inactive or revoked.',
        );
        $checks['adapter_installed'] = $this->check(
            $adapterKey !== '' && $this->adapters->find($adapterKey) !== null,
            'Configured adapter is installed.',
            'A registered adapter is required before activation.',
        );
        $checks['canonical_domain_valid'] = $this->check(
            $domain !== '' && filter_var($domain, FILTER_VALIDATE_IP) === false && $domain !== 'localhost'
                && filter_var('https://'.$domain, FILTER_VALIDATE_URL) !== false,
            'Canonical source domain is valid.',
            'A public canonical source domain is required.',
        );
        $checks['identity_valid'] = $this->identityCheck($adapterKey, $domain, $policy);
        $checks['credentials_available'] = $this->credentialCheck($adapterKey);
        $checks['permissions_valid'] = $this->permissionCheck($adapterKey, $policy);
        $checks['provider_contract_valid'] = $this->contractCheck($adapterKey, $policy);
        $checks['policy_complete'] = $this->policyCheck($adapterKey, $policy, $source->classification);
        $checks['verification_boundary_valid'] = $this->check(
            ! (($policy['manual_evidence_allowed'] ?? false) === true && ($policy['auto_verify'] ?? false) === true)
                && ! ($adapterKey === RedditSubredditGiftCodeSourceAdapter::KEY && ($policy['auto_verify'] ?? false) === true),
            'Verification policy preserves source trust boundaries.',
            'Source verification policy would bypass the required trust boundary.',
        );

        return new GiftCodeSourceActivationReadiness($checks);
    }

    /** @param array<string,mixed> $policy
     * @return array{ready:bool,message:string}
     */
    private function identityCheck(string $adapterKey, string $domain, array $policy): array
    {
        $ready = match ($adapterKey) {
            OfficialXGiftCodeSourceAdapter::KEY => $domain === 'x.com'
                && $this->matches($policy, 'x_user_id', '/^[0-9]{1,32}$/D')
                && $this->matches($policy, 'x_username', '/^[A-Za-z0-9_]{1,30}$/D'),
            CenturyGamesKingshotNewsRssGiftCodeSourceAdapter::KEY => $domain === 'centurygames.com'
                && $this->validFeedPath($policy),
            DiscordChannelGiftCodeSourceAdapter::KEY => $domain === 'discord.com'
                && $this->matches($policy, 'discord_guild_id', '/^[0-9]{1,32}$/D')
                && $this->matches($policy, 'discord_channel_id', '/^[0-9]{1,32}$/D')
                && $this->snowflakeList($policy['discord_author_ids'] ?? null),
            YouTubeChannelGiftCodeSourceAdapter::KEY => $domain === 'youtube.com'
                && $this->matches($policy, 'youtube_channel_id', '/^UC[A-Za-z0-9_-]{20,40}$/D')
                && $this->nonEmpty($policy, 'youtube_channel_title', 200),
            RedditSubredditGiftCodeSourceAdapter::KEY => $domain === 'reddit.com'
                && $this->matches($policy, 'reddit_subreddit', '/^[A-Za-z0-9_]{2,32}$/D'),
            FacebookPageGiftCodeSourceAdapter::KEY => $domain === 'facebook.com'
                && $this->matches($policy, 'facebook_page_id', '/^[0-9]{1,64}$/D')
                && $this->nonEmpty($policy, 'facebook_page_name', 200),
            InstagramMediaGiftCodeSourceAdapter::KEY => $domain === 'instagram.com'
                && $this->matches($policy, 'instagram_user_id', '/^[0-9]{1,64}$/D')
                && $this->matches($policy, 'instagram_username', '/^[A-Za-z0-9._]{1,80}$/D'),
            JsonFeedGiftCodeSourceAdapter::KEY,
            RssAtomGiftCodeSourceAdapter::KEY,
            StructuredHtmlGiftCodeSourceAdapter::KEY => $this->validFeedPath($policy),
            default => false,
        };

        return $this->check(
            $ready,
            'Provider/source identity requirements are complete.',
            'Provider/source identity requirements are incomplete or do not match the adapter.',
        );
    }

    /** @return array{ready:bool,message:string} */
    private function credentialCheck(string $adapterKey): array
    {
        $keys = match ($adapterKey) {
            OfficialXGiftCodeSourceAdapter::KEY => ['game_world.gift_codes.x_bearer_token'],
            DiscordChannelGiftCodeSourceAdapter::KEY => ['game_world.gift_codes.discord_bot_token'],
            YouTubeChannelGiftCodeSourceAdapter::KEY => ['game_world.gift_codes.youtube_api_key'],
            RedditSubredditGiftCodeSourceAdapter::KEY => [
                'game_world.gift_codes.reddit_client_id',
                'game_world.gift_codes.reddit_client_secret',
                'game_world.gift_codes.reddit_user_agent',
            ],
            FacebookPageGiftCodeSourceAdapter::KEY => ['game_world.gift_codes.facebook_access_token'],
            InstagramMediaGiftCodeSourceAdapter::KEY => ['game_world.gift_codes.instagram_access_token'],
            default => [],
        };
        foreach ($keys as $key) {
            if (trim((string) config($key, '')) === '') {
                return $this->check(false, '', 'Required provider credentials are not configured.');
            }
        }

        return $this->check(true, 'Required provider credentials are configured.', '');
    }

    /** @param array<string,mixed> $policy
     * @return array{ready:bool,message:string}
     */
    private function permissionCheck(string $adapterKey, array $policy): array
    {
        $ready = match ($adapterKey) {
            OfficialXGiftCodeSourceAdapter::KEY,
            YouTubeChannelGiftCodeSourceAdapter::KEY,
            RedditSubredditGiftCodeSourceAdapter::KEY => ($policy['platform_api_access_confirmed'] ?? false) === true,
            DiscordChannelGiftCodeSourceAdapter::KEY => ($policy['platform_permission_confirmed'] ?? false) === true
                && ($policy['message_content_access_confirmed'] ?? false) === true,
            FacebookPageGiftCodeSourceAdapter::KEY,
            InstagramMediaGiftCodeSourceAdapter::KEY => ($policy['platform_permission_confirmed'] ?? false) === true,
            CenturyGamesKingshotNewsRssGiftCodeSourceAdapter::KEY => ($policy['provider_permission_confirmed'] ?? false) === true,
            default => true,
        };

        return $this->check(
            $ready,
            'Required provider/API permissions are confirmed.',
            'Provider/API eligibility or permission has not been confirmed.',
        );
    }

    /** @param array<string,mixed> $policy
     * @return array{ready:bool,message:string}
     */
    private function contractCheck(string $adapterKey, array $policy): array
    {
        $ready = match ($adapterKey) {
            JsonFeedGiftCodeSourceAdapter::KEY,
            RssAtomGiftCodeSourceAdapter::KEY => ($policy['provider_contract_confirmed'] ?? false) === true,
            StructuredHtmlGiftCodeSourceAdapter::KEY => ($policy['structured_contract_confirmed'] ?? false) === true,
            CenturyGamesKingshotNewsRssGiftCodeSourceAdapter::KEY => ($policy['provider_permission_confirmed'] ?? false) === true,
            default => true,
        };

        return $this->check(
            $ready,
            'Machine-readable provider contract is established.',
            'A documented machine-readable provider contract is required before activation.',
        );
    }

    /** @param array<string,mixed> $policy
     * @return array{ready:bool,message:string}
     */
    private function policyCheck(string $adapterKey, array $policy, string $classification): array
    {
        $ready = match ($adapterKey) {
            RedditSubredditGiftCodeSourceAdapter::KEY => $classification === 'independent'
                && ($policy['auto_verify'] ?? false) !== true,
            JsonFeedGiftCodeSourceAdapter::KEY,
            RssAtomGiftCodeSourceAdapter::KEY,
            StructuredHtmlGiftCodeSourceAdapter::KEY,
            CenturyGamesKingshotNewsRssGiftCodeSourceAdapter::KEY => $this->validFeedPath($policy),
            default => $adapterKey !== '',
        };

        return $this->check(
            $ready,
            'Source-specific policy is complete.',
            'Source-specific policy is incomplete.',
        );
    }

    /** @param array<string,mixed> $policy */
    private function matches(array $policy, string $key, string $pattern): bool
    {
        $value = is_string($policy[$key] ?? null) ? trim($policy[$key]) : '';

        return preg_match($pattern, $value) === 1;
    }

    /** @param array<string,mixed> $policy */
    private function nonEmpty(array $policy, string $key, int $maximum): bool
    {
        $value = is_string($policy[$key] ?? null) ? trim($policy[$key]) : '';

        return $value !== '' && mb_strlen($value) <= $maximum;
    }

    /** @param array<string,mixed> $policy */
    private function validFeedPath(array $policy): bool
    {
        $path = is_string($policy['feed_path'] ?? null) ? trim($policy['feed_path']) : '';
        $parts = $path === '' ? false : parse_url($path);

        return $path !== ''
            && str_starts_with($path, '/')
            && ! str_starts_with($path, '//')
            && $parts !== false
            && ($parts['path'] ?? null) === $path
            && ! str_contains('/'.$path.'/', '/../')
            && ! str_contains('/'.$path.'/', '/./');
    }

    private function snowflakeList(mixed $value): bool
    {
        if (! is_array($value) || $value === [] || count($value) > 50) {
            return false;
        }
        foreach ($value as $item) {
            if (! is_string($item) || preg_match('/^[0-9]{1,32}$/D', trim($item)) !== 1) {
                return false;
            }
        }

        return true;
    }

    /** @return array{ready:bool,message:string} */
    private function check(bool $ready, string $pass, string $fail): array
    {
        return ['ready' => $ready, 'message' => $ready ? $pass : $fail];
    }
}
