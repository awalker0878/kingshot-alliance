<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\GiftCodes;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\GameWorld\GiftCodes\Actions\ManageGiftCodeCuratorGrant;
use App\Contexts\GameWorld\GiftCodes\Adapters\CenturyGamesKingshotNewsRssGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\JsonFeedGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\OfficialXGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\RssAtomGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\StructuredHtmlGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\Platform\Administration\Actions\ManagePlatformAdministrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class GiftCodeModerationHttpV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_moderation_routes_require_the_flag_mfa_authority_and_recent_password(): void
    {
        $administrator = $this->administrator();

        config()->set('game_world.gift_codes.moderation', false);
        $this->actingAs($administrator)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('platform.gift-codes.index'))
            ->assertNotFound();

        config()->set('game_world.gift_codes.moderation', true);
        $this->actingAs($administrator)
            ->withSession(['auth.password_confirmed_at' => 0])
            ->get(route('platform.gift-codes.index'))
            ->assertRedirect(route('password.confirm'));

        $unprivileged = app(ScenarioFactory::class)->authUser();
        $unprivileged->forceFill([
            'email_verified_at' => now(),
            'two_factor_confirmed_at' => now(),
        ])->save();
        $this->flushSession();
        $this->actingAs($unprivileged)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('platform.gift-codes.index'))
            ->assertForbidden();
    }

    public function test_platform_administrator_can_render_moderation_and_register_installed_source_adapters(): void
    {
        config()->set('game_world.gift_codes.moderation', true);
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('platform.gift-codes.index'))
            ->assertOk()
            ->assertInertia(static fn (Assert $page): Assert => $page
                ->component('Platform/GiftCodes/Review')
                ->where('canManagePlatformPolicy', true)
                ->has('adapterKeys', 5)
                ->where('adapterKeys.0', JsonFeedGiftCodeSourceAdapter::KEY)
                ->where('adapterKeys.1', RssAtomGiftCodeSourceAdapter::KEY)
                ->where('adapterKeys.2', StructuredHtmlGiftCodeSourceAdapter::KEY)
                ->where('adapterKeys.3', OfficialXGiftCodeSourceAdapter::KEY)
                ->where('adapterKeys.4', CenturyGamesKingshotNewsRssGiftCodeSourceAdapter::KEY));

        foreach ([
            [JsonFeedGiftCodeSourceAdapter::KEY, 'http-json-feed', '/gift-codes.json', 'approved_json_feed'],
            [RssAtomGiftCodeSourceAdapter::KEY, 'http-rss-feed', '/gift-codes.xml', 'approved_rss_atom_feed'],
            [StructuredHtmlGiftCodeSourceAdapter::KEY, 'http-html-feed', '/gift-codes', 'approved_structured_html'],
        ] as [$adapterKey, $sourceKey, $feedPath, $verificationMethod]) {
            $this->actingAs($administrator)
                ->withSession(['auth.password_confirmed_at' => time()])
                ->post(route('platform.gift-codes.sources.store'), [
                    'source_key' => $sourceKey,
                    'name' => 'HTTP source '.$sourceKey,
                    'classification' => 'official',
                    'canonical_domain' => 'publisher.example.test',
                    'verification_method' => $verificationMethod,
                    'adapter_key' => $adapterKey,
                    'feed_path' => $feedPath,
                    'auto_verify' => true,
                    'ingestion_enabled' => true,
                ])
                ->assertRedirect()
                ->assertSessionHas('actionReceipt');

            $this->assertDatabaseHas('gift_code_sources', [
                'source_key' => $sourceKey,
                'adapter_key' => $adapterKey,
                'ingestion_enabled' => true,
            ]);
        }
    }

    public function test_curator_bulk_confirmation_reauthorizes_and_rejects_a_stale_preview(): void
    {
        config()->set('game_world.gift_codes.moderation', true);
        $administrator = $this->administrator();
        $curator = app(ScenarioFactory::class)->authUser();
        $curator->forceFill([
            'email_verified_at' => now(),
            'two_factor_confirmed_at' => now(),
        ])->save();
        app(ManageGiftCodeCuratorGrant::class)->grant(
            app(AccountIdentityQuery::class)->require((int) $administrator->id),
            (int) $curator->id,
        );
        $giftCode = $this->giftCode('HTTP-BULK-STALE');

        $this->actingAs($curator)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('platform.gift-codes.bulk'), [
                'gift_code_ids' => [(string) $giftCode->id],
                'action' => 'quarantine',
                'confirmed' => false,
                'reason' => 'Review suspicious evidence.',
            ])
            ->assertRedirect()
            ->assertSessionHas('giftCodeBulkReviewPreview.statusRevisions.'.(string) $giftCode->id, 1);

        $giftCode->forceFill(['status_revision' => 2])->save();
        $this->actingAs($curator)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('platform.gift-codes.bulk'), [
                'gift_code_ids' => [(string) $giftCode->id],
                'expected_status_revisions' => [(string) $giftCode->id => 1],
                'action' => 'quarantine',
                'confirmed' => true,
                'reason' => 'Review suspicious evidence.',
            ])
            ->assertRedirect()
            ->assertSessionHas('giftCodeBulkReviewResult.failed', 1);

        self::assertSame(0, $giftCode->moderationDecisions()->count());
        $this->actingAs($curator)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('platform.gift-codes.sources.store'), [
                'source_key' => 'curator-cannot-register',
                'name' => 'Unauthorized source',
                'classification' => 'official',
                'canonical_domain' => 'publisher.example.test',
                'verification_method' => 'manual_review',
                'auto_verify' => false,
                'ingestion_enabled' => false,
            ])
            ->assertForbidden();
    }

    private function administrator(): User
    {
        $user = app(ScenarioFactory::class)->authUser();
        $user->forceFill([
            'email_verified_at' => now(),
            'two_factor_confirmed_at' => now(),
        ])->save();
        app(ManagePlatformAdministrator::class)->grant((int) $user->id);

        return $user;
    }

    private function giftCode(string $code): GiftCode
    {
        return GiftCode::query()->create([
            'code' => $code,
            'normalized_code' => $code,
            'status' => GiftCodeStatus::Pending,
            'status_revision' => 1,
            'status_reason_code' => 'awaiting_verified_evidence',
            'status_evidence_ids' => [],
            'status_changed_at' => now(),
            'status_derived_at' => now(),
            'discovered_at' => now(),
            'expires_revision' => 0,
        ]);
    }
}
