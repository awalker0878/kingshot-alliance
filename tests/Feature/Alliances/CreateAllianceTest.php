<?php

declare(strict_types=1);

namespace Tests\Feature\Alliances;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Shared\Audit\Models\AuditEvent;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Models\Permission;
use App\Domain\Authorization\Models\Role;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Authorization\Services\AllianceRankPermissions;
use App\Contexts\Accounts\Models\User;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Memberships\Enums\AllianceRank;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Shared\Messaging\Models\OutboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CreateAllianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_alliance_creation_is_player_scoped_transactional_and_provisions_r5_authorization(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 1234, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'ks-owner-1234',
            'current_name' => 'Kingshot Owner',
        ]);

        $alliance = $this->app->make(CreateAlliance::class)->handle(
            owner: $ownerPlayer,
            name: 'Kingshot One',
            slug: 'kingshot-one',
            language: 'en',
            timezone: 'America/Toronto',
        );

        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('player_id', $ownerPlayer->id)
            ->sole();

        self::assertSame(MembershipStatus::Active, $membership->status);
        self::assertNotNull($membership->joined_at);
        self::assertSame(3, Role::query()->where('alliance_id', $alliance->id)->count());
        self::assertSame(count(PermissionKey::cases()), Permission::query()->count());
        self::assertSame(AllianceRank::R5, $membership->rank);
        self::assertFalse($membership->roles()->exists());
        self::assertInstanceOf(Kingdom::class, $alliance->kingdom);
        self::assertSame($kingdom->id, $alliance->kingdom_id);
        self::assertSame(1234, $alliance->kingdom->number);
        self::assertSame(1, Kingdom::query()->count());

        $authorization = $this->app->make(AllianceAuthorization::class);
        $rankPermissions = $this->app->make(AllianceRankPermissions::class);
        foreach ($rankPermissions->for(AllianceRank::R5) as $permission) {
            self::assertTrue($authorization->allows($ownerPlayer, $alliance, $permission));
        }
        self::assertFalse($authorization->allows($ownerPlayer, $alliance, PermissionKey::EventKingdomManage));
        self::assertFalse($authorization->allows($ownerPlayer, $alliance, PermissionKey::EventTypeManage));

        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $alliance->id,
            'actor_user_id' => null,
            'actor_player_id' => $ownerPlayer->id,
            'event' => 'alliance.created',
            'subject_type' => $alliance::class,
            'subject_id' => $alliance->id,
        ]);

        $message = OutboxMessage::query()->where('event_type', 'alliance.created')->sole();
        self::assertSame($ownerPlayer->id, $message->payload['owner_player_id'] ?? null);
        self::assertSame($alliance->id, $message->payload['alliance_id'] ?? null);
        self::assertSame(1, AuditEvent::query()->count());
        self::assertSame(1, OutboxMessage::query()->count());
    }

    public function test_one_user_can_own_multiple_players_that_each_hold_independent_alliance_authority(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 2001, 'status' => 'active']);
        $firstPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'multi-player-a',
            'current_name' => 'Alpha',
        ]);
        $secondPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'multi-player-b',
            'current_name' => 'Bravo',
        ]);
        $action = $this->app->make(CreateAlliance::class);

        $first = $action->handle($firstPlayer, 'First Alliance', 'first-alliance');
        $second = $action->handle($secondPlayer, 'Second Alliance', 'second-alliance');

        self::assertNotSame($first->id, $second->id);
        self::assertSame(2, AllianceMembership::query()
            ->whereIn('player_id', [$firstPlayer->id, $secondPlayer->id])
            ->where('status', MembershipStatus::Active->value)
            ->count());
        self::assertSame(2, Player::query()->where('user_id', $owner->id)->count());
    }

    public function test_same_player_cannot_create_or_join_a_second_active_alliance(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 2002, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'single-authority-player',
            'current_name' => 'Single Authority',
        ]);
        $action = $this->app->make(CreateAlliance::class);
        $action->handle($ownerPlayer, 'First Alliance', 'single-authority-first');

        $this->expectException(ValidationException::class);
        $action->handle($ownerPlayer, 'Second Alliance', 'single-authority-second');
    }

    public function test_multiple_alliances_can_share_one_canonical_kingdom_through_distinct_players(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 42, 'status' => 'active']);
        $firstPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'kingdom-42-a',
            'current_name' => 'Forty Two Alpha',
        ]);
        $secondPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'kingdom-42-b',
            'current_name' => 'Forty Two Bravo',
        ]);
        $action = $this->app->make(CreateAlliance::class);

        $first = $action->handle($firstPlayer, 'First Kingdom Alliance', 'first-kingdom-alliance');
        $second = $action->handle($secondPlayer, 'Second Kingdom Alliance', 'second-kingdom-alliance');

        self::assertSame($kingdom->id, $first->kingdom_id);
        self::assertSame($first->kingdom_id, $second->kingdom_id);
        self::assertSame(42, $first->kingdom?->number);
        self::assertSame(1, Kingdom::query()->where('number', 42)->count());
    }
    public function test_unclaimed_player_cannot_create_an_alliance(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 2003, 'status' => 'active']);
        $player = Player::query()->create([
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'unclaimed-alliance-owner',
            'current_name' => 'Unclaimed Owner',
        ]);

        $this->expectException(ValidationException::class);
        $this->app->make(CreateAlliance::class)->handle($player, 'Invalid Alliance', 'invalid-unclaimed-alliance');
    }

}
