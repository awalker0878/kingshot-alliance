<?php

declare(strict_types=1);

namespace Tests\Contexts\Alliance\Access\Feature;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Access\Queries\AllianceRoleCatalogQuery;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class AllianceRoleCatalogV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_management_traversal_is_bounded_with_constant_queries_and_current_counts(): void
    {
        $s = $this->scenario();
        $query = app(AllianceRoleCatalogQuery::class);
        $role = Role::query()->where('alliance_id', $s['alliance']->allianceId)->where('key', 'catalog-000')->sole();
        $membership = AllianceMembership::query()->where('player_id', $s['player']->playerId)->sole();
        $membership->roles()->attach($role->id, ['alliance_id' => $s['alliance']->allianceId]);
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $page = $query->management($s['alliance']->allianceId, false, 'Catalog ');
            self::assertCount(2, DB::getQueryLog(), 'Membership counts must not issue a query for every role.');
        } finally {
            DB::disableQueryLog();
        }
        self::assertCount(25, $page->items);
        self::assertSame(1, $page->items[0]['memberCount']);
        $keys = array_column($page->items, 'key');
        // Renaming does not move a role across the immutable-key cursor.
        Role::query()->whereKey($page->items[24]['id'])->update(['name' => 'Catalog renamed']);
        while ($page->nextCursor !== null) {
            $page = $query->management($s['alliance']->allianceId, false, 'Catalog ', $page->nextCursor);
            self::assertLessThanOrEqual(25, count($page->items));
            self::assertFalse($page->isFirstPage);
            $keys = [...$keys, ...array_column($page->items, 'key')];
        }
        self::assertCount(70, $keys);
        self::assertCount(70, array_unique($keys));
        self::assertSame('catalog-069', $keys[69]);
    }

    /** @return iterable<string,array{string}> */
    public static function wrongScopes(): iterable
    {
        yield 'another Alliance' => ['alliance'];
        yield 'archive filter' => ['archived'];
        yield 'name filter' => ['search'];
        yield 'tampered cursor' => ['tampered'];
    }

    #[DataProvider('wrongScopes')]
    public function test_cursors_cannot_cross_scope_or_filter_boundaries(string $changed): void
    {
        $s = $this->scenario();
        $query = app(AllianceRoleCatalogQuery::class);
        $cursor = $query->management($s['alliance']->allianceId, false, 'Catalog ')->nextCursor;
        self::assertNotNull($cursor);
        $this->expectException(ValidationException::class);
        $query->management(
            $changed === 'alliance' ? (string) Str::ulid() : $s['alliance']->allianceId,
            $changed === 'archived',
            $changed === 'search' ? 'Other' : 'Catalog ',
            $changed === 'tampered' ? 'invalid-cursor' : $cursor,
        );
    }

    public function test_archived_options_are_excluded_and_search_metacharacters_are_literal(): void
    {
        $s = $this->scenario();
        $query = app(AllianceRoleCatalogQuery::class);
        $archived = $query->management($s['alliance']->allianceId, true, 'Catalog ');
        self::assertCount(25, $archived->items);
        self::assertNotNull($archived->items[0]['archivedAt']);
        self::assertSame([], $query->options($s['alliance']->allianceId, 'Catalog archived')->items);
        foreach (['Literal_% exact', 'Literal XX different'] as $index => $name) {
            Role::query()->create([
                'alliance_id' => $s['alliance']->allianceId,
                'key' => 'literal-'.$index,
                'name' => $name,
                'is_system' => false,
            ]);
        }
        $matches = $query->options($s['alliance']->allianceId, 'Literal_%')->items;
        self::assertCount(1, $matches);
        self::assertSame('Literal_% exact', $matches[0]['name']);
    }

    public function test_http_catalog_requires_current_role_authority_and_uses_only_the_current_alliance(): void
    {
        $s = $this->scenario();
        $this->asPlayer($s['user'], $s['player']);
        $this->getJson('/alliance/roles/options?q=Catalog')->assertOk()->assertJsonCount(25, 'items')->assertJsonPath('hasMore', true);
        $this->get('/alliance/roles?q=Catalog')->assertInertia(fn (Assert $page) => $page
            ->component('Alliance/Roles/Index')->has('rolePage.items', 25)->missing('roles'));
        $this->get('/alliance/members/bulk')->assertInertia(fn (Assert $page) => $page
            ->component('Alliance/Members/Bulk')->missing('roles'));
        $this->get('/alliance')->assertInertia(fn (Assert $page) => $page
            ->component('Alliance/Overview')->missing('membershipManagement.roleCatalog'));

        $factory = app(ScenarioFactory::class);
        $memberUser = $factory->authUser();
        $memberUser->forceFill(['email_verified_at' => now()])->save();
        $member = $factory->player((int) $memberUser->id, 59252);
        AllianceMembership::query()->create([
            'alliance_id' => $s['alliance']->allianceId,
            'player_id' => $member->playerId,
            'rank' => AllianceRank::R1,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);
        $this->asPlayer($memberUser, $member);
        $this->getJson('/alliance/roles/options')->assertForbidden();

        $otherUser = $factory->authUser();
        $otherUser->forceFill(['email_verified_at' => now()])->save();
        $otherPlayer = $factory->player((int) $otherUser->id, 59252);
        $factory->alliance($otherPlayer);
        $this->asPlayer($otherUser, $otherPlayer);
        $this->getJson('/alliance/roles/options?q=Catalog&alliance_id='.$s['alliance']->allianceId)
            ->assertOk()->assertJsonCount(0, 'items');
    }

    /** @return array{user:User,player:PlayerReference,alliance:AllianceReference} */
    private function scenario(): array
    {
        $factory = app(ScenarioFactory::class);
        $user = $factory->authUser();
        $user->forceFill(['email_verified_at' => now()])->save();
        $player = $factory->player((int) $user->id, 59252);
        $alliance = $factory->alliance($player);
        $rows = [];
        for ($index = 0; $index < 100; $index++) {
            $rows[] = [
                'id' => (string) Str::ulid(),
                'alliance_id' => $alliance->allianceId,
                'key' => sprintf('catalog-%03d', $index),
                'name' => sprintf('Catalog %s%03d', $index >= 70 ? 'archived ' : '', $index),
                'is_system' => false,
                'archived_at' => $index >= 70 ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        Role::query()->insert($rows);

        return compact('user', 'player', 'alliance');
    }

    private function asPlayer(User $user, PlayerReference $player): void
    {
        // Each actor uses a separate browser session, including its credential hash.
        session()->invalidate();
        $this->actingAs($user)->withSession([(string) config('game_world.active_player_session_key') => $player->playerId]);
    }
}
