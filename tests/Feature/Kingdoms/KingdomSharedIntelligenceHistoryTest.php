<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Actions\AcceptKingdomIntelligenceShareInvitation;
use App\Domain\Kingdoms\Actions\AddKingdomIntelligenceShareTarget;
use App\Domain\Kingdoms\Actions\CreateKingdomIntelligenceShareInvitation;
use App\Domain\Kingdoms\Actions\RecordKingdomAllianceObservation;
use App\Domain\Kingdoms\Actions\RemoveKingdomIntelligenceShareTarget;
use App\Domain\Kingdoms\Actions\RevokeKingdomIntelligenceShare;
use App\Domain\Kingdoms\Actions\StartTrackingKingdomAlliance;
use App\Domain\Kingdoms\Actions\UpdateAllianceKingdom;
use App\Domain\Kingdoms\Models\KingdomAllianceObservation;
use App\Domain\Kingdoms\Models\KingdomIntelligenceShare;
use App\Domain\Kingdoms\Models\TrackedKingdomAlliance;
use App\Domain\Kingdoms\Queries\SharedKingdomIntelligenceHistoryQuery;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class KingdomSharedIntelligenceHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_is_safe_accepted_bounded_paginated_and_cursor_is_target_bound(): void
    {
        $asOf = now()->startOfSecond();
        [$sourceOwner, $source] = $this->ownerAlliance('History Source', 'history-source', 7610);
        [$recipientOwner, $recipient] = $this->ownerAlliance('History Recipient', 'history-recipient', 7610);
        $share = $this->activeShare($sourceOwner, $source, $recipientOwner, $recipient);
        $tracking = $this->tracking($sourceOwner, $source, 'ga-7610-a', 'History Target', 'HIS');
        $target = $this->app->make(AddKingdomIntelligenceShareTarget::class)
            ->handle($source, $sourceOwner, (string) $share->id, (string) $tracking->id);

        $oldest = $this->observation($sourceOwner, $source, $tracking, 'Oldest', 'O1', '100', 10, $asOf->copy()->subDays(5));
        $corrected = $this->observation($sourceOwner, $source, $tracking, 'Incorrect', 'BAD', '200', 20, $asOf->copy()->subDays(4));
        $middle = $this->observation($sourceOwner, $source, $tracking, 'Middle', 'MID', '300', 30, $asOf->copy()->subDays(3));
        $replacement = $this->app->make(RecordKingdomAllianceObservation::class)->handle(
            $source,
            $sourceOwner,
            (string) $tracking->id,
            [
                'observed_name' => 'Corrected',
                'observed_tag' => 'FIX',
                'power' => '400',
                'member_count' => 40,
                'captured_at' => $asOf->copy()->subDays(2)->toIso8601String(),
                'corrects_observation_id' => (string) $corrected->id,
                'correction_reason' => 'PRIVATE CORRECTION REASON',
            ],
        );
        $latest = $this->observation($sourceOwner, $source, $tracking, 'Latest', 'NEW', '500', 50, $asOf->copy()->subDay());

        self::assertNotNull($corrected->refresh()->invalidated_at);

        $query = $this->app->make(SharedKingdomIntelligenceHistoryQuery::class);
        $first = $query->forRecipientTarget($recipient, (string) $target->id, pageSize: 2, asOf: $asOf);

        self::assertSame((string) $target->id, $first['shareTargetId']);
        self::assertSame(['id', 'name'], array_keys($first['sourceAlliance']));
        self::assertSame(['name', 'tag'], array_keys($first['gameAlliance']));
        self::assertCount(2, $first['items']);
        self::assertSame(['Latest', 'Corrected'], array_column($first['items'], 'observedName'));
        self::assertNotNull($first['nextCursor']);
        self::assertStringNotContainsString((string) $target->id, $first['nextCursor']);

        foreach ($first['items'] as $item) {
            self::assertSame(
                ['observedName', 'observedTag', 'power', 'memberCount', 'capturedAt', 'freshness'],
                array_keys($item),
            );
        }

        $second = $query->forRecipientTarget(
            $recipient,
            (string) $target->id,
            cursor: $first['nextCursor'],
            pageSize: 2,
        );
        self::assertSame(['Middle', 'Oldest'], array_column($second['items'], 'observedName'));
        self::assertNull($second['nextCursor']);

        $encoded = json_encode([$first, $second], JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('Incorrect', $encoded);
        self::assertStringNotContainsString('PRIVATE CORRECTION REASON', $encoded);
        foreach ([
            (string) $tracking->id,
            (string) $oldest->id,
            (string) $middle->id,
            (string) $replacement->id,
            (string) $latest->id,
        ] as $privateId) {
            self::assertStringNotContainsString($privateId, $encoded);
        }
        foreach (['actor_user_id', 'corrects_observation_id', 'invalidation_reason', 'source_adapter_key'] as $privateField) {
            self::assertStringNotContainsString($privateField, $encoded);
        }

        $secondTracking = $this->tracking($sourceOwner, $source, 'ga-7610-b', 'Second Target', 'SEC');
        $secondTarget = $this->app->make(AddKingdomIntelligenceShareTarget::class)
            ->handle($source, $sourceOwner, (string) $share->id, (string) $secondTracking->id);

        $this->assertInvalidCursor(fn () => $query->forRecipientTarget(
            $recipient,
            (string) $secondTarget->id,
            cursor: $first['nextCursor'],
            pageSize: 2,
        ));

        self::assertFalse(TrackedKingdomAlliance::query()->where('alliance_id', $recipient->id)->exists());
        self::assertFalse(KingdomAllianceObservation::query()->where('alliance_id', $recipient->id)->exists());
    }

    public function test_history_fails_closed_after_target_remove_share_revoke_and_kingdom_drift(): void
    {
        [$sourceOwner, $source] = $this->ownerAlliance('History Boundary Source', 'history-boundary-source', 7611);
        [$recipientOwner, $recipient] = $this->ownerAlliance('History Boundary Recipient', 'history-boundary-recipient', 7611);
        $share = $this->activeShare($sourceOwner, $source, $recipientOwner, $recipient);
        $tracking = $this->tracking($sourceOwner, $source, 'ga-7611', 'Boundary Target', 'BND');
        $this->observation($sourceOwner, $source, $tracking, 'Boundary', 'BND', '100', 10, now()->subDay());
        $target = $this->app->make(AddKingdomIntelligenceShareTarget::class)
            ->handle($source, $sourceOwner, (string) $share->id, (string) $tracking->id);
        $query = $this->app->make(SharedKingdomIntelligenceHistoryQuery::class);

        self::assertCount(1, $query->forRecipientTarget($recipient, (string) $target->id)['items']);

        $this->app->make(RemoveKingdomIntelligenceShareTarget::class)
            ->handle($source, $sourceOwner, (string) $share->id, (string) $target->id);
        $this->assertHistoryNotFound(fn () => $query->forRecipientTarget($recipient, (string) $target->id));

        $target = $this->app->make(AddKingdomIntelligenceShareTarget::class)
            ->handle($source, $sourceOwner, (string) $share->id, (string) $tracking->id);
        $this->app->make(RevokeKingdomIntelligenceShare::class)
            ->handle($source, $sourceOwner, (string) $share->id);
        $this->assertHistoryNotFound(fn () => $query->forRecipientTarget($recipient, (string) $target->id));

        $replacementShare = $this->activeShare($sourceOwner, $source, $recipientOwner, $recipient);
        $replacementTarget = $this->app->make(AddKingdomIntelligenceShareTarget::class)
            ->handle($source, $sourceOwner, (string) $replacementShare->id, (string) $tracking->id);
        self::assertCount(1, $query->forRecipientTarget($recipient, (string) $replacementTarget->id)['items']);

        $this->app->make(UpdateAllianceKingdom::class)->handle($recipient, $recipientOwner, 7612);
        $this->assertHistoryNotFound(fn () => $query->forRecipientTarget($recipient, (string) $replacementTarget->id));

        $this->app->make(UpdateAllianceKingdom::class)->handle($recipient, $recipientOwner, 7611);
        $this->assertHistoryNotFound(fn () => $query->forRecipientTarget($recipient, (string) $replacementTarget->id));
    }

    public function test_history_window_stops_at_250_and_each_page_uses_at_most_two_selects(): void
    {
        $asOf = now()->startOfSecond();
        [$sourceOwner, $source] = $this->ownerAlliance('History Limit Source', 'history-limit-source', 7613);
        [$recipientOwner, $recipient] = $this->ownerAlliance('History Limit Recipient', 'history-limit-recipient', 7613);
        $share = $this->activeShare($sourceOwner, $source, $recipientOwner, $recipient);
        $tracking = $this->tracking($sourceOwner, $source, 'ga-7613', 'Limit Target', 'LIM');
        $target = $this->app->make(AddKingdomIntelligenceShareTarget::class)
            ->handle($source, $sourceOwner, (string) $share->id, (string) $tracking->id);

        for ($index = 1; $index <= 260; $index++) {
            KingdomAllianceObservation::query()->create([
                'alliance_id' => $source->id,
                'tracked_kingdom_alliance_id' => $tracking->id,
                'kingdom_alliance_id' => $tracking->kingdom_alliance_id,
                'actor_user_id' => $sourceOwner->id,
                'observed_name' => 'Historical '.$index,
                'observed_tag' => 'H'.$index,
                'power' => 1000 + $index,
                'member_count' => 10 + ($index % 100),
                'captured_at' => $asOf->copy()->subMinutes($index),
                'source' => 'manual',
                'idempotency_key' => hash('sha256', 'history-limit-'.$index),
            ]);
        }

        $query = $this->app->make(SharedKingdomIntelligenceHistoryQuery::class);
        $cursor = null;
        $seen = 0;
        $pages = 0;

        do {
            DB::connection()->flushQueryLog();
            DB::connection()->enableQueryLog();
            $page = $query->forRecipientTarget(
                $recipient,
                (string) $target->id,
                cursor: $cursor,
                pageSize: 50,
                asOf: $asOf,
            );
            $queries = DB::connection()->getQueryLog();
            DB::connection()->disableQueryLog();

            $selects = array_values(array_filter(
                $queries,
                static fn (array $query): bool => preg_match('/^\s*select\b/i', (string) $query['query']) === 1,
            ));

            self::assertLessThanOrEqual(2, count($selects));
            self::assertLessThanOrEqual(50, count($page['items']));
            $seen += count($page['items']);
            $pages++;
            $cursor = $page['nextCursor'];
        } while ($cursor !== null);

        self::assertSame(250, $seen);
        self::assertSame(5, $pages);
        self::assertSame(SharedKingdomIntelligenceHistoryQuery::HISTORY_LIMIT, $seen);
    }

    private function activeShare(
        User $sourceOwner,
        Alliance $source,
        User $recipientOwner,
        Alliance $recipient,
    ): KingdomIntelligenceShare {
        $issued = $this->app->make(CreateKingdomIntelligenceShareInvitation::class)->handle($source, $sourceOwner);

        return $this->app->make(AcceptKingdomIntelligenceShareInvitation::class)
            ->handle($recipient, $recipientOwner, $issued->token);
    }

    private function tracking(
        User $owner,
        Alliance $source,
        string $gameAllianceId,
        string $name,
        string $tag,
    ): TrackedKingdomAlliance {
        return $this->app->make(StartTrackingKingdomAlliance::class)->handle($source, $owner, [
            'game_alliance_id' => $gameAllianceId,
            'current_name' => $name,
            'current_tag' => $tag,
        ]);
    }

    private function observation(
        User $owner,
        Alliance $source,
        TrackedKingdomAlliance $tracking,
        string $name,
        string $tag,
        string $power,
        int $memberCount,
        Carbon $capturedAt,
    ): KingdomAllianceObservation {
        return $this->app->make(RecordKingdomAllianceObservation::class)->handle(
            $source,
            $owner,
            (string) $tracking->id,
            [
                'observed_name' => $name,
                'observed_tag' => $tag,
                'power' => $power,
                'member_count' => $memberCount,
                'captured_at' => $capturedAt->toIso8601String(),
            ],
        );
    }

    /** @return array{0: User, 1: Alliance} */
    private function ownerAlliance(string $name, string $slug, int $kingdom): array
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, $name, $slug, $kingdom);

        return [$owner, $alliance];
    }

    /** @param callable(): mixed $callback */
    private function assertHistoryNotFound(callable $callback): void
    {
        try {
            $callback();
            self::fail('Expected shared history to fail closed.');
        } catch (ModelNotFoundException) {
            self::assertTrue(true);
        }
    }

    /** @param callable(): mixed $callback */
    private function assertInvalidCursor(callable $callback): void
    {
        try {
            $callback();
            self::fail('Expected a target-bound history cursor failure.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('cursor', $exception->errors());
        }
    }
}
