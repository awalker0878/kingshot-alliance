<?php

declare(strict_types=1);

namespace Tests\Performance;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\KingdomIntelligenceShare;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Kingdoms\Actions\AcceptKingdomIntelligenceShareInvitation;
use App\Domain\Kingdoms\Actions\CreateKingdomIntelligenceShareInvitation;
use App\Domain\Kingdoms\Queries\SharedKingdomIntelligenceCurrentQuery;
use App\Domain\Kingdoms\Queries\SharedKingdomIntelligenceHistoryQuery;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class KingdomSharedIntelligenceCapacityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_current_and_history_reads_stay_bounded_at_realistic_shared_intelligence_volume(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-12 12:00:00 UTC'));
        [$sourceOwner, $source] = $this->ownerAlliance('Capacity Source', 'capacity-source', 7640);
        [$recipientOwner, $recipient] = $this->ownerAlliance('Capacity Recipient', 'capacity-recipient', 7640);
        $share = $this->activeShare($sourceOwner, $source, $recipientOwner, $recipient);
        self::assertNotNull($source->kingdom_id);

        $firstTargetId = '';
        $firstTrackingId = '';
        $firstReferenceId = '';
        $references = [];
        $trackings = [];
        $targets = [];
        $observations = [];
        $now = now();

        for ($index = 1; $index <= 300; $index++) {
            $referenceId = (string) Str::ulid();
            $trackingId = (string) Str::ulid();
            $targetId = (string) Str::ulid();
            $observationId = (string) Str::ulid();

            if ($index === 1) {
                $firstReferenceId = $referenceId;
                $firstTrackingId = $trackingId;
                $firstTargetId = $targetId;
            }

            $references[] = [
                'id' => $referenceId,
                'kingdom_id' => $source->kingdom_id,
                'game_alliance_id' => 'shared-capacity-'.$index,
                'current_name' => sprintf('Shared Capacity %03d', $index),
                'current_tag' => sprintf('S%03d', $index),
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $trackings[] = [
                'id' => $trackingId,
                'alliance_id' => $source->id,
                'kingdom_alliance_id' => $referenceId,
                'kingdom_id' => $source->kingdom_id,
                'state' => 'active',
                'manager_notes' => null,
                'archived_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $targets[] = [
                'id' => $targetId,
                'kingdom_intelligence_share_id' => $share->id,
                'tracked_kingdom_alliance_id' => $trackingId,
                'state' => 'active',
                'shared_by_player_id' => Player::query()->where('user_id', $sourceOwner->id)->sole()->id,
                'shared_at' => $now,
                'removed_by_player_id' => null,
                'removed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $observations[] = $this->observationRow(
                $observationId,
                $source,
                $sourceOwner,
                $trackingId,
                $referenceId,
                sprintf('Shared Capacity %03d', $index),
                $now->copy()->subMinute(),
                'current-'.$index,
            );
        }

        foreach (array_chunk($references, 100) as $rows) {
            DB::table('kingdom_alliances')->insert($rows);
        }
        foreach (array_chunk($trackings, 100) as $rows) {
            DB::table('tracked_kingdom_alliances')->insert($rows);
        }
        foreach (array_chunk($targets, 100) as $rows) {
            DB::table('kingdom_intelligence_share_targets')->insert($rows);
        }
        foreach (array_chunk($observations, 100) as $rows) {
            DB::table('kingdom_alliance_observations')->insert($rows);
        }

        $historyRows = [];
        for ($index = 1; $index <= 999; $index++) {
            $historyRows[] = $this->observationRow(
                (string) Str::ulid(),
                $source,
                $sourceOwner,
                $firstTrackingId,
                $firstReferenceId,
                'Shared Capacity 001',
                $now->copy()->subHours($index + 1),
                'history-'.$index,
            );
        }
        foreach (array_chunk($historyRows, 100) as $rows) {
            DB::table('kingdom_alliance_observations')->insert($rows);
        }

        $selectQueries = 0;
        $collectQueries = false;
        DB::listen(static function (QueryExecuted $query) use (&$selectQueries, &$collectQueries): void {
            if ($collectQueries && str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $selectQueries++;
            }
        });

        $collectQueries = true;
        $current = $this->app->make(SharedKingdomIntelligenceCurrentQuery::class)
            ->forRecipient($recipient, $now);
        $collectQueries = false;

        self::assertCount(SharedKingdomIntelligenceCurrentQuery::CURRENT_LIMIT, $current);
        self::assertLessThanOrEqual(
            2,
            $selectQueries,
            'Current shared intelligence must stay at two batched SELECTs at and beyond its 250-target cap.',
        );
        self::assertLessThanOrEqual(
            160_000,
            strlen(json_encode($current, JSON_THROW_ON_ERROR)),
            'The bounded current-fact projection must stay within its reviewed response-size ceiling.',
        );
        self::assertSame(0, DB::table('kingdom_alliance_observations')->where('alliance_id', $recipient->id)->count());

        $historyQuery = $this->app->make(SharedKingdomIntelligenceHistoryQuery::class);
        $cursor = null;
        $historySelectQueries = 0;
        $historyCount = 0;

        for ($pageNumber = 1; $pageNumber <= 5; $pageNumber++) {
            $selectQueries = 0;
            $collectQueries = true;
            $page = $historyQuery->forRecipientTarget($recipient, $firstTargetId, $cursor, 50, $now);
            $collectQueries = false;

            $historySelectQueries += $selectQueries;
            $historyCount += count($page['items']);
            self::assertCount(50, $page['items']);
            self::assertLessThanOrEqual(
                2,
                $selectQueries,
                'Each shared-history page must remain one authorization SELECT plus one bounded observation SELECT.',
            );
            self::assertLessThanOrEqual(
                50_000,
                strlen(json_encode($page, JSON_THROW_ON_ERROR)),
                'Each history page must stay within its reviewed response-size ceiling.',
            );
            $cursor = $page['nextCursor'];
        }

        self::assertSame(SharedKingdomIntelligenceHistoryQuery::HISTORY_LIMIT, $historyCount);
        self::assertSame(10, $historySelectQueries);
        self::assertNull($cursor);
        self::assertSame(1000, DB::table('kingdom_alliance_observations')->where('tracked_kingdom_alliance_id', $firstTrackingId)->count());
        self::assertSame(0, DB::table('kingdom_alliance_observations')->where('alliance_id', $recipient->id)->count());
    }

    /** @return array<string, mixed> */
    private function observationRow(
        string $id,
        Alliance $source,
        User $sourceOwner,
        string $trackingId,
        string $referenceId,
        string $name,
        Carbon $capturedAt,
        string $key,
    ): array {
        return [
            'id' => $id,
            'alliance_id' => $source->id,
            'tracked_kingdom_alliance_id' => $trackingId,
            'kingdom_alliance_id' => $referenceId,
            'actor_player_id' => Player::query()->where('user_id', $sourceOwner->id)->sole()->id,
            'observed_name' => $name,
            'observed_tag' => 'CAP',
            'power' => 123456789,
            'member_count' => 88,
            'captured_at' => $capturedAt,
            'source' => 'manual',
            'idempotency_key' => hash('sha256', $trackingId.'|'.$key),
            'corrects_observation_id' => null,
            'invalidated_at' => null,
            'invalidated_by_player_id' => null,
            'invalidation_reason' => null,
            'created_at' => $capturedAt,
            'updated_at' => $capturedAt,
        ];
    }

    private function activeShare(
        User $sourceOwner,
        Alliance $source,
        User $recipientOwner,
        Alliance $recipient,
    ): KingdomIntelligenceShare {
        $issued = $this->app->make(CreateKingdomIntelligenceShareInvitation::class)
            ->handle($source, Player::query()->where('user_id', $sourceOwner->id)->sole());

        return $this->app->make(AcceptKingdomIntelligenceShareInvitation::class)
            ->handle($recipient, Player::query()->where('user_id', $recipientOwner->id)->sole(), $issued->token);
    }

    /** @return array{User, Alliance} */
    private function ownerAlliance(string $name, string $slug, int $kingdom): array
    {
        $owner = User::factory()->create();
        $kingdomModel = Kingdom::query()->firstOrCreate(
            ['number' => $kingdom],
            ['status' => 'active'],
        );
        $player = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdomModel->id,
            'game_player_id' => 'owner-'.$slug,
            'current_name' => $name.' Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($player, $name, $slug);

        return [$owner, $alliance];
    }
}
