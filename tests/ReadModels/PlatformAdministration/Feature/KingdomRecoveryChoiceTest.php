<?php

declare(strict_types=1);

namespace Tests\ReadModels\PlatformAdministration\Feature;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\TotpService;
use App\Contexts\Platform\Administration\Actions\ManagePlatformAdministrator;
use App\ReadModels\PlatformAdministration\KingdomRecoveryChoiceQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\ReadModels\PlatformAdministration\Support\KingdomRecoveryFixture;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class KingdomRecoveryChoiceTest extends TestCase
{
    use RefreshDatabase;

    /** @return iterable<string,array{string}> */
    public static function kinds(): iterable
    {
        yield 'Kingdoms beyond the old 250 clip' => ['kingdoms'];
        yield 'Governors beyond the old 1000 clip' => ['players'];
    }

    #[DataProvider('kinds')]
    public function test_all_choices_remain_reachable_with_bounded_queries_and_selected_choices(string $kind): void
    {
        $account = app(ScenarioFactory::class)->account();
        app(ManagePlatformAdministrator::class)->grant($account->userId);
        $kingdoms = KingdomRecoveryFixture::kingdoms();
        $players = KingdomRecoveryFixture::players($kingdoms[260]);
        $parent = $kind === 'players' ? $kingdoms[260] : null;
        $ids = $kind === 'players' ? $players : $kingdoms;
        $selected = $kind === 'players' ? $players[1000] : $kingdoms[260];
        $query = app(KingdomRecoveryChoiceQuery::class);
        $cursor = null;
        $seen = [];
        do {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $result = $query->page($account->userId, $kind, $parent, cursor: $cursor, selectedId: $selected);
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            self::assertLessThanOrEqual(7, count($queries));
            self::assertStringContainsString('limit 26', implode("\n", array_column($queries, 'query')));
            self::assertSame(count($ids), $result['total']);
            self::assertNotNull($result['selected']);
            self::assertSame($selected, $result['selected']['id']);
            self::assertLessThanOrEqual(25, count($result['page']['items']));
            foreach ($result['page']['items'] as $item) {
                self::assertSame(['id', 'name'], array_keys($item));
                $seen[] = $item['id'];
            }
            $cursor = $result['page']['nextCursor'];
        } while ($cursor !== null);
        self::assertSame($ids, $seen);
        $search = $kind === 'players' ? '1000' : '63260';
        self::assertSame([$selected], array_column($query->page($account->userId, $kind, $parent, $search)['page']['items'], 'id'));
        self::assertSame([], $query->page($account->userId, $kind, $parent, '%_')['page']['items']);
    }

    /** @return iterable<string,array{string}> */
    public static function invalidScopes(): iterable
    {
        foreach (['actor', 'kind', 'kingdom', 'search', 'tamper'] as $scope) {
            yield $scope => [$scope];
        }
    }

    #[DataProvider('invalidScopes')]
    public function test_cursors_cannot_cross_current_actor_parent_or_search_scope(string $change): void
    {
        $factory = app(ScenarioFactory::class);
        $actor = $factory->account();
        $other = $factory->account();
        app(ManagePlatformAdministrator::class)->grant($actor->userId);
        app(ManagePlatformAdministrator::class)->grant($other->userId, app(AccountIdentityQuery::class)->require($actor->userId));
        $kingdoms = KingdomRecoveryFixture::kingdoms(2);
        KingdomRecoveryFixture::players($kingdoms[0], 30);
        $query = app(KingdomRecoveryChoiceQuery::class);
        $cursor = $query->page($actor->userId, 'players', $kingdoms[0])['page']['nextCursor'];
        self::assertIsString($cursor);
        $this->expectException(ValidationException::class);
        $query->page($change === 'actor' ? $other->userId : $actor->userId, $change === 'kind' ? 'kingdoms' : 'players',
            $change === 'kind' ? null : ($change === 'kingdom' ? $kingdoms[1] : $kingdoms[0]),
            $change === 'search' ? 'governor' : '', $change === 'tamper' ? 'broken'.$cursor : $cursor);
    }

    public function test_deleted_boundary_and_new_inserts_do_not_shift_the_frontier_and_current_eligibility_is_rechecked(): void
    {
        $actor = app(ScenarioFactory::class)->account();
        $grant = app(ManagePlatformAdministrator::class)->grant($actor->userId);
        $kingdoms = KingdomRecoveryFixture::kingdoms(2);
        $players = KingdomRecoveryFixture::players($kingdoms[0], 61);
        $query = app(KingdomRecoveryChoiceQuery::class);
        $first = $query->page($actor->userId, 'players', $kingdoms[0]);
        DB::table('players')->where('id', $players[24])->delete();
        DB::table('players')->where('id', $players[25])->update(['canonical_player_id' => $players[0]]);
        DB::table('players')->where('id', $players[26])->update(['current_kingdom_id' => $kingdoms[1]]);
        $new = KingdomRecoveryFixture::players($kingdoms[0], 1)[0];
        $next = $query->page($actor->userId, 'players', $kingdoms[0], cursor: $first['page']['nextCursor'], selectedId: $players[25]);
        self::assertNull($next['selected']);
        self::assertSame(array_slice($players, 27, 25), array_column($next['page']['items'], 'id'));
        $last = $query->page($actor->userId, 'players', $kingdoms[0], cursor: $next['page']['nextCursor']);
        self::assertNotContains($new, array_column($last['page']['items'], 'id'));
        self::assertFalse($last['page']['hasMore']);
        DB::table('kingdoms')->where('id', $kingdoms[0])->update(['status' => 'archived']);
        self::assertNull($query->page($actor->userId, 'kingdoms', selectedId: $kingdoms[0])['selected']);
        try {
            $query->page($actor->userId, 'players', $kingdoms[0]);
            self::fail('Archived Kingdom choices must fail.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('kingdom_id', $exception->errors());
        }
        DB::table('platform_administrators')->where('id', $grant)->update(['revoked_at' => now()]);
        $this->expectException(AuthorizationException::class);
        $query->page($actor->userId, 'kingdoms');
    }

    public function test_http_requires_operator_mfa_and_recent_auth_and_returns_only_requested_scope(): void
    {
        $account = app(ScenarioFactory::class)->account();
        app(ManagePlatformAdministrator::class)->grant($account->userId);
        $kingdoms = KingdomRecoveryFixture::kingdoms(2);
        $players = KingdomRecoveryFixture::players($kingdoms[1], 31);
        $user = User::query()->findOrFail($account->userId);
        $url = '/platform/kingdom-governance-recovery/choices/players?kingdom='.$kingdoms[1].'&selected='.$players[30];
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($user)->get($url)->assertRedirect();
        $user->forceFill(['email_verified_at' => now(), 'two_factor_secret' => app(TotpService::class)->generateSecret(), 'two_factor_confirmed_at' => now()])->save();
        $this->get($url)->assertRedirect('/confirm-password');
        $this->withSession(['accounts.recent_authentication_at' => now()->timestamp])->getJson($url)
            ->assertOk()->assertJsonPath('total', 31)->assertJsonCount(25, 'page.items')->assertJsonPath('selected.id', $players[30]);
        $this->getJson('/platform/kingdom-governance-recovery/choices/unknown')->assertNotFound();
    }
}
