<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Events\Actions\CreateEvent;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventType;
use App\Domain\Events\Services\EventTypeRegistry;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\Kingdom;
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
            'event_alliance_results',
            'event_player_results',
            'event_result_metrics',
            'event_alliance_result_metrics',
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
            'kingdom_id_at_event',
            'context_frozen_at',
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
}
