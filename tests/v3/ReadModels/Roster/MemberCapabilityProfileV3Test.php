<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\Roster;

use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\ReadModels\Roster\Queries\MemberCapabilityProfileQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class MemberCapabilityProfileV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_profile_composes_factual_owner_sections_without_an_opaque_score(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->account();
        $actor = $scenario->player($account->userId, 78201);
        $alliance = $scenario->alliance($actor);
        $reference = $scenario->roster($actor, $alliance);
        $entry = AllianceRosterEntry::query()->findOrFail($reference->rosterEntryId);

        $profile = app(MemberCapabilityProfileQuery::class)->forPlayer(
            $actor->playerId,
            $alliance->allianceId,
            $entry,
            $actor,
        );

        self::assertSame('available', $profile['eventAccess']);
        self::assertSame(0, $profile['events']['count']);
        self::assertSame(0, $profile['bearHunt']['recordedResultCount']);
        self::assertSame([], $profile['rallies']);
        self::assertSame([], $profile['battleAssignments']);
        self::assertSame('available', $profile['evidence']['access']);
        self::assertSame(0, $profile['evidence']['total']);
        self::assertArrayNotHasKey('capabilityScore', $profile);
        self::assertArrayNotHasKey('recommendation', $profile);

        AllianceMembership::query()
            ->where('alliance_id', $alliance->allianceId)
            ->where('player_id', $actor->playerId)
            ->update(['rank' => AllianceRank::R3->value]);
        $limited = app(MemberCapabilityProfileQuery::class)->forPlayer(
            $actor->playerId,
            $alliance->allianceId,
            $entry,
            $actor,
        );
        self::assertSame('unavailable', $limited['evidence']['access']);
        self::assertSame(0, $limited['evidence']['total']);
    }

    public function test_profile_rejects_cross_alliance_owner_reads_before_composition(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->account();
        $actor = $scenario->player($account->userId, 78202);
        $alliance = $scenario->alliance($actor);
        $reference = $scenario->roster($actor, $alliance);
        $entry = AllianceRosterEntry::query()->findOrFail($reference->rosterEntryId);

        $otherAccount = $scenario->account();
        $other = $scenario->player($otherAccount->userId, 78203);
        $scenario->alliance($other);

        $this->expectException(AuthorizationException::class);
        app(MemberCapabilityProfileQuery::class)->forPlayer(
            $other->playerId,
            $alliance->allianceId,
            $entry,
            $actor,
        );
    }

    public function test_profile_rejects_a_roster_entry_from_another_alliance(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->account();
        $actor = $scenario->player($account->userId, 78204);
        $alliance = $scenario->alliance($actor);

        $otherAccount = $scenario->account();
        $other = $scenario->player($otherAccount->userId, 78205);
        $otherAlliance = $scenario->alliance($other);
        $reference = $scenario->roster($other, $otherAlliance);
        $entry = AllianceRosterEntry::query()->findOrFail($reference->rosterEntryId);

        $this->expectException(AuthorizationException::class);
        app(MemberCapabilityProfileQuery::class)->forPlayer(
            $actor->playerId,
            $alliance->allianceId,
            $entry,
            $other,
        );
    }
}
