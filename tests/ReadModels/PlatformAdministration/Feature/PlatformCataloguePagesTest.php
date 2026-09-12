<?php

declare(strict_types=1);

namespace Tests\ReadModels\PlatformAdministration\Feature;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\TotpService;
use App\Contexts\Platform\Administration\Actions\ManagePlatformAdministrator;
use App\ReadModels\PlatformAdministration\PlatformAdministrationQuery;
use App\ReadModels\PlatformAdministration\PlatformCatalogueKind;
use App\ReadModels\PlatformAdministration\PlatformCatalogueQuery;
use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Contexts\Platform\Integrations\Support\IntegrationCatalogueFixture;
use Tests\ReadModels\PlatformAdministration\Support\PlatformCatalogueFixture;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class PlatformCataloguePagesTest extends TestCase
{
    use RefreshDatabase;

    /** @return iterable<string,array{PlatformCatalogueKind}> */
    public static function kinds(): iterable
    {
        foreach (PlatformCatalogueKind::cases() as $kind) {
            yield $kind->value => [$kind];
        }
    }

    #[DataProvider('kinds')]
    public function test_every_catalogue_is_reachable_in_bounded_privacy_safe_pages(PlatformCatalogueKind $kind): void
    {
        $factory = app(ScenarioFactory::class);
        $actor = $factory->account();
        app(ManagePlatformAdministrator::class)->grant($actor->userId);
        $player = $factory->player($actor->userId);
        $alliance = $factory->alliance($player);
        $expected = PlatformCatalogueFixture::seed($kind, $actor->userId, $alliance, $player->playerId);
        if ($kind === PlatformCatalogueKind::Alliances) {
            $expected[] = $alliance->allianceId;
        } elseif ($kind === PlatformCatalogueKind::Administrators) {
            $expected[] = (string) DB::table('platform_administrators')->where('user_id', $actor->userId)->value('id');
        }
        $query = app(PlatformCatalogueQuery::class);
        $cursor = null;
        $seen = [];
        do {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $page = $query->page($actor->userId, $kind, $cursor, $alliance->allianceId, PlatformCatalogueFixture::TRACE);
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            self::assertLessThanOrEqual(25, count($page['items']));
            self::assertSame(count($expected), $page['total']);
            self::assertSame(25, $page['pageSize']);
            self::assertLessThanOrEqual(9, count($queries));
            $sql = implode("\n", array_column($queries, 'query'));
            self::assertStringContainsString('limit 26', $sql);
            if ($kind === PlatformCatalogueKind::Alliances) {
                self::assertStringContainsString('"alliance_plan_assignments" where "alliance_id" in', $sql);
                self::assertStringContainsString('"alliance_platform_settings" where "alliance_id" in', $sql);
            }
            foreach ($page['items'] as $row) {
                foreach (['payload', 'exception', 'last_error', 'metadata', 'configuration', 'body', 'signing_secret'] as $private) {
                    self::assertArrayNotHasKey($private, $row);
                }
                if (array_key_exists('errorFingerprint', $row)) {
                    self::assertSame(substr(hash('sha256', PlatformCatalogueFixture::PRIVATE_ERROR), 0, 16), $row['errorFingerprint']);
                }
                $seen[] = $row[$kind === PlatformCatalogueKind::Features ? 'key' : 'id'];
            }
            $cursor = $page['nextCursor'];
        } while ($cursor !== null);
        self::assertSame($expected, $seen);
        self::assertSame(count($seen), count(array_unique($seen)));
    }

    public function test_deleted_boundary_new_records_and_revoked_authority_are_rechecked(): void
    {
        $factory = app(ScenarioFactory::class);
        $actor = $factory->account();
        $grant = app(ManagePlatformAdministrator::class)->grant($actor->userId);
        $player = $factory->player($actor->userId);
        $alliance = $factory->alliance($player);
        $expected = PlatformCatalogueFixture::seed(PlatformCatalogueKind::LegalHolds, $actor->userId, $alliance, $player->playerId);
        $query = app(PlatformCatalogueQuery::class);
        $first = $query->page($actor->userId, PlatformCatalogueKind::LegalHolds);
        DB::table('legal_holds')->where('id', $first['items'][24]['id'])->delete();
        PlatformCatalogueFixture::seed(PlatformCatalogueKind::LegalHolds, $actor->userId, $alliance, $player->playerId, 1);
        $next = $query->page($actor->userId, PlatformCatalogueKind::LegalHolds, $first['nextCursor']);
        self::assertSame(array_slice($expected, 25, 25), array_column($next['items'], 'id'));
        DB::table('platform_administrators')->where('id', $grant)->update(['revoked_at' => now()]);
        $this->expectException(AuthorizationException::class);
        $query->page($actor->userId, PlatformCatalogueKind::LegalHolds, $first['nextCursor']);
    }

    /** @return iterable<string,array{string}> */
    public static function invalidScopes(): iterable
    {
        foreach (['actor', 'kind', 'alliance', 'correlation', 'tampered', 'invalid-position'] as $case) {
            yield $case => [$case];
        }
    }

    #[DataProvider('invalidScopes')]
    public function test_cursors_cannot_cross_scope_or_accept_invalid_positions(string $case): void
    {
        $factory = app(ScenarioFactory::class);
        $actor = $factory->account();
        $other = $factory->account();
        $manage = app(ManagePlatformAdministrator::class);
        $manage->grant($actor->userId);
        $manage->grant($other->userId, app(AccountIdentityQuery::class)->require($actor->userId));
        $player = $factory->player($actor->userId);
        $alliance = $factory->alliance($player);
        $kind = $case === 'correlation' ? PlatformCatalogueKind::CorrelatedAudit : PlatformCatalogueKind::Features;
        PlatformCatalogueFixture::seed($kind, $actor->userId, $alliance, $player->playerId, 26);
        $query = app(PlatformCatalogueQuery::class);
        $cursor = $query->page($actor->userId, $kind, allianceId: $alliance->allianceId, correlation: PlatformCatalogueFixture::TRACE)['nextCursor'];
        self::assertNotNull($cursor);
        if ($case === 'tampered') {
            $cursor .= 'x';
        } elseif ($case === 'invalid-position') {
            $cursor = app(ScopedCursorCodec::class)->encode('platform-catalogue|'.$actor->userId.'|features|'.$alliance->allianceId.'|', ['after' => 'bad', 'through' => 'bad']);
        }
        $this->expectException(ValidationException::class);
        $query->page($case === 'actor' ? $other->userId : $actor->userId,
            $case === 'kind' ? PlatformCatalogueKind::LegalHolds : $kind, $cursor,
            $case === 'alliance' ? '01AAAAAAAAAAAAAAAAAAAAAAAA' : $alliance->allianceId,
            $case === 'correlation' ? str_repeat('f', 32) : PlatformCatalogueFixture::TRACE);
    }

    public function test_http_pages_preserve_independent_catalogues_and_off_page_selected_alliance(): void
    {
        $factory = app(ScenarioFactory::class);
        $actor = $factory->account();
        $grant = app(ManagePlatformAdministrator::class)->grant($actor->userId);
        $player = $factory->player($actor->userId);
        $alliance = $factory->alliance($player);
        foreach ([PlatformCatalogueKind::Alliances, PlatformCatalogueKind::Features, PlatformCatalogueKind::OutboxFailures] as $kind) {
            PlatformCatalogueFixture::seed($kind, $actor->userId, $alliance, $player->playerId, 61);
        }
        $user = User::query()->findOrFail($actor->userId);
        $user->forceFill(['email_verified_at' => now(), 'two_factor_secret' => app(TotpService::class)->generateSecret(), 'two_factor_confirmed_at' => now()])->save();
        $url = '/platform?alliance='.$alliance->allianceId;
        $first = $this->actingAs($user)->withSession(['accounts.recent_authentication_at' => now()->timestamp])->get($url, ['X-Inertia' => 'true'])->assertOk();
        $first->assertJsonCount(25, 'props.platform.alliances')->assertJsonCount(25, 'props.selectedAlliance.features')
            ->assertJsonPath('props.selectedAlliance.id', $alliance->allianceId)->assertJsonPath('props.selectedAlliance.plan', 'standard')
            ->assertJsonPath('props.selectedAlliance.featuresPagination.total', 61)->assertJsonPath('props.platform.metrics.activeAdministrators', 1);
        self::assertNotContains($alliance->allianceId, array_column($first->json('props.platform.alliances'), 'id'));
        $url .= '&alliances_cursor='.urlencode($first->json('props.platform.pagination.alliances.nextCursor'));
        $next = $this->get($url, ['X-Inertia' => 'true'])->assertOk();
        self::assertNotSame($first->json('props.platform.alliances'), $next->json('props.platform.alliances'));
        self::assertSame($first->json('props.selectedAlliance.features'), $next->json('props.selectedAlliance.features'));
        self::assertSame($alliance->allianceId, $next->json('props.selectedAlliance.id'));
        self::assertSame($first->json('props.platform.diagnostics.outboxFailures'), $next->json('props.platform.diagnostics.outboxFailures'));
        $this->get($url.'&features_cursor='.urlencode($first->json('props.selectedAlliance.featuresPagination.nextCursor')), ['X-Inertia' => 'true'])->assertOk()
            ->assertJsonPath('props.selectedAlliance.features.0.key', 'Platform history 035');
        DB::table('platform_administrators')->where('id', $grant)->update(['revoked_at' => now()]);
        $this->get($url, ['X-Inertia' => 'true'])->assertForbidden();
    }

    public function test_global_and_selected_usage_share_expiry_and_queued_delivery_semantics(): void
    {
        $factory = app(ScenarioFactory::class);
        $actor = $factory->account();
        app(ManagePlatformAdministrator::class)->grant($actor->userId);
        $player = $factory->player($actor->userId);
        $alliance = $factory->alliance($player);
        $f = IntegrationCatalogueFixture::seed($alliance->allianceId, $player->playerId);
        $f['delivery']->forceFill(['status' => 'queued'])->save();
        $dashboard = app(PlatformAdministrationQuery::class)->dashboard($actor->userId);
        self::assertSame(1, $dashboard['metrics']['pendingWebhooks']);
        self::assertSame(1, $dashboard['alliances'][0]['apiCredentials']);
        self::assertSame(1, $dashboard['alliances'][0]['webhooks']);
        self::assertSame($dashboard['alliances'][0], app(PlatformCatalogueQuery::class)->alliance($actor->userId, $alliance->allianceId));
    }
}
