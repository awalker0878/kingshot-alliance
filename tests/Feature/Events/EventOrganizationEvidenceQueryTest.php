<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Events\Actions\CreateEvent;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Models\EventType;
use App\Domain\Events\Queries\EventAllianceHistoryQuery;
use App\Domain\Events\Services\EventTypeRegistry;
use App\Contexts\Accounts\Models\User;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class EventOrganizationEvidenceQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_alliance_history_summarizes_occurrence_operational_evidence_without_current_membership_reconstruction(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 8991, 'status' => 'active']);
        $owner = Player::query()->create([
            'user_id' => User::factory()->create()->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8991-owner',
            'current_name' => 'Evidence Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Evidence Alliance', 'evidence-alliance');

        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $owner,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->subHour(),
            durationMinutes: 60,
        );
        $occurrence = $event->occurrences->firstOrFail();
        $now = now();

        DB::table('event_attendance')->insert([
            'id' => (string) Str::ulid(),
            'occurrence_id' => $occurrence->id,
            'player_id' => $owner->id,
            'status' => 'present',
            'notes' => null,
            'recorded_by_player_id' => $owner->id,
            'recorded_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('event_objectives')->insert([
            'id' => (string) Str::ulid(),
            'occurrence_id' => $occurrence->id,
            'parent_id' => null,
            'objective_type' => 'custom',
            'name' => 'Hold the centre',
            'description' => null,
            'priority' => 50,
            'starts_at' => null,
            'ends_at' => null,
            'status' => 'completed',
            'sort_order' => 0,
            'metadata' => null,
            'created_by_player_id' => $owner->id,
            'updated_by_player_id' => $owner->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $history = $this->app->make(EventAllianceHistoryQuery::class)->forAlliance($owner, $alliance);

        self::assertCount(1, $history);
        self::assertSame(1, $history[0]['evidence']['attendance']['total']);
        self::assertSame(1, $history[0]['evidence']['attendance']['byStatus']['present']);
        self::assertSame(0, $history[0]['evidence']['roster']['total']);
        self::assertSame(0, $history[0]['evidence']['rallies']['total']);
        self::assertSame(1, $history[0]['evidence']['objectives']['total']);
        self::assertSame(1, $history[0]['evidence']['objectives']['byStatus']['completed']);
        self::assertSame(0, $history[0]['evidence']['objectives']['assignments']);
    }
}
