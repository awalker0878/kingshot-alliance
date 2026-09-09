<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Alliance\Recruitment;

use App\Contexts\Alliance\Access\Actions\AssignMembershipRole;
use App\Contexts\Alliance\Access\Actions\CreateAllianceRole;
use App\Contexts\Alliance\Access\Actions\RemoveMembershipRole;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Recruitment\Actions\BulkChangeRecruitmentStage;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class RecruitmentBulkAuthorityRevocationV3Test extends TestCase
{
    use DatabaseMigrations;

    /** @return iterable<string,array{bool}> */
    public static function revocationPoints(): iterable
    {
        yield 'after preview before first write' => [false];
        yield 'after first committed candidate' => [true];
    }

    #[DataProvider('revocationPoints')]
    public function test_current_revocation_preserves_every_outcome_and_selective_retry(bool $afterFirst): void
    {
        $factory = app(ScenarioFactory::class);
        $owner = $factory->player($factory->account()->userId, 59289);
        $alliance = $factory->alliance($owner);
        $manager = $factory->player($factory->account()->userId, 59289);
        $membership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->allianceId, 'player_id' => $manager->playerId,
            'rank' => AllianceRank::R3, 'status' => MembershipStatus::Active, 'joined_at' => now(),
        ]);
        $roleId = app(CreateAllianceRole::class)->handle($alliance->allianceId, $owner->playerId, 'Current bulk recruiter', [AlliancePermission::RecruitmentManage]);
        app(AssignMembershipRole::class)->handle($alliance->allianceId, $owner->playerId, (string) $membership->id, $roleId);
        $ids = [];
        for ($index = 0; $index < 3; $index++) {
            $candidate = RecruitmentCandidate::query()->create([
                'alliance_id' => $alliance->allianceId, 'full_name' => 'Bulk candidate '.$index,
                'email' => 'bulk-'.$index.'@example.test', 'stage' => RecruitmentStage::New, 'submitted_at' => now(),
            ]);
            $ids[] = (string) $candidate->id;
        }
        $revoke = static fn () => app(RemoveMembershipRole::class)->handle($alliance->allianceId, $owner->playerId, (string) $membership->id, $roleId);
        $revoked = false;
        $readyToRevoke = false;
        DB::listen(static function (QueryExecuted $query) use ($afterFirst, $revoke, &$revoked, &$readyToRevoke): void {
            if ($revoked) {
                return;
            }
            if ($afterFirst) {
                if (str_starts_with($query->sql, 'insert into "outbox_messages"') && in_array('recruitment.candidate.stage_changed', $query->bindings, true)) {
                    $readyToRevoke = true;
                }
            } elseif (str_starts_with($query->sql, 'select * from "recruitment_candidates"') && ! str_contains($query->sql, 'for update')) {
                $revoked = true;
                $revoke();
            }
        });
        Event::listen(TransactionCommitted::class, static function (TransactionCommitted $event) use ($afterFirst, $revoke, &$revoked, &$readyToRevoke): void {
            if ($afterFirst && ! $revoked && $readyToRevoke && $event->connection->transactionLevel() === 0) {
                $revoked = true;
                $revoke();
            }
        });
        $result = app(BulkChangeRecruitmentStage::class)->handle($manager->playerId, $alliance->allianceId, $ids, RecruitmentStage::Screening)->toArray();
        self::assertTrue($revoked);
        self::assertSame($afterFirst ? 1 : 0, $result['succeeded']);
        self::assertSame($afterFirst ? 2 : 3, $result['failed']);
        self::assertSame(0, $result['skipped']);
        self::assertSame($afterFirst ? array_slice($ids, 1) : $ids, $result['failedItemIds']);
        foreach ($result['items'] as $index => $item) {
            if ($afterFirst && $index === 0) {
                self::assertSame('stage-updated', $item['code']);
            } else {
                self::assertSame('permission-denied', $item['code']);
                self::assertSame(RecruitmentStage::New, RecruitmentCandidate::query()->findOrFail($item['itemId'])->stage);
            }
        }
        $receipt = AuditEvent::query()->where('event', 'recruitment.candidates.bulk_stage_changed')->sole();
        self::assertSame($ids, $receipt->metadata['candidate_ids']);
        self::assertSame($result['succeeded'], $receipt->metadata['succeeded']);
        self::assertSame($result['failed'], $receipt->metadata['failed']);
        self::assertSame($afterFirst ? 1 : 0, DB::table('recruitment_stage_history')->count());
        app(AssignMembershipRole::class)->handle($alliance->allianceId, $owner->playerId, (string) $membership->id, $roleId);
        $retry = app(BulkChangeRecruitmentStage::class)->handle($manager->playerId, $alliance->allianceId, $result['failedItemIds'], RecruitmentStage::Screening)->toArray();
        self::assertSame($afterFirst ? 2 : 3, $retry['succeeded']);
        self::assertSame(0, $retry['failed']);
        self::assertSame(3, DB::table('recruitment_stage_history')->count());
        self::assertSame(3, RecruitmentCandidate::query()->where('stage', RecruitmentStage::Screening)->count());
        self::assertSame(2, AuditEvent::query()->where('event', 'recruitment.candidates.bulk_stage_changed')->count());
    }
}
