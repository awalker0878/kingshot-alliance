<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\Kingdoms;

use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\GameWorld\Governance\Services\KingdomWriteState;
use App\Contexts\GameWorld\Kingdoms\Actions\ArchiveKingdom;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Players\Actions\PersistPlayerIdentity;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Events\Services\EventTargetResolver;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class KingdomDownstreamActiveBoundaryV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_active_locks_reject_archived_kingdom_while_historical_reference_survives(): void
    {
        $kingdom = (new ScenarioFactory)->kingdom(16101);
        $query = app(KingdomReferenceQuery::class);

        $locked = DB::transaction(fn () => $query->lockActiveShared($kingdom->kingdomId));
        self::assertSame($kingdom->kingdomId, $locked->kingdomId);

        app(ArchiveKingdom::class)->handle($kingdom->kingdomId, reason: 'downstream-boundary-test');
        self::assertSame($kingdom->kingdomId, $query->require($kingdom->kingdomId)->kingdomId);

        $this->expectException(ModelNotFoundException::class);
        DB::transaction(fn () => $query->lockActiveShared($kingdom->kingdomId));
    }

    public function test_archived_kingdom_rejects_player_alliance_and_governance_operational_state(): void
    {
        $factory = new ScenarioFactory;
        $owner = $factory->account();
        $actor = $factory->player($owner->userId, 16102);
        $alliance = $factory->alliance($actor);
        $kingdom = $factory->kingdom(16102);

        DB::transaction(fn () => app(KingdomWriteState::class)->lockActiveScope($actor->playerId, $kingdom->kingdomId));
        DB::transaction(fn () => app(AllianceWriteState::class)->lockActiveScope($actor->playerId, $alliance->allianceId));

        app(ArchiveKingdom::class)->handle($kingdom->kingdomId, reason: 'freeze-current-work');

        try {
            DB::transaction(fn () => app(KingdomWriteState::class)->lockActiveScope($actor->playerId, $kingdom->kingdomId));
            self::fail('Archived Kingdom governance state must fail closed.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }

        try {
            DB::transaction(fn () => app(AllianceWriteState::class)->lockActiveScope($actor->playerId, $alliance->allianceId));
            self::fail('Alliance writes under an archived Kingdom must fail closed.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }

        $this->expectException(ValidationException::class);
        app(PersistPlayerIdentity::class)->handle($kingdom->kingdomId, 'Archived Target', 'archived-16102');
    }

    public function test_event_history_resolves_after_archive_but_new_event_write_is_rejected(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 16103);
        $alliance = $scenario->alliance($actor);
        $kingdom = $scenario->kingdom(16103);
        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'bear-hunt'))
            ->firstOrFail();

        $created = app(CreateEvent::class)->handle(
            actorPlayerId: $actor->playerId,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $alliance->allianceId,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            title: 'Historical Event',
            durationMinutes: 60,
        );
        $event = Event::query()->findOrFail($created->eventId);

        app(ArchiveKingdom::class)->handle($kingdom->kingdomId, reason: 'retired');

        $historicalTarget = app(EventTargetResolver::class)->forEvent($event);
        self::assertSame($alliance->allianceId, $historicalTarget->targetId);
        self::assertSame($kingdom->kingdomId, $historicalTarget->kingdomId);

        $this->expectException(ModelNotFoundException::class);
        app(CreateEvent::class)->handle(
            actorPlayerId: $actor->playerId,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $alliance->allianceId,
            firstLocalStart: CarbonImmutable::now('UTC')->addDays(2),
            title: 'Rejected Event',
            durationMinutes: 60,
        );
    }
}
