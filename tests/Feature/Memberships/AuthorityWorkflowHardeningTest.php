<?php

declare(strict_types=1);

namespace Tests\Feature\Memberships;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Enums\AllianceStatus;
use App\Contexts\Accounts\Models\User;
use App\Domain\Kingdoms\Actions\SaveRosterEntry;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Memberships\Actions\AcceptInvitation;
use App\Domain\Memberships\Actions\CreateInvitation;
use App\Domain\Memberships\Actions\TransferAllianceLeadership;
use App\Domain\Memberships\Enums\AllianceRank;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Memberships\Models\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AuthorityWorkflowHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_active_in_another_alliance_cannot_receive_a_second_alliance_invitation(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $member = User::factory()->create(['email' => 'already-member@example.com']);
        $kingdom = Kingdom::query()->create(['number' => 7710, 'status' => 'active']);

        $firstOwnerPlayer = $this->player($firstOwner, $kingdom, 'hardening-owner-a', 'First Owner');
        $secondOwnerPlayer = $this->player($secondOwner, $kingdom, 'hardening-owner-b', 'Second Owner');
        $memberPlayer = $this->player($member, $kingdom, 'hardening-member', 'Existing Member');

        $createAlliance = $this->app->make(CreateAlliance::class);
        $firstAlliance = $createAlliance->handle($firstOwnerPlayer, 'First Alliance', 'hardening-first');
        $secondAlliance = $createAlliance->handle($secondOwnerPlayer, 'Second Alliance', 'hardening-second');

        $saveRoster = $this->app->make(SaveRosterEntry::class);
        $saveRoster->handle($firstAlliance, $firstOwnerPlayer, [
            'name' => $memberPlayer->current_name,
            'game_player_id' => $memberPlayer->game_player_id,
        ]);

        $issued = $this->app->make(CreateInvitation::class)
            ->handle($firstAlliance, $firstOwnerPlayer, $memberPlayer, $member->email);
        $this->app->make(AcceptInvitation::class)->handle($member, $issued->token);

        $saveRoster->handle($secondAlliance, $secondOwnerPlayer, [
            'name' => $memberPlayer->current_name,
            'game_player_id' => $memberPlayer->game_player_id,
        ]);

        try {
            $this->app->make(CreateInvitation::class)
                ->handle($secondAlliance, $secondOwnerPlayer, $memberPlayer, $member->email);
            self::fail('A Player already active in another Alliance must not receive a second Alliance invitation.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('player_id', $exception->errors());
        }

        self::assertFalse(Invitation::query()
            ->where('alliance_id', $secondAlliance->id)
            ->where('player_id', $memberPlayer->id)
            ->exists());
    }

    public function test_leadership_cannot_be_transferred_after_alliance_is_suspended(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'next-r5@example.com']);
        $kingdom = Kingdom::query()->create(['number' => 7711, 'status' => 'active']);
        $ownerPlayer = $this->player($owner, $kingdom, 'hardening-r5-owner', 'Current R5');
        $memberPlayer = $this->player($member, $kingdom, 'hardening-next-r5', 'Next R5');

        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($ownerPlayer, 'Leadership Alliance', 'hardening-leadership');
        $this->app->make(SaveRosterEntry::class)->handle($alliance, $ownerPlayer, [
            'name' => $memberPlayer->current_name,
            'game_player_id' => $memberPlayer->game_player_id,
        ]);
        $issued = $this->app->make(CreateInvitation::class)
            ->handle($alliance, $ownerPlayer, $memberPlayer, $member->email);
        $this->app->make(AcceptInvitation::class)->handle($member, $issued->token);

        $alliance->forceFill(['status' => AllianceStatus::Suspended])->save();

        try {
            $this->app->make(TransferAllianceLeadership::class)
                ->handle($alliance, $ownerPlayer, (string) $memberPlayer->id);
            self::fail('Leadership transfer must fail once the Alliance is suspended.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('alliance', $exception->errors());
        }

        self::assertSame(
            AllianceRank::R5,
            AllianceMembership::query()
                ->where('alliance_id', $alliance->id)
                ->where('player_id', $ownerPlayer->id)
                ->sole()
                ->rank,
        );
        self::assertSame(
            AllianceRank::R1,
            AllianceMembership::query()
                ->where('alliance_id', $alliance->id)
                ->where('player_id', $memberPlayer->id)
                ->sole()
                ->rank,
        );
    }

    private function player(User $user, Kingdom $kingdom, string $gamePlayerId, string $name): Player
    {
        return Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => $gamePlayerId,
            'current_name' => $name,
        ]);
    }
}
