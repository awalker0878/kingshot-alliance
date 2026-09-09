<?php

declare(strict_types=1);

namespace Tests\Feature\ReadModels\AllianceGovernance;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Access\Actions\AssignMembershipRole;
use App\Contexts\Alliance\Access\Actions\CreateAllianceRole;
use App\Contexts\Alliance\Access\Actions\RemoveMembershipRole;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\ReadModels\AllianceGovernance\Queries\MembershipGovernanceHistoryQuery;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class MembershipHistoryPaginationV3Test extends TestCase
{
    use RefreshDatabase;

    /** @return iterable<string,array{string}> */
    public static function targetKeys(): iterable
    {
        foreach (['player_id', 'target_player_id', 'owner_player_id', 'previous_r5_player_id', 'new_r5_player_id'] as $key) {
            yield $key => [$key];
        }
    }

    #[DataProvider('targetKeys')]
    public function test_supported_target_facts_are_selected_before_unrelated_history(string $key): void
    {
        [$owner, $target, $allianceId] = $this->fixture();
        $expected = $this->events($allianceId, $owner->playerId, $target->playerId, 1, $key);
        $this->events($allianceId, $owner->playerId, $owner->playerId, 501, $key);
        $this->events($allianceId, $owner->playerId, $target->playerId, 1, $key, 'alliance.settings_changed');
        $page = app(MembershipGovernanceHistoryQuery::class)->forPlayer($owner->playerId, $allianceId, $target->playerId);
        self::assertSame($expected, array_column($page->items, 'id'));
        self::assertNull($page->nextCursor);
    }

    public function test_tied_history_is_bounded_and_continues_after_a_deleted_boundary_and_newer_insert(): void
    {
        [$owner, $target, $allianceId] = $this->fixture();
        $expected = array_reverse($this->events($allianceId, $owner->playerId, $target->playerId, 101));
        $this->events($allianceId, $owner->playerId, $owner->playerId, 600);
        $hydrated = 0;
        $queries = 0;
        AuditEvent::retrieved(static function () use (&$hydrated): void {
            $hydrated++;
        });
        DB::listen(static function (QueryExecuted $event) use (&$queries): void {
            $queries++;
        });
        $query = app(MembershipGovernanceHistoryQuery::class);
        $first = $query->forPlayer($owner->playerId, $allianceId, $target->playerId);
        self::assertCount(50, $first->items);
        self::assertSame(array_slice($expected, 0, 50), array_column($first->items, 'id'));
        self::assertLessThanOrEqual(51, $hydrated);
        self::assertLessThanOrEqual(25, $queries);
        self::assertNotNull($first->nextCursor);
        DB::table('audit_events')->where('id', $first->items[49]['id'])->delete();
        $this->events($allianceId, $owner->playerId, $target->playerId, 1);
        $second = $query->forPlayer($owner->playerId, $allianceId, $target->playerId, $first->nextCursor);
        self::assertSame(array_slice($expected, 50, 50), array_column($second->items, 'id'));
        self::assertFalse($second->isFirstPage);
        $third = $query->forPlayer($owner->playerId, $allianceId, $target->playerId, $second->nextCursor);
        self::assertSame(array_slice($expected, 100), array_column($third->items, 'id'));
        self::assertNull($third->nextCursor);
    }

    /** @return iterable<string,array{string}> */
    public static function invalidScopes(): iterable
    {
        yield 'another member' => ['member'];
        yield 'another alliance' => ['alliance'];
        yield 'tampered token' => ['tampered'];
    }

    #[DataProvider('invalidScopes')]
    public function test_cursor_cannot_be_reused_outside_its_member_and_alliance(string $kind): void
    {
        [$owner, $target, $allianceId] = $this->fixture();
        $this->events($allianceId, $owner->playerId, $target->playerId, 2);
        $query = app(MembershipGovernanceHistoryQuery::class);
        $cursor = $query->forPlayer($owner->playerId, $allianceId, $target->playerId, limit: 1)->nextCursor;
        self::assertNotNull($cursor);
        if ($kind === 'member') {
            $target = $owner;
        } elseif ($kind === 'alliance') {
            $cursor = app(ScopedCursorCodec::class)->encode('membership-governance|foreign|'.$target->playerId, ['at' => now()->toDateTimeString(), 'id' => (string) Str::ulid()]);
        } else {
            $cursor .= 'tampered';
        }
        $this->expectException(ValidationException::class);
        $query->forPlayer($owner->playerId, $allianceId, $target->playerId, $cursor);
    }

    public function test_role_revocation_is_checked_again_by_an_existing_query_and_http_continuation(): void
    {
        [$owner, $target, $allianceId] = $this->fixture();
        $factory = new ScenarioFactory;
        $user = $factory->authUser();
        $user->forceFill(['email_verified_at' => now()])->save();
        $viewer = $factory->player((int) $user->id, 59295);
        $membership = AllianceMembership::query()->create([
            'alliance_id' => $allianceId, 'player_id' => $viewer->playerId, 'status' => MembershipStatus::Active, 'rank' => AllianceRank::R3, 'joined_at' => now(),
        ]);
        $role = app(CreateAllianceRole::class)->handle($allianceId, $owner->playerId, 'History reader', [AlliancePermission::RoleManage]);
        app(AssignMembershipRole::class)->handle($allianceId, $owner->playerId, (string) $membership->id, $role);
        $this->events($allianceId, $owner->playerId, $target->playerId, 2);
        $query = app(MembershipGovernanceHistoryQuery::class);
        $cursor = $query->forPlayer($viewer->playerId, $allianceId, $target->playerId, limit: 1)->nextCursor;
        self::assertNotNull($cursor);
        app(RemoveMembershipRole::class)->handle($allianceId, $owner->playerId, (string) $membership->id, $role);
        try {
            $query->forPlayer($viewer->playerId, $allianceId, $target->playerId, $cursor);
            self::fail('A continuation must not retain revoked authority.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }
        $this->actingAs($user)->withSession([(string) config('game_world.active_player_session_key') => $viewer->playerId])
            ->get(route('alliance.members.history', ['player' => $target->playerId, 'cursor' => $cursor]))->assertForbidden();
    }

    public function test_http_page_and_profile_preview_use_the_same_complete_relevant_history(): void
    {
        [$owner, $target, $allianceId, $user, $rosterId] = $this->fixture();
        $expected = array_reverse($this->events($allianceId, $owner->playerId, $target->playerId, 51));
        $this->events($allianceId, $owner->playerId, $owner->playerId, 501);
        $this->actingAs($user)->withSession([(string) config('game_world.active_player_session_key') => $owner->playerId]);
        $cursor = null;
        $this->get(route('alliance.members.history', $target->playerId))->assertOk()
            ->assertInertia(static function (Assert $page) use ($expected, &$cursor): void {
                $page->component('Alliance/Members/History')->has('historyPage.items', 50)->where('historyPage.items.0.id', $expected[0])->where('historyPage.hasMore', true)->missing('history')
                    ->where('historyPage.nextCursor', static function ($value) use (&$cursor): bool {
                        $cursor = $value;

                        return is_string($value);
                    });
            });
        self::assertIsString($cursor);
        $this->get(route('alliance.members.history', ['player' => $target->playerId, 'cursor' => $cursor]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('historyPage.items', 1)->where('historyPage.items.0.id', $expected[50])->where('historyPage.hasMore', false));
        $this->get(route('alliance.roster.history', $rosterId))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('capabilityProfile.membershipGovernance.history', 12)->where('capabilityProfile.membershipGovernance.history.0.id', $expected[0]));
        $this->getJson(route('alliance.members.history', ['player' => $target->playerId, 'cursor' => ['bad']]))->assertUnprocessable()->assertJsonValidationErrors('cursor');
    }

    public function test_member_page_does_not_disclose_a_player_without_any_alliance_relationship(): void
    {
        [$owner, , , $user] = $this->fixture();
        $foreign = (new ScenarioFactory)->unclaimedPlayer(59296);
        $this->actingAs($user)->withSession([(string) config('game_world.active_player_session_key') => $owner->playerId])
            ->get(route('alliance.members.history', $foreign->playerId))->assertNotFound();
    }

    /** @return array{PlayerReference,PlayerReference,string,User,string} */
    private function fixture(): array
    {
        $factory = new ScenarioFactory;
        $user = $factory->authUser();
        $user->forceFill(['email_verified_at' => now()])->save();
        $owner = $factory->player((int) $user->id, 59295);
        $alliance = $factory->alliance($owner);
        $target = $factory->unclaimedPlayer(59295);
        $roster = $factory->roster($owner, $alliance, $target);

        return [$owner, $target, $alliance->allianceId, $user, $roster->rosterEntryId];
    }

    /** @return list<string> */
    private function events(string $allianceId, string $actorId, string $targetId, int $count, string $key = 'player_id', string $event = 'membership.rank_changed'): array
    {
        $ids = [];
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $id = (string) Str::ulid();
            $ids[] = $id;
            $rows[] = [
                'id' => $id, 'alliance_id' => $allianceId, 'actor_player_id' => $actorId, 'event' => $event,
                'subject_type' => Alliance::class, 'subject_id' => $allianceId,
                'metadata' => json_encode([$key => $targetId, 'new_rank' => 'r3'], JSON_THROW_ON_ERROR),
                'created_at' => now()->subDay()->startOfDay()->toDateTimeString(),
            ];
        }
        DB::table('audit_events')->insert($rows);

        return $ids;
    }
}
