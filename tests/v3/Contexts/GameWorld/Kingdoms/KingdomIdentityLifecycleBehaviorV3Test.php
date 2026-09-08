<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\Kingdoms;

use App\Contexts\GameWorld\Kingdoms\Actions\ArchiveKingdom;
use App\Contexts\GameWorld\Kingdoms\Actions\ArchiveKingdomAlliance;
use App\Contexts\GameWorld\Kingdoms\Actions\ResolveKingdomAlliance;
use App\Contexts\GameWorld\Kingdoms\Actions\RestoreKingdom;
use App\Contexts\GameWorld\Kingdoms\Actions\RestoreKingdomAlliance;
use App\Contexts\GameWorld\Kingdoms\Actions\UpdateKingdomAllianceIdentity;
use App\Contexts\GameWorld\Kingdoms\Enums\KingdomAllianceIdentitySource;
use App\Contexts\GameWorld\Kingdoms\Enums\KingdomAllianceStatus;
use App\Contexts\GameWorld\Kingdoms\Enums\KingdomStatus;
use App\Contexts\GameWorld\Kingdoms\Models\KingdomAllianceIdentityHistory;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomAllianceIdentityHistoryQuery;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomAllianceReferenceQuery;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class KingdomIdentityLifecycleBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_alliance_identity_is_conservative_while_stable_identity_is_idempotent(): void
    {
        $kingdom = (new ScenarioFactory)->kingdom(16001);
        $resolve = app(ResolveKingdomAlliance::class);

        $firstUnknown = $resolve->handle($kingdom->kingdomId, 'Same Name', 'TAG', null);
        $secondUnknown = $resolve->handle($kingdom->kingdomId, 'Same Name', 'TAG', null);
        self::assertNotSame($firstUnknown->kingdomAllianceId, $secondUnknown->kingdomAllianceId);

        $firstStable = $resolve->handle($kingdom->kingdomId, 'Stable Name', 'STB', 'game-alliance-16001');
        $secondStable = $resolve->handle($kingdom->kingdomId, 'Observed Rename', 'NEW', 'game-alliance-16001');
        self::assertSame($firstStable->kingdomAllianceId, $secondStable->kingdomAllianceId);
        self::assertSame('Stable Name', $secondStable->currentName);
        self::assertSame(3, KingdomAllianceIdentityHistory::query()->count());
    }

    public function test_archived_alliance_remains_historical_but_is_not_operational_until_explicit_restore(): void
    {
        $kingdom = (new ScenarioFactory)->kingdom(16002);
        $reference = app(ResolveKingdomAlliance::class)->handle($kingdom->kingdomId, 'Archive Me', 'ARC', 'stable-16002');

        $archived = app(ArchiveKingdomAlliance::class)->handle($reference->kingdomAllianceId, reason: 'disbanded');
        self::assertSame(KingdomAllianceStatus::Archived, $archived->statusObservedAtRead);
        self::assertNotNull(app(KingdomAllianceReferenceQuery::class)->find($reference->kingdomAllianceId));
        self::assertNull(app(KingdomAllianceReferenceQuery::class)->findActive($reference->kingdomAllianceId));

        $restored = app(RestoreKingdomAlliance::class)->handle($reference->kingdomAllianceId, reason: 'confirmed active again');
        self::assertSame(KingdomAllianceStatus::Active, $restored->statusObservedAtRead);
    }

    public function test_archiving_kingdom_cascades_operational_alliances_but_restore_does_not_reactivate_children(): void
    {
        $kingdom = (new ScenarioFactory)->kingdom(16003);
        $alliance = app(ResolveKingdomAlliance::class)->handle($kingdom->kingdomId, 'Cascade', 'CAS', null);

        $archivedKingdom = app(ArchiveKingdom::class)->handle($kingdom->kingdomId, reason: 'retired');
        self::assertSame(KingdomStatus::Archived->value, $archivedKingdom->status);
        self::assertSame(
            KingdomAllianceStatus::Archived,
            app(KingdomAllianceReferenceQuery::class)->require($alliance->kingdomAllianceId)->statusObservedAtRead,
        );
        self::assertNotNull(app(KingdomReferenceQuery::class)->find($kingdom->kingdomId));
        self::assertNull(app(KingdomReferenceQuery::class)->findActive($kingdom->kingdomId));

        $restoredKingdom = app(RestoreKingdom::class)->handle($kingdom->kingdomId, reason: 'reopened');
        self::assertSame(KingdomStatus::Active->value, $restoredKingdom->status);
        self::assertNull(app(KingdomAllianceReferenceQuery::class)->findActive($alliance->kingdomAllianceId));

        $restoredAlliance = app(RestoreKingdomAlliance::class)->handle($alliance->kingdomAllianceId);
        self::assertSame(KingdomAllianceStatus::Active, $restoredAlliance->statusObservedAtRead);
    }

    public function test_archived_kingdom_rejects_new_or_existing_operational_alliance_resolution(): void
    {
        $kingdom = (new ScenarioFactory)->kingdom(16004);
        app(ArchiveKingdom::class)->handle($kingdom->kingdomId);

        $this->expectException(ValidationException::class);
        app(ResolveKingdomAlliance::class)->handle($kingdom->kingdomId, 'Rejected', 'NO', null);
    }

    public function test_identity_mutation_records_temporal_history_and_provenance_without_noop_rows(): void
    {
        $kingdom = (new ScenarioFactory)->kingdom(16005);
        $alliance = app(ResolveKingdomAlliance::class)->handle($kingdom->kingdomId, 'First', 'ONE', null);

        $updated = app(UpdateKingdomAllianceIdentity::class)->handle(
            $alliance->kingdomAllianceId,
            $kingdom->kingdomId,
            'Second',
            'TWO',
            'stable-16005',
            KingdomAllianceIdentitySource::IntelligenceObservation,
            'observation:16005',
            confidenceBasisPoints: 9200,
            reason: 'newer accepted observation',
        );
        self::assertSame('Second', $updated->currentName);
        self::assertSame('stable-16005', $updated->gameAllianceId);

        $history = app(KingdomAllianceIdentityHistoryQuery::class)->identityHistory($alliance->kingdomAllianceId);
        self::assertCount(2, $history);
        self::assertNotNull($history[0]->validTo);
        self::assertNull($history[1]->validTo);
        self::assertSame(KingdomAllianceIdentitySource::IntelligenceObservation, $history[1]->sourceType);
        self::assertSame('observation:16005', $history[1]->sourceReference);
        self::assertSame(9200, $history[1]->confidenceBasisPoints);

        app(UpdateKingdomAllianceIdentity::class)->handle(
            $alliance->kingdomAllianceId,
            $kingdom->kingdomId,
            'Second',
            'TWO',
            'stable-16005',
        );
        self::assertCount(2, app(KingdomAllianceIdentityHistoryQuery::class)->identityHistory($alliance->kingdomAllianceId));
    }
}
