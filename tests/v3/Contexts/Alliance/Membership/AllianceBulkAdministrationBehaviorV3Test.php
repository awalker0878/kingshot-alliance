<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Alliance\Membership;

use App\Contexts\Alliance\Membership\Actions\BulkUpdateAllianceRank;
use App\Contexts\Alliance\Membership\Actions\PreviewBulkAllianceRankChange;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class AllianceBulkAdministrationBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_rank_preview_protects_self_and_updates_only_ready_memberships(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $owner = $scenario->player((int) $account->id, 59301);
        $alliance = $scenario->alliance($owner);
        $ownerMembership = AllianceMembership::query()
            ->where('alliance_id', $alliance->allianceId)
            ->where('player_id', $owner->playerId)
            ->firstOrFail();
        $ready = $this->membership(
            $alliance->allianceId,
            $scenario->unclaimedPlayer(59301)->playerId,
            AllianceRank::R1,
        );
        $alreadySet = $this->membership(
            $alliance->allianceId,
            $scenario->unclaimedPlayer(59301)->playerId,
            AllianceRank::R3,
        );
        $ids = [(string) $ownerMembership->id, (string) $ready->id, (string) $alreadySet->id];

        $preview = app(PreviewBulkAllianceRankChange::class)->handle(
            $alliance->allianceId,
            $owner->playerId,
            $ids,
            AllianceRank::R3,
        );

        self::assertSame(1, $preview['ready']);
        self::assertSame(1, $preview['blocked']);
        self::assertSame(
            ['self_rank_change_blocked', 'ready', 'already_set'],
            array_column($preview['items'], 'code'),
        );

        $result = app(BulkUpdateAllianceRank::class)->handle(
            $alliance->allianceId,
            $owner->playerId,
            $ids,
            AllianceRank::R3,
        );

        self::assertSame(1, $result['succeeded']);
        self::assertSame(0, $result['failed']);
        self::assertSame(2, $result['skipped']);
        self::assertSame(AllianceRank::R3, $ready->refresh()->rank);
        self::assertSame(AllianceRank::R5, $ownerMembership->refresh()->rank);
    }

    public function test_bulk_rank_administration_rejects_r5_and_more_than_fifty_memberships(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $owner = $scenario->player((int) $account->id, 59302);
        $alliance = $scenario->alliance($owner);
        $membership = $this->membership(
            $alliance->allianceId,
            $scenario->unclaimedPlayer(59302)->playerId,
            AllianceRank::R1,
        );

        try {
            app(PreviewBulkAllianceRankChange::class)->handle(
                $alliance->allianceId,
                $owner->playerId,
                [(string) $membership->id],
                AllianceRank::R5,
            );
            self::fail('Expected bulk R5 assignment to require leadership transfer.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('rank', $exception->errors());
        }

        $tooMany = array_map(static fn (int $index): string => 'membership-'.$index, range(1, 51));
        try {
            app(PreviewBulkAllianceRankChange::class)->handle(
                $alliance->allianceId,
                $owner->playerId,
                $tooMany,
                AllianceRank::R2,
            );
            self::fail('Expected the 50-membership bulk bound to be enforced.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('membership_ids', $exception->errors());
        }
    }

    private function membership(string $allianceId, string $playerId, AllianceRank $rank): AllianceMembership
    {
        return AllianceMembership::query()->create([
            'alliance_id' => $allianceId,
            'player_id' => $playerId,
            'status' => MembershipStatus::Active,
            'rank' => $rank,
            'joined_at' => now(),
        ]);
    }
}
