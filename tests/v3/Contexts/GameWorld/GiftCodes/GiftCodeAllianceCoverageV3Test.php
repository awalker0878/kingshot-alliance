<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\GiftCodes;

use App\Contexts\Alliance\Access\Actions\AssignMembershipRole;
use App\Contexts\Alliance\Access\Actions\RemoveMembershipRole;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Enums\DefaultAllianceRole;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceRankPermissions;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemption;
use App\ReadModels\GiftCodes\Queries\GiftCodeAllianceCoverageQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class GiftCodeAllianceCoverageV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_coverage_is_an_explicit_specialist_role_with_aggregate_only_output_and_revocation(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $ownerAccount = $scenarios->account();
        $memberAccount = $scenarios->account();
        $owner = $scenarios->player($ownerAccount->userId, 2301, 'GCW-COVERAGE-OWNER');
        $member = $scenarios->player($memberAccount->userId, 2301, 'GCW-COVERAGE-MEMBER');
        $alliance = $scenarios->alliance($owner);

        $memberMembership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->allianceId,
            'player_id' => $member->playerId,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R4,
            'joined_at' => now(),
        ]);
        $coordinator = Role::query()
            ->where('alliance_id', $alliance->allianceId)
            ->where('key', DefaultAllianceRole::GiftCodeCoordinator->value)
            ->firstOrFail();
        $authorization = app(AllianceAuthorization::class);

        self::assertFalse(app(AllianceRankPermissions::class)->allows(
            AllianceRank::R5,
            AlliancePermission::GiftCodeCoverage,
        ));
        self::assertTrue($authorization->allows(
            $owner->playerId,
            $alliance->allianceId,
            AlliancePermission::GiftCodeCoverage,
        ));
        self::assertFalse($authorization->allows(
            $member->playerId,
            $alliance->allianceId,
            AlliancePermission::GiftCodeCoverage,
        ));

        app(AssignMembershipRole::class)->handle(
            $alliance->allianceId,
            $owner->playerId,
            (string) $memberMembership->id,
            (string) $coordinator->id,
        );
        self::assertTrue($authorization->allows(
            $member->playerId,
            $alliance->allianceId,
            AlliancePermission::GiftCodeCoverage,
        ));

        $giftCode = $this->validCode('GCW-ALLIANCE-COVERAGE');
        GiftCodeRedemption::query()->create([
            'gift_code_id' => $giftCode->id,
            'player_id' => $owner->playerId,
            'kingdom_id' => $owner->kingdomId,
            'status' => GiftCodeRedemptionStatus::Redeemed,
            'provider' => 'coverage-test',
            'attempts' => 1,
            'last_result_code' => GiftCodeRedemptionStatus::Redeemed->value,
            'last_attempt_at' => now(),
            'redeemed_at' => now(),
        ]);

        $coverage = app(GiftCodeAllianceCoverageQuery::class)->forAlliance($alliance->allianceId);
        self::assertSame(2, $coverage['eligibleGovernors']);
        self::assertCount(1, $coverage['codes']);
        self::assertSame(1, $coverage['codes'][0]['completed']);
        self::assertSame(1, $coverage['codes'][0]['incomplete']);
        self::assertSame(
            ['id', 'code', 'expiresAt', 'completed', 'incomplete', 'retryReady', 'unknown'],
            array_keys($coverage['codes'][0]),
        );
        $serialized = json_encode($coverage, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($owner->currentName, $serialized);
        self::assertStringNotContainsString($member->currentName, $serialized);
        self::assertStringNotContainsString((string) $owner->gamePlayerId, $serialized);
        self::assertStringNotContainsString((string) $member->gamePlayerId, $serialized);

        app(RemoveMembershipRole::class)->handle(
            $alliance->allianceId,
            $owner->playerId,
            (string) $memberMembership->id,
            (string) $coordinator->id,
        );
        self::assertFalse($authorization->allows(
            $member->playerId,
            $alliance->allianceId,
            AlliancePermission::GiftCodeCoverage,
        ));
    }

    private function validCode(string $code): GiftCode
    {
        return GiftCode::query()->create([
            'code' => $code,
            'normalized_code' => $code,
            'status' => GiftCodeStatus::Valid,
            'status_revision' => 1,
            'status_reason_code' => 'qualified_positive_evidence',
            'status_evidence_ids' => [],
            'status_changed_at' => now(),
            'status_derived_at' => now(),
            'discovered_at' => now(),
            'expires_revision' => 0,
        ]);
    }
}
