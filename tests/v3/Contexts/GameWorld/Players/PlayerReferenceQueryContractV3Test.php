<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\Players;

use App\Contexts\GameWorld\Players\Actions\PersistPlayerIdentity;
use App\Contexts\GameWorld\Players\Actions\ReconcilePlayers;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class PlayerReferenceQueryContractV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_owned_reads_are_isolated_ordered_bounded_and_include_kingdom_projection(): void
    {
        $factory = new ScenarioFactory;
        $owner = $factory->account();
        $other = $factory->account();
        $first = $factory->player($owner->userId, 19301);
        $second = $factory->player($owner->userId, 19302);
        $factory->player($other->userId, 19303);
        $query = app(PlayerReferenceQuery::class);

        $owned = $query->ownedByUser($owner->userId);
        self::assertSame(2, count($owned));
        self::assertSame([$first->playerId, $second->playerId], array_values(array_map(static fn ($player): string => $player->playerId, $owned)));
        self::assertSame([19301, 19302], array_values(array_map(static fn ($player): ?int => $player->kingdomNumber, $owned)));
        self::assertCount(1, $query->ownedByUserUpTo($owner->userId, 1));
        self::assertNull($query->findOwnedByUser($owner->userId, $factory->player($other->userId, 19304)->playerId));
        self::assertSame([$first->playerId, $second->playerId], $query->ownedIds($owner->userId));
    }

    public function test_by_ids_normalizes_input_and_stable_and_kingdom_queries_return_direct_identities(): void
    {
        $factory = new ScenarioFactory;
        $owner = $factory->account();
        $first = $factory->player($owner->userId, 19310, 'stable-19310');
        $second = $factory->player($owner->userId, 19310, 'stable-19311');
        $query = app(PlayerReferenceQuery::class);

        $byIds = $query->byIds([$first->playerId, '', $first->playerId, $second->playerId]);
        self::assertSame([$first->playerId, $second->playerId], array_keys($byIds));
        self::assertSame($first->playerId, $query->findByGamePlayerId(' stable-19310 ')?->playerId);
        self::assertSame([$first->playerId], array_values(array_map(
            static fn ($player): string => $player->playerId,
            $query->matchingGamePlayerIdInKingdom($first->kingdomId, 'stable-19310'),
        )));
    }

    public function test_historical_alias_is_fetchable_but_excluded_from_current_operational_reads(): void
    {
        $factory = new ScenarioFactory;
        $owner = $factory->account();
        $kingdom = $factory->kingdom(19320);
        $persist = app(PersistPlayerIdentity::class);
        $canonical = $persist->handle($kingdom->kingdomId, 'Canonical', null);
        $duplicate = $factory->player($owner->userId, 19320, 'alias-stable-19320');

        app(ReconcilePlayers::class)->handle($canonical->playerId, $duplicate->playerId, 'explicit duplicate evidence');
        $query = app(PlayerReferenceQuery::class);

        self::assertSame($duplicate->playerId, $query->require($duplicate->playerId)->playerId);
        self::assertSame($canonical->playerId, $query->requireCanonical($duplicate->playerId)->playerId);
        self::assertArrayHasKey($duplicate->playerId, $query->byIds([$duplicate->playerId]));
        self::assertNotContains($duplicate->playerId, $query->ownedIds($owner->userId));
        self::assertNotContains($duplicate->playerId, array_map(static fn ($player): string => $player->playerId, $query->inKingdom($kingdom->kingdomId)));
    }
}
