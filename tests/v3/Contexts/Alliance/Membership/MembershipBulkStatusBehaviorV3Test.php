<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Alliance\Membership;

use App\Contexts\Alliance\Membership\Actions\BulkChangeMembershipStatus;
use App\Contexts\Alliance\Membership\Actions\PreviewMembershipStatusBulkChange;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class MembershipBulkStatusBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_status_change_previews_hierarchy_and_reports_every_membership(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $owner = $scenario->player((int) $account->id, 58001);
        $alliance = $scenario->alliance($owner);
        $ownerMembership = AllianceMembership::query()
            ->where('alliance_id', $alliance->allianceId)
            ->where('player_id', $owner->playerId)
            ->firstOrFail();
        $ready = $this->membership(
            $alliance->allianceId,
            $scenario->unclaimedPlayer(58001)->playerId,
            MembershipStatus::Active,
        );
        $complete = $this->membership(
            $alliance->allianceId,
            $scenario->unclaimedPlayer(58001)->playerId,
            MembershipStatus::Suspended,
        );
        $membershipIds = [
            (string) $ownerMembership->id,
            (string) $ready->id,
            (string) $complete->id,
        ];

        $preview = app(PreviewMembershipStatusBulkChange::class)->handle(
            $owner->playerId,
            $alliance->allianceId,
            $membershipIds,
            MembershipStatus::Suspended,
        );

        self::assertSame(1, $preview['ready']);
        self::assertSame(2, $preview['blocked']);
        self::assertSame([(string) $ready->id], $preview['readyItemIds']);
        self::assertSame(
            ['member-protected', 'ready', 'already-in-target-status'],
            array_column($preview['items'], 'code'),
        );

        $result = app(BulkChangeMembershipStatus::class)->handle(
            $owner->playerId,
            $alliance->allianceId,
            $membershipIds,
            MembershipStatus::Suspended,
        )->toArray();

        self::assertSame(1, $result['succeeded']);
        self::assertSame(1, $result['failed']);
        self::assertSame(1, $result['skipped']);
        self::assertSame([(string) $ownerMembership->id], $result['failedItemIds']);
        self::assertSame(MembershipStatus::Suspended, $ready->refresh()->status);
        self::assertSame(MembershipStatus::Active, $ownerMembership->refresh()->status);
        self::assertTrue(AuditEvent::query()
            ->where('alliance_id', $alliance->allianceId)
            ->where('event', 'membership.members.bulk_status_changed')
            ->exists());
    }

    private function membership(
        string $allianceId,
        string $playerId,
        MembershipStatus $status,
    ): AllianceMembership {
        return AllianceMembership::query()->create([
            'alliance_id' => $allianceId,
            'player_id' => $playerId,
            'status' => $status,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);
    }
}
