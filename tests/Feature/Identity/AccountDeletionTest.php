<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Authorization\Actions\AssignMembershipRole;
use App\Domain\Authorization\Enums\DefaultAllianceRole;
use App\Domain\Authorization\Models\Role;
use App\Domain\Identity\Actions\RequestAccountDeletion;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Memberships\Enums\AllianceRank;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Platform\Actions\ManagePlatformAdministrator;
use App\Domain\Platform\Actions\ProcessAccountDeletionRequests;
use App\Domain\Platform\Services\LegalHoldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_r5_player_must_transfer_leadership_before_requesting_account_deletion(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 4601]);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'deletion-r5-player',
            'current_name' => 'Deletion R5 Player',
        ]);
        $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Deletion Leader', 'deletion-leader');

        $this->expectException(ValidationException::class);
        $this->app->make(RequestAccountDeletion::class)->handle($owner);
    }

    public function test_eligible_request_unclaims_players_ends_active_membership_and_preserves_game_history(): void
    {
        $leader = User::factory()->create();
        $user = User::factory()->create(['email' => 'delete-me@example.com']);
        $kingdom = Kingdom::query()->create(['number' => 4611]);
        $leaderPlayer = Player::query()->create([
            'user_id' => $leader->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'deletion-leader-player',
            'current_name' => 'Deletion Leader',
        ]);
        $player = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'delete-me-player',
            'current_name' => 'Historical Player',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($leaderPlayer, 'Deletion History', 'deletion-history');
        $membership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $player->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);
        $eventCoordinator = Role::query()
            ->where('alliance_id', $alliance->id)
            ->where('key', DefaultAllianceRole::EventCoordinator->value)
            ->sole();
        $this->app->make(AssignMembershipRole::class)
            ->handle($alliance, $leaderPlayer, $membership->id, $eventCoordinator->id);

        $deletion = $this->app->make(RequestAccountDeletion::class)->handle($user);
        $deletion->forceFill(['eligible_at' => now()->subMinute()])->save();

        self::assertSame(1, $this->app->make(ProcessAccountDeletionRequests::class)->handle());

        $user->refresh();
        self::assertSame('Deleted User', $user->name);
        self::assertStringStartsWith('deleted+', $user->email);
        self::assertNotNull($user->anonymized_at);
        self::assertSame('processed', $deletion->refresh()->status);
        self::assertNull($player->refresh()->user_id);
        self::assertSame('Historical Player', $player->current_name);
        self::assertSame(MembershipStatus::Left, $membership->refresh()->status);
        self::assertNotNull($membership->left_at);
        self::assertDatabaseMissing('membership_roles', [
            'membership_id' => $membership->id,
            'role_id' => $eventCoordinator->id,
        ]);
        self::assertDatabaseHas('outbox_messages', [
            'alliance_id' => $alliance->id,
            'event_type' => 'membership.left',
            'aggregate_id' => $membership->id,
        ]);
        self::assertDatabaseHas('alliance_memberships', [
            'id' => $membership->id,
            'player_id' => $player->id,
            'alliance_id' => $alliance->id,
        ]);
    }

    public function test_legal_hold_blocks_processing_until_released(): void
    {
        $administrator = User::factory()->create();
        $this->app->make(ManagePlatformAdministrator::class)->grant($administrator);
        $user = User::factory()->create();
        $deletion = $this->app->make(RequestAccountDeletion::class)->handle($user);
        $deletion->forceFill(['eligible_at' => now()->subMinute()])->save();
        $hold = $this->app->make(LegalHoldService::class)->place(
            $administrator,
            'user',
            (string) $user->id,
            'Preserve account records',
        );

        self::assertSame(0, $this->app->make(ProcessAccountDeletionRequests::class)->handle());
        self::assertSame('blocked', $deletion->refresh()->status);
        self::assertNull($user->refresh()->anonymized_at);

        $this->app->make(LegalHoldService::class)->release($administrator, $hold);
        self::assertSame(1, $this->app->make(ProcessAccountDeletionRequests::class)->handle());
        self::assertNotNull($user->refresh()->anonymized_at);
    }
}
