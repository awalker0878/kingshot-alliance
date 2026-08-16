<?php

declare(strict_types=1);

namespace Tests\Feature\Alliance\Membership\v2;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Enums\AllianceStatus;
use App\Contexts\Alliance\Membership\Actions\AcceptInvitation;
use App\Contexts\Alliance\Membership\Actions\CreateInvitation;
use App\Contexts\Alliance\Membership\Actions\LeaveAlliance;
use App\Contexts\Alliance\Membership\Actions\TransferAllianceLeadership;
use App\Contexts\Alliance\Membership\Actions\UpdateAllianceRank;
use App\Contexts\Alliance\Membership\Actions\UpdateMembershipStatus;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Intelligence\Roster\Actions\SaveRosterEntry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\V2\ScenarioFactory;
use Tests\TestCase;

final class MembershipAuthorityV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_r4_can_manage_lower_rank_members_but_never_r5(): void
    {
        $scenario = (new ScenarioFactory)->alliance(4530, 'R5 Owner', 'Hierarchy V2', 'hierarchy-v2-4530');
        $r4User = User::factory()->create(['email' => 'r4-v2@example.com']);
        $memberUser = User::factory()->create(['email' => 'member-v2-4530@example.com']);

        $r4Entry = app(SaveRosterEntry::class)->handle($scenario['alliance'], $scenario['player'], [
            'name' => 'R4 V2',
            'game_player_id' => 'r4-v2-4530',
        ]);
        $r4Invite = app(CreateInvitation::class)->handle($scenario['alliance'], $scenario['player'], $r4Entry->player, $r4User->email);
        $r4Membership = app(AcceptInvitation::class)->handle($r4User, $r4Invite->token);

        $memberEntry = app(SaveRosterEntry::class)->handle($scenario['alliance'], $scenario['player'], [
            'name' => 'Member V2',
            'game_player_id' => 'member-v2-4530',
        ]);
        $memberInvite = app(CreateInvitation::class)->handle($scenario['alliance'], $scenario['player'], $memberEntry->player, $memberUser->email);
        $memberMembership = app(AcceptInvitation::class)->handle($memberUser, $memberInvite->token);

        app(UpdateAllianceRank::class)->handle($scenario['alliance'], $scenario['player'], $r4Membership->id, AllianceRank::R4);
        $updated = app(UpdateMembershipStatus::class)->handle(
            $scenario['alliance'],
            $r4Entry->player,
            $memberMembership->id,
            MembershipStatus::Suspended,
        );
        self::assertSame(MembershipStatus::Suspended, $updated->status);

        $ownerMembership = AllianceMembership::query()
            ->where('alliance_id', $scenario['alliance']->id)
            ->where('player_id', $scenario['player']->id)
            ->sole();

        $this->expectException(AuthorizationException::class);
        app(UpdateMembershipStatus::class)->handle(
            $scenario['alliance'],
            $r4Entry->player,
            $ownerMembership->id,
            MembershipStatus::Suspended,
        );
    }

    public function test_r5_must_transfer_leadership_player_to_player_before_leaving(): void
    {
        $scenario = (new ScenarioFactory)->alliance(4531, 'Current R5', 'Leadership V2', 'leadership-v2-4531');
        $nextUser = User::factory()->create(['email' => 'next-r5-v2@example.com']);
        $nextEntry = app(SaveRosterEntry::class)->handle($scenario['alliance'], $scenario['player'], [
            'name' => 'Next R5 V2',
            'game_player_id' => 'next-r5-v2-4531',
        ]);
        $nextInvite = app(CreateInvitation::class)->handle($scenario['alliance'], $scenario['player'], $nextEntry->player, $nextUser->email);
        $nextMembership = app(AcceptInvitation::class)->handle($nextUser, $nextInvite->token);

        try {
            app(LeaveAlliance::class)->handle($scenario['alliance'], $scenario['player']);
            self::fail('The active R5 must transfer leadership before leaving.');
        } catch (ValidationException) {
            self::assertSame(AllianceRank::R5, AllianceMembership::query()
                ->where('alliance_id', $scenario['alliance']->id)
                ->where('player_id', $scenario['player']->id)
                ->sole()
                ->rank);
        }

        app(TransferAllianceLeadership::class)->handle(
            $scenario['alliance'],
            $scenario['player'],
            (string) $nextEntry->player_id,
        );

        $formerR5 = AllianceMembership::query()
            ->where('alliance_id', $scenario['alliance']->id)
            ->where('player_id', $scenario['player']->id)
            ->sole();
        self::assertSame(AllianceRank::R4, $formerR5->rank);
        self::assertSame(AllianceRank::R5, $nextMembership->refresh()->rank);
        self::assertSame(1, AllianceMembership::query()
            ->where('alliance_id', $scenario['alliance']->id)
            ->where('status', MembershipStatus::Active->value)
            ->where('rank', AllianceRank::R5->value)
            ->count());

        $left = app(LeaveAlliance::class)->handle($scenario['alliance'], $scenario['player']);
        self::assertSame(MembershipStatus::Left, $left->status);
    }

    public function test_suspended_alliance_blocks_leadership_transfer_without_mutating_ranks(): void
    {
        $scenario = (new ScenarioFactory)->alliance(4532, 'Suspended R5', 'Suspended Leadership V2', 'suspended-leadership-v2-4532');
        $nextUser = User::factory()->create(['email' => 'suspended-next-v2@example.com']);
        $nextEntry = app(SaveRosterEntry::class)->handle($scenario['alliance'], $scenario['player'], [
            'name' => 'Suspended Next V2',
            'game_player_id' => 'suspended-next-v2-4532',
        ]);
        $invite = app(CreateInvitation::class)->handle($scenario['alliance'], $scenario['player'], $nextEntry->player, $nextUser->email);
        $nextMembership = app(AcceptInvitation::class)->handle($nextUser, $invite->token);
        $scenario['alliance']->forceFill(['status' => AllianceStatus::Suspended])->save();

        try {
            app(TransferAllianceLeadership::class)->handle(
                $scenario['alliance'],
                $scenario['player'],
                (string) $nextEntry->player_id,
            );
            self::fail('Leadership transfer must fail for a suspended Alliance.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('alliance', $exception->errors());
        }

        self::assertSame(AllianceRank::R5, AllianceMembership::query()
            ->where('alliance_id', $scenario['alliance']->id)
            ->where('player_id', $scenario['player']->id)
            ->sole()
            ->rank);
        self::assertSame(AllianceRank::R1, $nextMembership->refresh()->rank);
    }
}
