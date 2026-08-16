<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\KingdomIntelligenceShare;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Kingdoms\Enums\KingdomIntelligenceShareState;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class KingdomIntelligenceSharingFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_creates_hash_only_bounded_invitation_without_observation_sharing(): void
    {
        [$owner, $ownerPlayer, $source, $session] = $this->ownerAlliance('K5 Source', 'k5-source', 7501);

        $response = $this->actingAs($owner)->withSession($session)
            ->postJson('/alliance/kingdom-sharing/invitations');

        $response->assertCreated()->assertJsonStructure(['shareId', 'token']);
        $token = (string) $response->json('token');
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $token);

        $share = KingdomIntelligenceShare::query()->sole();
        self::assertSame($source->id, $share->source_alliance_id);
        self::assertNull($share->recipient_alliance_id);
        self::assertSame($source->kingdom_id, $share->kingdom_id);
        self::assertSame(KingdomIntelligenceShareState::Pending, $share->state);
        self::assertSame(hash('sha256', $token), $share->invitation_token_hash);
        self::assertNotSame($token, $share->invitation_token_hash);
        self::assertTrue($share->invitation_expires_at->isFuture());
        self::assertLessThanOrEqual(72 * 60 + 1, now()->diffInMinutes($share->invitation_expires_at));
        self::assertArrayNotHasKey('invitation_token_hash', $share->toArray());

        self::assertTrue(Schema::hasTable('kingdom_intelligence_shares'));
        self::assertTrue(Schema::hasTable('kingdom_intelligence_share_targets'));
        $this->assertDatabaseCount('kingdom_intelligence_share_targets', 0);
        foreach (['observation_id', 'payload', 'normalized_payload', 'adapter_key', 'source_record_id', 'manager_notes'] as $column) {
            self::assertFalse(Schema::hasColumn('kingdom_intelligence_shares', $column));
        }

        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $source->id,
            'actor_player_id' => $ownerPlayer->id,
            'event' => 'kingdoms.shared_intelligence_invitation_created',
        ]);
        $this->assertDatabaseHas('outbox_messages', [
            'alliance_id' => $source->id,
            'event_type' => 'kingdoms.shared_intelligence_invitation_created',
        ]);

        $outbox = OutboxMessage::query()
            ->where('event_type', 'kingdoms.shared_intelligence_invitation_created')
            ->latest('occurred_at')
            ->firstOrFail();
        self::assertStringNotContainsString($token, json_encode($outbox->payload, JSON_THROW_ON_ERROR));
        self::assertFalse(Route::has('alliance.kingdom-sharing.observations.index'));
    }

    public function test_consent_mutations_require_recent_password_and_kingdom_manage(): void
    {
        [$owner, $ownerPlayer, $source] = $this->ownerAlliance('K5 Password', 'k5-password', 7502);
        $staleSession = [
            ...$this->activeSession($ownerPlayer->id),
            'auth.password_confirmed_at' => 0,
        ];

        $this->actingAs($owner)->withSession($staleSession)
            ->post('/alliance/kingdom-sharing/invitations')
            ->assertRedirect(route('password.confirm'));
        self::assertSame(0, KingdomIntelligenceShare::query()->count());

        [$memberUser, $memberPlayer] = $this->member($source);
        $this->actingAs($memberUser)->withSession($this->confirmedSession($memberPlayer->id))
            ->postJson('/alliance/kingdom-sharing/invitations')
            ->assertForbidden();
        self::assertSame(0, KingdomIntelligenceShare::query()->count());
    }

    public function test_same_kingdom_recipient_accepts_once_and_duplicate_or_self_share_fails_closed(): void
    {
        [$sourceOwner, $sourcePlayer, $source, $sourceSession] = $this->ownerAlliance('K5 Source Accept', 'k5-source-accept', 7503);
        [$recipientOwner, $recipientPlayer, $recipient, $recipientSession] = $this->ownerAlliance('K5 Recipient Accept', 'k5-recipient-accept', 7503);

        $token = $this->issueViaHttp($sourceOwner, $sourceSession);

        $this->actingAs($recipientOwner)->withSession($recipientSession)
            ->post('/alliance/kingdom-sharing/invitations/accept', ['token' => $token])
            ->assertRedirect();

        $share = KingdomIntelligenceShare::query()->sole();
        self::assertSame(KingdomIntelligenceShareState::Active, $share->state);
        self::assertSame($source->id, $share->source_alliance_id);
        self::assertSame($recipient->id, $share->recipient_alliance_id);
        self::assertNotNull($share->accepted_at);
        self::assertNull($share->invitation_token_hash);
        self::assertNotNull($share->invitation_used_at);

        $this->actingAs($recipientOwner)->withSession($recipientSession)
            ->post('/alliance/kingdom-sharing/invitations/accept', ['token' => $token])
            ->assertRedirect()
            ->assertSessionHasErrors('token');
        self::assertSame(1, KingdomIntelligenceShare::query()->count());

        $selfToken = $this->issueViaHttp($sourceOwner, $sourceSession);
        $this->actingAs($sourceOwner)->withSession($sourceSession)
            ->post('/alliance/kingdom-sharing/invitations/accept', ['token' => $selfToken])
            ->assertRedirect()
            ->assertSessionHasErrors('sharing');
        self::assertSame(2, KingdomIntelligenceShare::query()->count());
    }

    public function test_wrong_kingdom_acceptance_fails_closed_without_binding_recipient(): void
    {
        [$sourceOwner, $sourcePlayer, $source, $sourceSession] = $this->ownerAlliance('K5 Source Kingdom', 'k5-source-kingdom', 7504);
        [$recipientOwner, $recipientPlayer, $recipient, $recipientSession] = $this->ownerAlliance('K5 Other Kingdom', 'k5-other-kingdom', 7505);

        $token = $this->issueViaHttp($sourceOwner, $sourceSession);

        $this->actingAs($recipientOwner)->withSession($recipientSession)
            ->post('/alliance/kingdom-sharing/invitations/accept', ['token' => $token])
            ->assertRedirect()
            ->assertSessionHasErrors('sharing');

        $share = KingdomIntelligenceShare::query()->sole();
        self::assertSame(KingdomIntelligenceShareState::Pending, $share->state);
        self::assertNull($share->recipient_alliance_id);
        self::assertNull($share->accepted_at);
        self::assertSame($source->kingdom_id, $share->kingdom_id);
        self::assertNotSame($source->kingdom_id, $recipient->kingdom_id);
    }

    public function test_invitation_decline_revoke_and_recipient_leave_terminate_the_capability(): void
    {
        [$sourceOwner, $sourcePlayer, $source, $sourceSession] = $this->ownerAlliance('K5 Source Lifecycle', 'k5-source-life', 7506);
        [$recipientOwner, $recipientPlayer, $recipient, $recipientSession] = $this->ownerAlliance('K5 Recipient Lifecycle', 'k5-recipient-life', 7506);

        $declineToken = $this->issueViaHttp($sourceOwner, $sourceSession);
        $declineShare = $this->shareForToken($declineToken);
        $this->actingAs($recipientOwner)->withSession($recipientSession)
            ->post('/alliance/kingdom-sharing/invitations/decline', ['token' => $declineToken])
            ->assertRedirect();
        $declineShare->refresh();
        self::assertSame(KingdomIntelligenceShareState::Declined, $declineShare->state);
        self::assertNull($declineShare->invitation_token_hash);

        $revokeToken = $this->issueViaHttp($sourceOwner, $sourceSession);
        $revokeShare = $this->shareForToken($revokeToken);
        $this->actingAs($sourceOwner)->withSession($sourceSession)
            ->post('/alliance/kingdom-sharing/'.$revokeShare->id.'/revoke')
            ->assertRedirect();
        $revokeShare->refresh();
        self::assertSame(KingdomIntelligenceShareState::Revoked, $revokeShare->state);
        self::assertNull($revokeShare->invitation_token_hash);

        $leaveToken = $this->issueViaHttp($sourceOwner, $sourceSession);
        $leaveShare = $this->shareForToken($leaveToken);
        $this->actingAs($recipientOwner)->withSession($recipientSession)
            ->post('/alliance/kingdom-sharing/invitations/accept', ['token' => $leaveToken])
            ->assertRedirect();
        $leaveShare->refresh();
        self::assertSame(KingdomIntelligenceShareState::Active, $leaveShare->state);
        self::assertNull($leaveShare->invitation_token_hash);
        $this->actingAs($recipientOwner)->withSession($recipientSession)
            ->post('/alliance/kingdom-sharing/'.$leaveShare->id.'/leave')
            ->assertRedirect();
        $leaveShare->refresh();
        self::assertSame(KingdomIntelligenceShareState::Declined, $leaveShare->state);
        self::assertNull($leaveShare->invitation_token_hash);
    }

    public function test_membership_and_kingdom_context_changes_fail_closed(): void
    {
        [$sourceOwner, $sourcePlayer, $source, $sourceSession] = $this->ownerAlliance('K5 Source Context', 'k5-source-context', 7507);
        [$recipientOwner, $recipientPlayer, $recipient, $recipientSession] = $this->ownerAlliance('K5 Recipient Context', 'k5-recipient-context', 7507);

        $token = $this->issueViaHttp($sourceOwner, $sourceSession);
        $recipientMembership = AllianceMembership::query()
            ->where('alliance_id', $recipient->id)
            ->where('user_id', $recipientOwner->id)
            ->firstOrFail();
        $recipientMembership->forceFill(['status' => MembershipStatus::Suspended])->save();

        $this->actingAs($recipientOwner)->withSession($recipientSession)
            ->post('/alliance/kingdom-sharing/invitations/accept', ['token' => $token])
            ->assertForbidden();

        $recipientMembership->forceFill(['status' => MembershipStatus::Active])->save();
        $recipient->forceFill(['kingdom_id' => Kingdom::query()->firstOrCreate(['number' => 7508])->id])->save();
        $this->actingAs($recipientOwner)->withSession($recipientSession)
            ->post('/alliance/kingdom-sharing/invitations/accept', ['token' => $token])
            ->assertRedirect()
            ->assertSessionHasErrors('sharing');
    }

    private function issueViaHttp(User $owner, array $session): string
    {
        $response = $this->actingAs($owner)->withSession($session)
            ->postJson('/alliance/kingdom-sharing/invitations')
            ->assertCreated();

        return (string) $response->json('token');
    }

    private function shareForToken(string $token): KingdomIntelligenceShare
    {
        return KingdomIntelligenceShare::query()
            ->where('invitation_token_hash', hash('sha256', $token))
            ->sole();
    }

    /** @return array{0: User, 1: Player, 2: Alliance, 3: array<string, mixed>} */
    private function ownerAlliance(string $name, string $slug, int $kingdomNumber): array
    {
        $ownerUser = User::factory()->create();
        $kingdom = Kingdom::query()->firstOrCreate(
            ['number' => $kingdomNumber],
            ['status' => 'active'],
        );
        $ownerPlayer = $this->player($ownerUser, $kingdom, $slug.'-r5', $name.' R5');
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, $name, $slug);

        return [$ownerUser, $ownerPlayer, $alliance, $this->confirmedSession($ownerPlayer->id)];
    }

    private function player(User $user, Kingdom $kingdom, string $gamePlayerId, string $name): Player
    {
        return Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => $gamePlayerId,
            'current_name' => $name,
        ]);
    }

    /** @return array{0: User, 1: Player} */
    private function member(Alliance $alliance): array
    {
        $memberUser = User::factory()->create();
        $kingdom = Kingdom::query()->findOrFail($alliance->kingdom_id);
        $memberPlayer = $this->player(
            $memberUser,
            $kingdom,
            'sharing-member-'.$memberUser->id,
            'Sharing Member',
        );
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $memberPlayer->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);

        return [$memberUser, $memberPlayer];
    }

    /** @return array<string, mixed> */
    private function activeSession(string $playerId): array
    {
        return [(string) config('game_world.active_player_session_key') => $playerId];
    }

    /** @return array<string, mixed> */
    private function confirmedSession(string $playerId): array
    {
        return [
            ...$this->activeSession($playerId),
            'auth.password_confirmed_at' => time(),
        ];
    }
}
