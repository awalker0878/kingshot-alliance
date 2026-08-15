<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Enums\DefaultKingdomRole;
use App\Domain\Authorization\Models\KingdomRoleAssignment;
use App\Domain\Authorization\Services\KingdomRoleProvisioner;
use App\Domain\Events\Actions\CreateEvent;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventKingdomAllianceResult;
use App\Domain\Events\Models\EventPlayerContext;
use App\Domain\Events\Models\EventType;
use App\Domain\Events\Services\EventTypeRegistry;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\KingdomAlliance;
use App\Domain\Kingdoms\Models\Player;
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
            'event_kingdom_alliance_results',
            'event_player_results',
            'event_result_metrics',
            'event_kingdom_alliance_result_metrics',
            'event_player_result_metrics',
        ] as $table) {
            self::assertTrue(Schema::hasTable($table), $table);
        }

        self::assertTrue(Schema::hasColumns('events', [
            'target_display_name',
            'target_secondary_label',
        ]));
        self::assertTrue(Schema::hasColumns('event_metric_definitions', [
            'event_type_scope_id',
            'key',
            'subject',
            'value_type',
            'aggregation',
            'is_contribution_metric',
        ]));
        self::assertTrue(Schema::hasColumns('event_player_contexts', [
            'occurrence_id',
            'player_id',
            'player_name_snapshot',
            'represented_alliance_id',
            'represented_kingdom_alliance_id',
            'kingdom_id_at_event',
            'context_frozen_at',
        ]));
        self::assertTrue(Schema::hasColumns('event_kingdom_alliance_results', [
            'occurrence_id',
            'kingdom_alliance_id',
            'alliance_name_snapshot',
            'alliance_tag_snapshot',
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

    public function test_kingdom_event_alliance_result_must_use_game_alliance_from_the_same_kingdom(): void
    {
        [$actor, $eventKingdom] = $this->player(8713, 'Kingdom Historian');
        $this->grantKingdomAdministrator($actor, $eventKingdom);
        $event = $this->createKingdomEvent($actor, $eventKingdom);
        $occurrence = $event->occurrences->firstOrFail();

        [, $otherKingdom] = $this->player(8714, 'Other Kingdom Player');
        $wrongAlliance = KingdomAlliance::query()->create([
            'kingdom_id' => $otherKingdom->id,
            'game_alliance_id' => '8714-wrong-alliance',
            'current_name' => 'Wrong Kingdom Alliance',
            'current_tag' => 'WKA',
            'status' => 'active',
        ]);

        $this->expectException(QueryException::class);

        EventKingdomAllianceResult::query()->create([
            'occurrence_id' => $occurrence->id,
            'kingdom_alliance_id' => $wrongAlliance->id,
            'alliance_name_snapshot' => $wrongAlliance->current_name,
            'alliance_tag_snapshot' => $wrongAlliance->current_tag,
            'score' => 100,
            'recorded_by_player_id' => $actor->id,
            'recorded_at' => now(),
        ]);
    }

    public function test_kingdom_event_alliance_result_accepts_game_alliance_from_the_event_kingdom(): void
    {
        [$actor, $eventKingdom] = $this->player(8715, 'Kingdom Historian');
        $this->grantKingdomAdministrator($actor, $eventKingdom);
        $event = $this->createKingdomEvent($actor, $eventKingdom);
        $occurrence = $event->occurrences->firstOrFail();
        $alliance = KingdomAlliance::query()->create([
            'kingdom_id' => $eventKingdom->id,
            'game_alliance_id' => '8715-alliance',
            'current_name' => 'Kingdom Alliance',
            'current_tag' => 'KA',
            'status' => 'active',
        ]);

        $result = EventKingdomAllianceResult::query()->create([
            'occurrence_id' => $occurrence->id,
            'kingdom_alliance_id' => $alliance->id,
            'alliance_name_snapshot' => $alliance->current_name,
            'alliance_tag_snapshot' => $alliance->current_tag,
            'score' => 250,
            'recorded_by_player_id' => $actor->id,
            'recorded_at' => now(),
        ]);

        self::assertSame((string) $alliance->id, (string) $result->kingdom_alliance_id);
        self::assertSame(250, $result->score);
    }

    public function test_frozen_player_context_rejects_represented_game_alliance_from_another_kingdom(): void
    {
        [$actor, $eventKingdom] = $this->player(8716, 'Context Player');
        $this->grantKingdomAdministrator($actor, $eventKingdom);
        $event = $this->createKingdomEvent($actor, $eventKingdom);
        $occurrence = $event->occurrences->firstOrFail();

        [, $otherKingdom] = $this->player(8717, 'Other Context Player');
        $wrongAlliance = KingdomAlliance::query()->create([
            'kingdom_id' => $otherKingdom->id,
            'game_alliance_id' => '8717-context-alliance',
            'current_name' => 'Wrong Context Alliance',
            'current_tag' => 'WCA',
            'status' => 'active',
        ]);

        $this->expectException(QueryException::class);

        EventPlayerContext::query()->create([
            'occurrence_id' => $occurrence->id,
            'player_id' => $actor->id,
            'player_name_snapshot' => $actor->current_name,
            'represented_kingdom_alliance_id' => $wrongAlliance->id,
            'represented_alliance_name_snapshot' => $wrongAlliance->current_name,
            'represented_alliance_tag_snapshot' => $wrongAlliance->current_tag,
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
        $player = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => (string) $kingdomNumber.'-owner',
            'current_name' => $name,
        ]);

        return [$player, $kingdom];
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
