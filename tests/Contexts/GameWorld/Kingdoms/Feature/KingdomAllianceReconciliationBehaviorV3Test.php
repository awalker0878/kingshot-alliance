<?php

declare(strict_types=1);

namespace Tests\Contexts\GameWorld\Kingdoms\Feature;

use App\Contexts\GameWorld\Kingdoms\Actions\ReconcileKingdomAlliances;
use App\Contexts\GameWorld\Kingdoms\Actions\ResolveKingdomAlliance;
use App\Contexts\GameWorld\Kingdoms\Actions\UpdateKingdomAllianceIdentity;
use App\Contexts\GameWorld\Kingdoms\Enums\KingdomAllianceStatus;
use App\Contexts\GameWorld\Kingdoms\Models\KingdomAllianceIdentityHistory;
use App\Contexts\GameWorld\Kingdoms\Models\KingdomAllianceReconciliation;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomAllianceReconciliationCandidateQuery;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomAllianceReferenceQuery;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomsIntegrityQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class KingdomAllianceReconciliationBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_reconciliation_preserves_alias_and_transfers_late_stable_identity(): void
    {
        $kingdom = (new ScenarioFactory)->kingdom(16101);
        $resolve = app(ResolveKingdomAlliance::class);
        $canonical = $resolve->handle($kingdom->kingdomId, 'Alpha', 'AAA', null);
        $duplicate = $resolve->handle($kingdom->kingdomId, 'Alpha', 'AAA', null);

        $candidates = app(KingdomAllianceReconciliationCandidateQuery::class)->forAlliance($canonical->kingdomAllianceId);
        self::assertSame($duplicate->kingdomAllianceId, $candidates[0]['kingdom_alliance_id']);

        app(UpdateKingdomAllianceIdentity::class)->handle(
            $duplicate->kingdomAllianceId,
            $kingdom->kingdomId,
            'Alpha',
            'AAA',
            'stable-16101',
        );

        $result = app(ReconcileKingdomAlliances::class)->handle(
            $canonical->kingdomAllianceId,
            $duplicate->kingdomAllianceId,
            'Stable game identity and observation evidence prove both records are one Alliance.',
            sourceReference: 'case:16101',
            confidenceBasisPoints: 10000,
        );

        self::assertSame($canonical->kingdomAllianceId, $result->kingdomAllianceId);
        self::assertSame('stable-16101', $result->gameAllianceId);
        $alias = app(KingdomAllianceReferenceQuery::class)->require($duplicate->kingdomAllianceId);
        self::assertSame(KingdomAllianceStatus::Archived, $alias->statusObservedAtRead);
        self::assertSame($canonical->kingdomAllianceId, $alias->canonicalKingdomAllianceId);
        self::assertNull($alias->gameAllianceId);
        self::assertSame(
            $canonical->kingdomAllianceId,
            app(KingdomAllianceReferenceQuery::class)->requireCanonical($duplicate->kingdomAllianceId)->kingdomAllianceId,
        );
        self::assertSame(1, KingdomAllianceReconciliation::query()->count());
        self::assertSame(
            0,
            KingdomAllianceIdentityHistory::query()
                ->where('kingdom_alliance_id', $duplicate->kingdomAllianceId)
                ->whereNull('valid_to')
                ->count(),
        );
    }

    public function test_name_and_tag_similarity_only_surface_candidates_and_never_auto_merge(): void
    {
        $kingdom = (new ScenarioFactory)->kingdom(16102);
        $resolve = app(ResolveKingdomAlliance::class);
        $left = $resolve->handle($kingdom->kingdomId, 'Duplicate Name', 'DUP', null);
        $right = $resolve->handle($kingdom->kingdomId, 'duplicate name', 'dup', null);

        self::assertNotSame($left->kingdomAllianceId, $right->kingdomAllianceId);
        self::assertNull($left->canonicalKingdomAllianceId);
        self::assertNull($right->canonicalKingdomAllianceId);
        self::assertNotEmpty(app(KingdomAllianceReconciliationCandidateQuery::class)->forAlliance($left->kingdomAllianceId));
        self::assertNotEmpty(app(KingdomsIntegrityQuery::class)->report()['unresolved_duplicate_candidates']);
    }

    public function test_cross_kingdom_and_conflicting_stable_identity_reconciliation_are_rejected(): void
    {
        $factory = new ScenarioFactory;
        $firstKingdom = $factory->kingdom(16103);
        $secondKingdom = $factory->kingdom(16104);
        $resolve = app(ResolveKingdomAlliance::class);
        $first = $resolve->handle($firstKingdom->kingdomId, 'One', 'ONE', null);
        $otherKingdom = $resolve->handle($secondKingdom->kingdomId, 'Two', 'TWO', null);

        try {
            app(ReconcileKingdomAlliances::class)->handle(
                $first->kingdomAllianceId,
                $otherKingdom->kingdomAllianceId,
                'invalid cross kingdom attempt',
            );
            self::fail('Cross-Kingdom reconciliation must fail.');
        } catch (ValidationException) {
            self::assertTrue(true);
        }

        $stableOne = $resolve->handle($firstKingdom->kingdomId, 'Stable One', 'S1', 'stable-one');
        $stableTwo = $resolve->handle($firstKingdom->kingdomId, 'Stable Two', 'S2', 'stable-two');

        $this->expectException(ValidationException::class);
        app(ReconcileKingdomAlliances::class)->handle(
            $stableOne->kingdomAllianceId,
            $stableTwo->kingdomAllianceId,
            'invalid conflicting stable IDs',
        );
    }
}
