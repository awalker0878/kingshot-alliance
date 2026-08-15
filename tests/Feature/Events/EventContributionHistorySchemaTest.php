<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Governance\Services\KingdomRoleProvisioner;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Actions\CreateEvent;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\EventCore\Models\Event;
use App\Contexts\Operations\EventCore\Models\EventAllianceResult;
use App\Contexts\Operations\EventCore\Models\EventPlayerContext;
use App\Contexts\Operations\EventCore\Models\EventType;
use App\Contexts\Operations\EventCore\Services\EventTypeRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class EventContributionHistorySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_greenfield_schema_has_normalized_event_history_tables_without_json_metric_columns(): void
    {
        foreach ([
            'event_metric_definitions',
            'event_player_contexts',
            'event_results',
            'event_alliance_results',
            'event_player_results',
            'event_result_metrics',
            'event_alliance_result_metrics',
            'event_player_result_metrics',
        ] as $table) {
            self::assertTrue(Schema::hasTable($table), $table);
        }

        self::assertTrue(Schema::hasColumns('alliances', [
            'id',
            'kingdom_id',
            'name',
        ]));
        self::assertTrue(Schema::hasColumns('events', [
            'target_display_name',
            'target_secondary_label',
        ]));
        self::assertTrue(Schema::hasColumns('event_type_scopes', [
            'result_score_label_key',
            'result_score_unit',
            'result_score_higher_is_better',
        ]));
        self::assertTrue(Schema::hasColumns('event_metric_definitions', [
            'event_type_scope_id',
            'key',
            'subject',
            'value_type',
            'aggregation',
            'dimension_kind',
            'is_contribution_metric',
        ]));
        self::assertTrue(Schema::hasColumns('event_player_contexts', [
            'occurrence_id',
            'player_id',
            'player_name_snapshot',
            'represented_alliance_id',
            'kingdom_id_at_event',
            'context_frozen_at',
        ]));
        self::assertTrue(Schema::hasColumns('event_alliance_results', [
            'occurrence_id',
            'alliance_id',
            'alliance_name_snapshot',
            'score',
            'rank',
        ]));
        self::assertFalse(Schema::hasColumn('event_results', 'metrics'));
        self::assertFalse(Schema::hasColumn('event_player_results', 'metrics'));
    }

    public function test_event_creation_freezes_target_display_snapshot_independently_of_current_alliance_name(): void
    {
        [$player, $kingdom] = $this->player(8711, 'Snapshot Owner');
        $alliance = $this->app->make(CreateAlliance::class)->handle($player, 'Original Alliance', 'original-alliance');
        $event = $this->createAllianceEvent($player, $alliance);

        self::assertSame('Original Alliance', $event->target_display_name);
        self::assertSame('Kingdom #8711', $event->target_secondary_label);

        $alliance->forceFill(['name' => 'Renamed Alliance'])->save();
        $event->refresh();

        self::assertSame('Original Alliance', $event->target_display_name);
        self::assertSame('Kingdom #'.(int) $kingdom->number, $event->target_secondary_label);
    }

    public function test_database_rejects_retargeting_an_existing_event(): void
    {
        [$firstPlayer] = $this->player(8712, 'First Owner');
        $kingdom = $firstPlayer->currentKingdom()->firstOrFail();
        $firstAlliance = $this->app->make(CreateAlliance::class)->handle($firstPlayer, 'First Alliance', 'first-alliance');

        $secondUser = User::factory()->create();
        $secondPlayer = Player::query()->create([
            'user_id' => $secondUser->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8712-second',
            'current_name' => 'Second Owner',
        ]);
        $secondAlliance = $this->app->make(CreateAlliance::class)->handle($secondPlayer, 'Second Alliance', 'second-alliance');
        $event = $this->createAllianceEvent($firstPlayer, $firstAlliance);

        $this->expectException(QueryException::class);

        DB::table('events')
            ->where('id', $event->id)
            ->update(['alliance_id' => $secondAlliance->id]);
    }

    public function test_kingdom_event_alliance_result_rejects_alliance_from_another_kingdom(): void
    {
        [$actor, $eventKingdom] = $this->player(8713, 'Kingdom Historian');
        $this->grantKingdomAdministrator($actor, $eventKingdom);
        $event = $this->createKingdomEvent($actor, $eventKingdom);
        $occurrence = $event->occurrences->firstOrFail();

        [$otherOwner] = $this->player(8714, 'Other Alliance Owner');
        $wrongAlliance = $this->app->make(CreateAlliance::class)
            ->handle($otherOwner, 'Same Name Allowed', 'other-kingdom-alliance');

        $this->expectException(QueryException::class);

        EventAllianceResult::query()->create([
            'occurrence_id' => $occurrence->id,
            'alliance_id' => $wrongAlliance->id,
            'alliance_name_snapshot' => $wrongAlliance->name,
            'score' => 100,
            'recorded_by_player_id' => $actor->id,
            'recorded_at' => now(),
        ]);
    }

    public function test_kingdom_event_alliance_result_accepts_alliance_whose_kingdom_matches_event(): void
    {
        [$actor, $eventKingdom] = $this->player(8715, 'Kingdom Historian');
        $this->grantKingdomAdministrator($actor, $eventKingdom);
        $event = $this->createKingdomEvent($actor, $eventKingdom);
        $occurrence = $event->occurrences->firstOrFail();

        $allianceOwner = $this->playerInKingdom($eventKingdom, 'Alliance Owner', '8715-alliance-owner');
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($allianceOwner, 'Same Name Allowed', 'same-kingdom-alliance');

        $result = EventAllianceResult::query()->create([
            'occurrence_id' => $occurrence->id,
            'alliance_id' => $alliance->id,
            'alliance_name_snapshot' => $alliance->name,
            'score' => 250,
            'recorded_by_player_id' => $actor->id,
            'recorded_at' => now(),
        ]);

        self::assertSame((string) $alliance->id, (string) $result->alliance_id);
        self::assertSame((string) $eventKingdom->id, (string) $alliance->kingdom_id);
        self::assertSame(250, $result->score);
    }

    public function test_frozen_player_context_rejects_represented_alliance_from_another_kingdom(): void
    {
        [$actor, $eventKingdom] = $this->player(8716, 'Context Player');
        $this->grantKingdomAdministrator($actor, $eventKingdom);
        $event = $this->createKingdomEvent($actor, $eventKingdom);
        $occurrence = $event->occurrences->firstOrFail();

        [$otherOwner] = $this->player(8717, 'Other Context Owner');
        $wrongAlliance = $this->app->make(CreateAlliance::class)
            ->handle($otherOwner, 'Same Name Allowed', 'wrong-context-alliance');

        $this->expectException(QueryException::class);

        EventPlayerContext::query()->create([
            'occurrence_id' => $occurrence->id,
            'player_id' => $actor->id,
            'player_name_snapshot' => $actor->current_name,
            'represented_alliance_id' => $wrongAlliance->id,
            'represented_alliance_name_snapshot' => $wrongAlliance->name,
            'kingdom_id_at_event' => $eventKingdom->id,
            'context_frozen_at' => now(),
        ]);
    }

    /** @return array{0:Player,1:Kingdom} */
    private function player(int $kingdomNumber, string $name): array
    {
        $user = User::factory()->create();
        $kingdom = Kingdom::query()->create([
            'number' => $kingdomNumber,
            'status' => 'active',
        ]);
        $player = $this->playerInKingdom($kingdom, $name, (string) $kingdomNumber.'-owner', $user);

        return [$player, $kingdom];
    }

    private function playerInKingdom(Kingdom $kingdom, string $name, string $gamePlayerId, ?User $user = null): Player
    {
        $user ??= User::factory()->create();

        return Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => $gamePlayerId,
            'current_name' => $name,
        ]);
    }

    private function grantKingdomAdministrator(Player $player, Kingdom $kingdom): void
    {
        $roles = $this->app->make(KingdomRoleProvisioner::class)->provision($kingdom);
        $administrator = $roles[DefaultKingdomRole::Administrator->value];

        KingdomRoleAssignment::query()->create([
            'kingdom_id' => $kingdom->id,
            'player_id' => $player->id,
            'kingdom_role_id' => $administrator->id,
        ]);
    }

    private function createAllianceEvent(Player $player, Alliance $alliance): Event
    {
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);

        return $this->app->make(CreateEvent::class)->handle(
            actor: $player,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 60,
        );
    }

    private function createKingdomEvent(Player $player, Kingdom $kingdom): Event
    {
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Kingdom);

        return $this->app->make(CreateEvent::class)->handle(
            actor: $player,
            configuration: $configuration,
            target: $kingdom,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 60,
        );
    }
}
