<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\KingdomIntelligenceShare;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Observations\Models\KingdomAllianceObservation;
use App\ReadModels\SharedKingdomIntelligence\SharedKingdomIntelligenceCurrentQuery;
use App\Workflows\KingdomTransfer\Actions\AcceptKingdomIntelligenceShareInvitation;
use App\Workflows\KingdomTransfer\Actions\AddKingdomIntelligenceShareTarget;
use App\Workflows\KingdomTransfer\Actions\CreateKingdomIntelligenceShareInvitation;
use App\Workflows\KingdomTransfer\Actions\RecordKingdomAllianceObservation;
use App\Workflows\KingdomTransfer\Actions\StartTrackingKingdomAlliance;
use App\Workflows\KingdomTransfer\Models\TrackedKingdomAlliance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class KingdomSharedIntelligenceIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipient_read_creates_no_local_canonical_copy_and_cannot_reshare_source_tracking(): void
    {
        [, $sourcePlayer, $source] = $this->ownerAlliance('No Copy Source', 'no-copy-source', 7606);
        [$recipientUser, $recipientPlayer, $recipient, $recipientSession] = $this->ownerAllianceWithSession('No Copy Recipient', 'no-copy-recipient', 7606);
        [, $otherPlayer, $other] = $this->ownerAlliance('No Copy Other', 'no-copy-other', 7606);

        $incoming = $this->activeShare($sourcePlayer, $source, $recipientPlayer, $recipient);
        $tracking = $this->tracking($sourcePlayer, $source, 'ga-7606', 'No Copy Target', 'NCP');
        $this->app->make(RecordKingdomAllianceObservation::class)->handle(
            $source,
            $sourcePlayer,
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
            ->handle($source, $sourcePlayer, (string) $incoming->id, (string) $tracking->id);

        $rows = $this->app->make(SharedKingdomIntelligenceCurrentQuery::class)->forRecipient($recipient);
        self::assertCount(1, $rows);
        self::assertFalse(TrackedKingdomAlliance::query()->where('alliance_id', $recipient->id)->exists());
        self::assertFalse(KingdomAllianceObservation::query()->where('alliance_id', $recipient->id)->exists());

        $outgoing = $this->activeShare($recipientPlayer, $recipient, $otherPlayer, $other);
        $this->actingAs($recipientUser)->withSession($recipientSession)
            ->post("/alliance/kingdom-sharing/{$outgoing->id}/targets/{$tracking->id}")
            ->assertNotFound();

        self::assertSame([], $this->app->make(SharedKingdomIntelligenceCurrentQuery::class)->forRecipient($other));
    }

    public function test_explicit_target_without_accepted_observation_is_shared_as_missing_not_zero(): void
    {
        [, $sourcePlayer, $source] = $this->ownerAlliance('Missing Source', 'missing-source', 7607);
        [, $recipientPlayer, $recipient] = $this->ownerAlliance('Missing Recipient', 'missing-recipient', 7607);
        $share = $this->activeShare($sourcePlayer, $source, $recipientPlayer, $recipient);
        $tracking = $this->tracking($sourcePlayer, $source, 'ga-7607', 'Missing Target', 'MIS');
        $this->app->make(AddKingdomIntelligenceShareTarget::class)
            ->handle($source, $sourcePlayer, (string) $share->id, (string) $tracking->id);

        $rows = $this->app->make(SharedKingdomIntelligenceCurrentQuery::class)->forRecipient($recipient);

        self::assertCount(1, $rows);
        self::assertSame('missing', $rows[0]['freshness']);
        self::assertNull($rows[0]['latestObservation']);
        self::assertSame(['name' => 'Missing Target', 'tag' => 'MIS'], $rows[0]['gameAlliance']);
    }

    private function activeShare(
        Player $sourceActor,
        Alliance $source,
        Player $recipientActor,
        Alliance $recipient,
    ): KingdomIntelligenceShare {
        $issued = $this->app->make(CreateKingdomIntelligenceShareInvitation::class)->handle($source, $sourceActor);

        return $this->app->make(AcceptKingdomIntelligenceShareInvitation::class)
            ->handle($recipient, $recipientActor, $issued->token);
    }

    private function tracking(
        Player $actor,
        Alliance $source,
        string $gameAllianceId,
        string $name,
        string $tag,
    ): TrackedKingdomAlliance {
        return $this->app->make(StartTrackingKingdomAlliance::class)->handle($source, $actor, [
            'game_alliance_id' => $gameAllianceId,
            'current_name' => $name,
            'current_tag' => $tag,
        ]);
    }

    /** @return array{0: User, 1: Player, 2: Alliance} */
    private function ownerAlliance(string $name, string $slug, int $kingdomNumber): array
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->firstOrCreate(['number' => $kingdomNumber], ['status' => 'active']);
        $player = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => $slug.'-owner',
            'current_name' => $name.' Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($player, $name, $slug);

        return [$owner, $player, $alliance];
    }

    /** @return array{0: User, 1: Player, 2: Alliance, 3: array<string, mixed>} */
    private function ownerAllianceWithSession(string $name, string $slug, int $kingdomNumber): array
    {
        [$owner, $player, $alliance] = $this->ownerAlliance($name, $slug, $kingdomNumber);

        return [$owner, $player, $alliance, [
            (string) config('game_world.active_player_session_key') => $player->id,
            'auth.password_confirmed_at' => time(),
        ]];
    }
}
