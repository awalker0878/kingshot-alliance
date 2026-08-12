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
use App\Domain\Kingdoms\Actions\StartTrackingKingdomAlliance;
use App\Domain\Kingdoms\Models\KingdomAllianceObservation;
use App\Domain\Kingdoms\Models\KingdomIntelligenceShare;
use App\Domain\Kingdoms\Models\TrackedKingdomAlliance;
use App\Domain\Kingdoms\Queries\SharedKingdomIntelligenceCurrentQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class KingdomSharedIntelligenceIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipient_read_creates_no_local_canonical_copy_and_cannot_reshare_source_tracking(): void
    {
        [$sourceOwner, $source] = $this->ownerAlliance('No Copy Source', 'no-copy-source', 7606);
        [$recipientOwner, $recipient, $recipientSession] = $this->ownerAllianceWithSession('No Copy Recipient', 'no-copy-recipient', 7606);
        [$otherOwner, $other] = $this->ownerAlliance('No Copy Other', 'no-copy-other', 7606);

        $incoming = $this->activeShare($sourceOwner, $source, $recipientOwner, $recipient);
        $tracking = $this->tracking($sourceOwner, $source, 'ga-7606', 'No Copy Target', 'NCP');
        $this->app->make(RecordKingdomAllianceObservation::class)->handle(
            $source,
            $sourceOwner,
            (string) $tracking->id,
            [
                'observed_name' => 'Shared but source-owned',
                'observed_tag' => 'SRC',
                'power' => '4444',
                'member_count' => 44,
                'captured_at' => now()->subDay()->toIso8601String(),
            ],
        );
        $this->app->make(AddKingdomIntelligenceShareTarget::class)
            ->handle($source, $sourceOwner, (string) $incoming->id, (string) $tracking->id);

        $rows = $this->app->make(SharedKingdomIntelligenceCurrentQuery::class)->forRecipient($recipient);
        self::assertCount(1, $rows);
        self::assertFalse(TrackedKingdomAlliance::query()->where('alliance_id', $recipient->id)->exists());
        self::assertFalse(KingdomAllianceObservation::query()->where('alliance_id', $recipient->id)->exists());

        $outgoing = $this->activeShare($recipientOwner, $recipient, $otherOwner, $other);
        $this->actingAs($recipientOwner)->withSession($recipientSession)
            ->post("/alliance/kingdom-sharing/{$outgoing->id}/targets/{$tracking->id}")
            ->assertNotFound();

        self::assertSame([], $this->app->make(SharedKingdomIntelligenceCurrentQuery::class)->forRecipient($other));
    }

    public function test_explicit_target_without_accepted_observation_is_shared_as_missing_not_zero(): void
    {
        [$sourceOwner, $source] = $this->ownerAlliance('Missing Source', 'missing-source', 7607);
        [$recipientOwner, $recipient] = $this->ownerAlliance('Missing Recipient', 'missing-recipient', 7607);
        $share = $this->activeShare($sourceOwner, $source, $recipientOwner, $recipient);
        $tracking = $this->tracking($sourceOwner, $source, 'ga-7607', 'Missing Target', 'MIS');
        $this->app->make(AddKingdomIntelligenceShareTarget::class)
            ->handle($source, $sourceOwner, (string) $share->id, (string) $tracking->id);

        $rows = $this->app->make(SharedKingdomIntelligenceCurrentQuery::class)->forRecipient($recipient);

        self::assertCount(1, $rows);
        self::assertSame('missing', $rows[0]['freshness']);
        self::assertNull($rows[0]['latestObservation']);
        self::assertSame(['name' => 'Missing Target', 'tag' => 'MIS'], $rows[0]['gameAlliance']);
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

    /** @return array{0: User, 1: Alliance} */
    private function ownerAlliance(string $name, string $slug, int $kingdom): array
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, $name, $slug, $kingdom);

        return [$owner, $alliance];
    }

    /** @return array{0: User, 1: Alliance, 2: array<string, mixed>} */
    private function ownerAllianceWithSession(string $name, string $slug, int $kingdom): array
    {
        [$owner, $alliance] = $this->ownerAlliance($name, $slug, $kingdom);

        return [$owner, $alliance, [
            (string) config('identity.active_alliance_session_key') => $alliance->id,
            'auth.password_confirmed_at' => time(),
        ]];
    }
}
