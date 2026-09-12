<?php

declare(strict_types=1);

namespace Tests\Contexts\Platform\Integrations\Feature;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Platform\Integrations\Enums\IntegrationCatalogueKind;
use App\Contexts\Platform\Integrations\Models\ApiCredential;
use App\Contexts\Platform\Integrations\Models\WebhookDelivery;
use App\Contexts\Platform\Integrations\Models\WebhookSubscription;
use App\Contexts\Platform\Integrations\Queries\IntegrationManagementQuery;
use App\Contexts\Platform\Integrations\Queries\IntegrationUsageQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Contexts\Platform\Integrations\Support\IntegrationCatalogueFixture;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class IntegrationCataloguePagesTest extends TestCase
{
    use RefreshDatabase;

    public static function kinds(): iterable
    {
        foreach (IntegrationCatalogueKind::cases() as $kind) {
            yield $kind->value => [$kind];
        }
    }

    #[DataProvider('kinds')]
    public function test_complete_catalogues_have_bounded_pages_and_current_totals(IntegrationCatalogueKind $kind): void
    {
        $factory = app(ScenarioFactory::class);
        $player = $factory->player($factory->account()->userId);
        $alliance = $factory->alliance($player);
        IntegrationCatalogueFixture::seed($alliance->allianceId, $player->playerId);
        $model = match ($kind) {
            IntegrationCatalogueKind::Credentials => ApiCredential::class,
            IntegrationCatalogueKind::Webhooks => WebhookSubscription::class,
            IntegrationCatalogueKind::Deliveries => WebhookDelivery::class,
        };
        $loaded = 0;
        $model::retrieved(static function () use (&$loaded): void {
            $loaded++;
        });
        $cursor = null;
        $seen = [];
        do {
            $loaded = 0;
            $page = app(IntegrationManagementQuery::class)->page($player->playerId, $alliance->allianceId, $kind, $cursor);
            self::assertLessThanOrEqual(26, $loaded);
            self::assertLessThanOrEqual(25, count($page['items']));
            self::assertSame(61, $page['total']);
            foreach ($page['items'] as $row) {
                self::assertArrayNotHasKey('secret_hash', $row);
                self::assertArrayNotHasKey('signing_secret', $row);
                self::assertArrayNotHasKey('payload', $row);
                if ($kind === IntegrationCatalogueKind::Deliveries) {
                    self::assertSame('History 000', $row['subscriptionName']);
                    self::assertTrue($row['canRetry']);
                }
            }
            array_push($seen, ...array_column($page['items'], 'id'));
            $cursor = $page['nextCursor'];
        } while ($cursor !== null);
        self::assertSame($model::query()->orderByDesc('id')->pluck('id')->all(), $seen);
        self::assertSame(1, app(IntegrationUsageQuery::class)->activeCredentials($alliance->allianceId));
        self::assertSame(1, app(IntegrationUsageQuery::class)->activeWebhooks($alliance->allianceId));
    }

    public function test_deleted_boundary_and_new_rows_preserve_continuation_and_kind_isolation(): void
    {
        $factory = app(ScenarioFactory::class);
        $player = $factory->player($factory->account()->userId);
        $alliance = $factory->alliance($player);
        $f = IntegrationCatalogueFixture::seed($alliance->allianceId, $player->playerId);
        $query = app(IntegrationManagementQuery::class);
        $first = $query->page($player->playerId, $alliance->allianceId, IntegrationCatalogueKind::Credentials);
        $remaining = ApiCredential::query()->orderByDesc('id')->skip(25)->pluck('id')->all();
        ApiCredential::query()->whereKey($first['items'][24]['id'])->delete();
        $copy = $f['credential']->replicate();
        $copy->forceFill(['prefix' => 'new-after-frontier'])->save();
        $second = $query->page($player->playerId, $alliance->allianceId, IntegrationCatalogueKind::Credentials, $first['nextCursor']);
        self::assertSame(array_slice($remaining, 0, 25), array_column($second['items'], 'id'));
        $this->expectException(ValidationException::class);
        $query->page($player->playerId, $alliance->allianceId, IntegrationCatalogueKind::Webhooks, $first['nextCursor']);
    }

    public function test_continuation_rechecks_current_membership_and_delivery_retry_facts(): void
    {
        $factory = app(ScenarioFactory::class);
        $player = $factory->player($factory->account()->userId);
        $alliance = $factory->alliance($player);
        $f = IntegrationCatalogueFixture::seed($alliance->allianceId, $player->playerId);
        $query = app(IntegrationManagementQuery::class);
        $first = $query->page($player->playerId, $alliance->allianceId, IntegrationCatalogueKind::Deliveries);
        $f['webhook']->forceFill(['revoked_at' => now(), 'is_active' => false])->save();
        $next = $query->page($player->playerId, $alliance->allianceId, IntegrationCatalogueKind::Deliveries, $first['nextCursor']);
        self::assertSame(array_fill(0, 25, false), array_column($next['items'], 'canRetry'));
        DB::table('alliance_memberships')->where('alliance_id', $alliance->allianceId)->where('player_id', $player->playerId)->update(['status' => 'left']);
        $this->expectException(AuthorizationException::class);
        $query->page($player->playerId, $alliance->allianceId, IntegrationCatalogueKind::Deliveries, $first['nextCursor']);
    }

    public function test_http_composition_preserves_independent_pages_and_active_counts(): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $player = $factory->player($account->userId);
        $alliance = $factory->alliance($player);
        IntegrationCatalogueFixture::seed($alliance->allianceId, $player->playerId);
        $user = User::query()->findOrFail($account->userId);
        $user->forceFill(['email_verified_at' => now()])->save();
        $first = $this->actingAs($user)->get('/alliance/integrations', ['X-Inertia' => 'true'])->assertOk();
        $first->assertJsonCount(25, 'props.credentials')->assertJsonCount(25, 'props.webhooks')->assertJsonCount(25, 'props.recentDeliveries');
        $first->assertJsonPath('props.activeCounts.credentials', 1)->assertJsonPath('props.pagination.deliveries.total', 61);
        $first->assertJsonPath('props.credentials.0.active', false);
        $next = $this->get('/alliance/integrations?credentials_cursor='.urlencode($first->json('props.pagination.credentials.nextCursor')), ['X-Inertia' => 'true'])->assertOk();
        self::assertNotSame($first->json('props.credentials.0.id'), $next->json('props.credentials.0.id'));
        self::assertSame($first->json('props.webhooks'), $next->json('props.webhooks'));
        self::assertSame($first->json('props.recentDeliveries'), $next->json('props.recentDeliveries'));
    }

    public function test_a_cursor_cannot_cross_to_another_current_manager_scope(): void
    {
        $factory = app(ScenarioFactory::class);
        $player = $factory->player($factory->account()->userId);
        $alliance = $factory->alliance($player);
        IntegrationCatalogueFixture::seed($alliance->allianceId, $player->playerId);
        $query = app(IntegrationManagementQuery::class);
        $first = $query->page($player->playerId, $alliance->allianceId, IntegrationCatalogueKind::Credentials);
        $other = $factory->player($factory->account()->userId);
        $otherAlliance = $factory->alliance($other);
        $this->expectException(ValidationException::class);
        $query->page($other->playerId, $otherAlliance->allianceId, IntegrationCatalogueKind::Credentials, $first['nextCursor']);
    }
}
