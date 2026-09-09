<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Alliance\Membership;

use App\Contexts\Alliance\Membership\Actions\BulkChangeMembershipStatus;
use App\Contexts\Alliance\Membership\Actions\PreviewMembershipStatusBulkChange;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class MembershipBulkStatusBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    /** @return iterable<string,array{bool,int}> */
    public static function invalidSelections(): iterable
    {
        foreach ([false, true] as $execute) {
            yield ($execute ? 'execute ' : 'preview ').'empty' => [$execute, 0];
            yield ($execute ? 'execute ' : 'preview ').'oversized' => [$execute, 51];
        }
    }

    #[DataProvider('invalidSelections')]
    public function test_direct_owner_rejects_invalid_selection_before_scope_queries(bool $execute, int $count): void
    {
        $ids = [];
        for ($index = 0; $index < $count; $index++) {
            $ids[] = (string) Str::ulid();
        }
        $queried = false;
        DB::listen(static function (QueryExecuted $query) use (&$queried): void {
            if (str_contains($query->sql, '"alliances"') || str_contains($query->sql, '"alliance_memberships"') || str_contains($query->sql, '"audit_events"')) {
                $queried = true;
            }
        });
        try {
            $action = $execute ? app(BulkChangeMembershipStatus::class) : app(PreviewMembershipStatusBulkChange::class);
            $action->handle((string) Str::ulid(), (string) Str::ulid(), $ids, MembershipStatus::Suspended);
            self::fail('Direct owner callers must share the bounded selection contract.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('membership_ids', $exception->errors());
        }
        self::assertFalse($queried);
    }

    public function test_exactly_fifty_distinct_selections_remain_supported(): void
    {
        $factory = app(ScenarioFactory::class);
        $owner = $factory->player($factory->account()->userId, 58001);
        $alliance = $factory->alliance($owner);
        $ids = [];
        for ($index = 0; $index < 50; $index++) {
            $ids[] = (string) Str::ulid();
        }
        $preview = app(PreviewMembershipStatusBulkChange::class)->handle($owner->playerId, $alliance->allianceId, $ids, MembershipStatus::Suspended);
        self::assertCount(50, $preview['items']);
        self::assertSame($ids, array_column($preview['items'], 'itemId'));
        self::assertSame(50, $preview['blocked']);
    }

    public function test_duplicate_selections_execute_once_and_produce_a_canonical_receipt(): void
    {
        $factory = app(ScenarioFactory::class);
        $owner = $factory->player($factory->account()->userId, 58001);
        $alliance = $factory->alliance($owner);
        $membership = $this->membership($alliance->allianceId, $factory->unclaimedPlayer(58001)->playerId, MembershipStatus::Active);
        $result = app(BulkChangeMembershipStatus::class)->handle($owner->playerId, $alliance->allianceId, array_fill(0, 60, (string) $membership->id), MembershipStatus::Suspended)->toArray();
        self::assertSame(1, $result['succeeded']);
        self::assertSame(0, $result['failed']);
        self::assertSame(0, $result['skipped']);
        self::assertSame(MembershipStatus::Suspended, $membership->fresh()?->status);
        $receipt = AuditEvent::query()->where('event', 'membership.members.bulk_status_changed')->sole();
        self::assertSame([(string) $membership->id], $receipt->metadata['membership_ids']);
        self::assertSame(1, DB::table('outbox_messages')->where('event_type', 'member.updated')->where('aggregate_id', $membership->id)->count());
    }

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
