<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\Players;

use App\Contexts\GameWorld\Players\Actions\ClaimPlayerAccount;
use App\Contexts\GameWorld\Players\Actions\PersistPlayerIdentity;
use App\Contexts\GameWorld\Players\Actions\ReconcilePlayers;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\Models\PlayerIdentityHistory;
use App\Contexts\GameWorld\Players\Models\PlayerReconciliation;
use App\Contexts\GameWorld\Players\Queries\PlayerReconciliationCandidateQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\Queries\PlayersIntegrityQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class PlayerReconciliationBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_reconciliation_preserves_alias_and_transfers_late_identity_facts(): void
    {
        $factory = new ScenarioFactory;
        $account = $factory->account();
        $kingdom = $factory->kingdom(19201);
        $persist = app(PersistPlayerIdentity::class);
        $canonical = $persist->handle($kingdom->kingdomId, 'Same Governor', null);
        $duplicate = $persist->handle($kingdom->kingdomId, 'same governor', 'stable-19201');
        app(ClaimPlayerAccount::class)->handle($duplicate->playerId, $account->userId);

        $candidates = app(PlayerReconciliationCandidateQuery::class)->forPlayer($canonical->playerId);
        self::assertSame($duplicate->playerId, $candidates[0]['player_id']);
        self::assertContains('same_normalized_name', $candidates[0]['reasons']);

        $result = app(ReconcilePlayers::class)->handle(
            $canonical->playerId,
            $duplicate->playerId,
            'Stable identity evidence proves both records are the same Governor.',
            sourceReference: 'case:19201',
            confidenceBasisPoints: 10000,
        );

        self::assertSame($canonical->playerId, $result->playerId);
        self::assertSame($account->userId, $result->userId);
        self::assertSame('stable-19201', $result->gamePlayerId);

        $alias = app(PlayerReferenceQuery::class)->require($duplicate->playerId);
        self::assertSame($canonical->playerId, $alias->canonicalPlayerId);
        self::assertNull($alias->userId);
        self::assertNull($alias->gamePlayerId);
        self::assertSame($canonical->playerId, app(PlayerReferenceQuery::class)->requireCanonical($duplicate->playerId)->playerId);
        self::assertSame(1, PlayerReconciliation::query()->count());
        self::assertSame(0, PlayerIdentityHistory::query()->where('player_id', $duplicate->playerId)->whereNull('valid_to')->count());

        app(ReconcilePlayers::class)->handle($canonical->playerId, $duplicate->playerId, 'idempotent repeat');
        self::assertSame(1, PlayerReconciliation::query()->count());
        self::assertNotContains($duplicate->playerId, app(PlayerReferenceQuery::class)->ownedIds($account->userId));
    }

    public function test_similarity_only_surfaces_candidates_and_never_auto_merges(): void
    {
        $factory = new ScenarioFactory;
        $kingdom = $factory->kingdom(19202);
        $persist = app(PersistPlayerIdentity::class);
        $left = $persist->handle($kingdom->kingdomId, 'Duplicate Name', null);
        $right = $persist->handle($kingdom->kingdomId, 'duplicate name', null);

        self::assertNotSame($left->playerId, $right->playerId);
        self::assertNull(Player::query()->findOrFail($left->playerId)->canonical_player_id);
        self::assertNull(Player::query()->findOrFail($right->playerId)->canonical_player_id);
        self::assertNotEmpty(app(PlayerReconciliationCandidateQuery::class)->forPlayer($left->playerId));
        self::assertNotEmpty(app(PlayersIntegrityQuery::class)->report()['unresolved_duplicate_candidates']);
    }

    public function test_cross_kingdom_conflicting_owner_and_active_dependency_reconciliation_are_rejected(): void
    {
        $factory = new ScenarioFactory;
        $firstAccount = $factory->account();
        $secondAccount = $factory->account();
        $first = $factory->player($firstAccount->userId, 19203, null);
        $otherKingdom = $factory->player($firstAccount->userId, 19204, null);

        try {
            app(ReconcilePlayers::class)->handle($first->playerId, $otherKingdom->playerId, 'invalid cross kingdom');
            self::fail('Cross-Kingdom reconciliation must fail.');
        } catch (ValidationException) {
            self::assertTrue(true);
        }

        $otherOwner = $factory->player($secondAccount->userId, 19203, null);
        try {
            app(ReconcilePlayers::class)->handle($first->playerId, $otherOwner->playerId, 'invalid cross owner');
            self::fail('Cross-owner reconciliation must fail.');
        } catch (ValidationException) {
            self::assertTrue(true);
        }

        $duplicateWithMembership = $factory->player($firstAccount->userId, 19203, null);
        $factory->alliance($duplicateWithMembership);
        $this->expectException(ValidationException::class);
        app(ReconcilePlayers::class)->handle($first->playerId, $duplicateWithMembership->playerId, 'unsafe active dependency');
    }
}
