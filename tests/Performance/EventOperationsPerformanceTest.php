<?php

declare(strict_types=1);

namespace Tests\Performance;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Events\Actions\CreateEvent;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Models\EventType;
use App\Domain\Events\Queries\EventAttentionQuery;
use App\Domain\Events\Services\EventTypeRegistry;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\Player;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class EventOperationsPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_attention_query_count_is_bounded_as_upcoming_events_grow(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 9910, 'status' => 'active']);
        $player = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'attention-scale-player',
            'current_name' => 'Scale Player',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($player, 'Attention Scale', 'attention-scale');

        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $create = $this->app->make(CreateEvent::class);

        for ($index = 0; $index < 25; $index++) {
            $create->handle(
                actor: $player,
                configuration: $configuration,
                target: $alliance,
                firstLocalStart: CarbonImmutable::now('UTC')->addHours(12 + $index),
                durationMinutes: 60,
            );
        }

        $selects = 0;
        DB::listen(static function (QueryExecuted $query) use (&$selects): void {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $selects++;
            }
        });

        $items = $this->app->make(EventAttentionQuery::class)->for($player, days: 7);

        self::assertGreaterThanOrEqual(25, count($items));
        self::assertLessThanOrEqual(30, $selects, 'Attention queries must remain bounded rather than scaling per occurrence.');
    }
}
