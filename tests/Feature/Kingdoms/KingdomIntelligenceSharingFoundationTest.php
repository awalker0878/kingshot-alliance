<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Enums\DefaultAllianceRole;
use App\Domain\Authorization\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Actions\CreateKingdomIntelligenceShareInvitation;
use App\Domain\Kingdoms\Enums\KingdomIntelligenceShareState;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\KingdomIntelligenceShare;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Platform\Models\OutboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class KingdomIntelligenceSharingFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_creates_hash_only_bounded_invitation_without_observation_sharing(): void
    {
        [$owner, $source, $session] = $this->ownerAlliance('K5 Source', 'k5-source', 7501);

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
        self::assertFalse(Schema::hasTable('kingdom_intelligence_share_targets'));
        foreach (['observation_id', 'payload', 'normalized_payload', 'adapter_key', 'source_record_id', 'manager_notes'] as $column) {
            self::assertFalse(Schema::hasColumn('kingdom_intelligence_shares', $column));
        }

        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $source->id,
            'actor_user_id' => $owner->id,
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
        self::assertFalse(Route::has('alliance.kingdom-sharing.index'));
        self::assertFalse(Route::has('alliance.kingdom-sharing.observations.index'));
    }

    public function test_consent_mutations_require_recent_password_and_kingdom_manage(): void
    {
        [$owner, $source] = $this->ownerAlliance('K5 Password', 'k5-password', 7502);
        $staleSession = [
            (string) config('identity.active_alliance_session_key') => $source->id,
            'auth.password_confirmed_at' => 0,
        ];

        $this->actingAs($owner)->withSession($staleSession)
            ->post('/alliance/kingdom-sharing/invitations')
            ->assertRedirect(route('password.confirm'));
        self::assertSame(0, KingdomIntelligenceShare::query()->count());

        $member = $this->member($source);
        $this->actingAs($member)->withSession($this->confirmedSession((string) $source->id))
            ->postJson('/alliance/kingdom-sharing/invitations')
            ->assertForbidden();
        self::assertSame(0, KingdomIntelligenceShare::query()->count());
    }

    public function test_same_kingdom_recipient_accepts_once_and_duplicate_or_self_share_fails_closed(): void
    {
        [$sourceOwner, $source, $sourceSession] = $this->ownerAlliance('K5 Source Accept', 'k5-source-accept', 7503);
        [$recipientOwner, $recipient, $recipientSession] = $this->ownerAlliance('K5 Recipient Accept', 'k5-recipient-accept', 7503);

        $token = $this->issueViaHttp($sourceOwner, $sourceSession);

        $this->actingAs($recipientOwner)->withSession($recipientSession)
            ->post('/alliance/kingdom-sharing/invitations/accept', ['token' => $token])
            ->assertRedirect();

        $share = KingdomIntelligenceShare::query()->sole();
        self::assertSame(KingdomIntelligenceShareState::Active, $share->state);
        self::assertSame($source->id, $share->source_alliance_id);
        self::assertSame($recipient->id, $share->recipient_alliance_id);
        self::assertNotNull($share->accepted_at);
        self::assertNotNull($share->invitation_used_at);

        $this->actingAs($recipientOwner)->withSession($recipientSession)
            ->from('/alliance/kingdom-alliances')
            ->post('/alliance/kingdom-sharing/invitations/accept', ['token' => $token])
            ->assertRedirect('/alliance/kingdom-alliances')
            ->assertSessionHasErrors('token');
        self::assertSame(1, KingdomIntelligenceShare::query()->where('state', KingdomIntelligenceShareState::Active->value)->count());

        $secondToken = $this->issueViaHttp($sourceOwner, $sourceSession);
        $this->actingAs($recipientOwner)->withSession($recipientSession)
            ->from('/alliance/kingdom-alliances')
            ->post('/alliance/kingdom-sharing/invitations/accept', ['token' => $secondToken])
            ->assertRedirect('/alliance/kingdom-alliances')
            ->assertSessionHasErrors('sharing');

        $selfToken = $this->issueViaHttp($sourceOwner, $sourceSession);
        $this->actingAs($sourceOwner)->withSession($sourceSession)
            ->from('/alliance/kingdom-alliances')
            ->post('/alliance/kingdom-sharing/invitations/accept', ['token' => $selfToken])
            ->assertRedirect('/alliance/kingdom-alliances')
            ->assertSessionHasErrors('sharing');
    }

    public function test_different_kingdom_or_expired_invitation_cannot_activate_but_decline_can_reduce_access(): void
    {
        [$sourceOwner, $source, $sourceSession] = $this->ownerAlliance('K5 Source Boundary', 'k5-source-boundary', 7504);
        [$otherOwner, $other, $otherSession] = $this->ownerAlliance('K5 Other Kingdom', 'k5-other-kingdom', 7505);

        $differentKingdomToken = $this->issueViaHttp($sourceOwner, $sourceSession);
        $this->actingAs($otherOwner)->withSession($otherSession)
            ->from('/alliance/kingdom-alliances')
            ->post('/alliance/kingdom-sharing/invitations/accept', ['token' => $differentKingdomToken])
            ->assertRedirect('/alliance/kingdom-alliances')
            ->assertSessionHasErrors('sharing');

        $pending = KingdomIntelligenceShare::query()
            ->where('invitation_token_hash', hash('sha256', $differentKingdomToken))
            ->sole();
        self::assertSame(KingdomIntelligenceShareState::Pending, $pending->state);
        self::assertNull($pending->invitation_used_at);

        $this->actingAs($otherOwner)->withSession($otherSession)
            ->post('/alliance/kingdom-sharing/invitations/decline', ['token' => $differentKingdomToken])
            ->assertRedirect();
        self::assertSame(KingdomIntelligenceShareState::Declined, $pending->refresh()->state);
        self::assertSame($other->id, $pending->recipient_alliance_id);
        self::assertNotNull($pending->invitation_used_at);

        $expiredToken = $this->issueViaHttp($sourceOwner, $sourceSession);
        $expired = KingdomIntelligenceShare::query()
            ->where('invitation_token_hash', hash('sha256', $expiredToken))
            ->sole();
        $expired->forceFill(['invitation_expires_at' => now()->subMinute()])->save();

        $this->actingAs($otherOwner)->withSession($otherSession)
            ->from('/alliance/kingdom-alliances')
            ->post('/alliance/kingdom-sharing/invitations/accept', ['token' => $expiredToken])
            ->assertRedirect('/alliance/kingdom-alliances')
            ->assertSessionHasErrors('token');
        self::assertSame(KingdomIntelligenceShareState::Pending, $expired->refresh()->state);
    }

    public function test_source_revoke_and_recipient_leave_are_tenant_scoped_terminal_and_drift_tolerant(): void
    {
        [$sourceOwner, $source, $sourceSession] = $this->ownerAlliance('K5 Source Terminal', 'k5-source-terminal', 7506);
        [$recipientOwner, $recipient, $recipientSession] = $this->ownerAlliance('K5 Recipient Terminal', 'k5-recipient-terminal', 7506);
        [$otherOwner, $other, $otherSession] = $this->ownerAlliance('K5 Other Terminal', 'k5-other-terminal', 7506);

        $token = $this->issueViaHttp($sourceOwner, $sourceSession);
        $this->actingAs($recipientOwner)->withSession($recipientSession)
            ->post('/alliance/kingdom-sharing/invitations/accept', ['token' => $token])
            ->assertRedirect();
        $share = KingdomIntelligenceShare::query()->sole();

        $this->actingAs($otherOwner)->withSession($otherSession)
            ->post("/alliance/kingdom-sharing/{$share->id}/revoke")
            ->assertNotFound();
        $this->actingAs($otherOwner)->withSession($otherSession)
            ->post("/alliance/kingdom-sharing/{$share->id}/leave")
            ->assertNotFound();

        $newKingdom = Kingdom::query()->create(['number' => 7599, 'status' => 'active']);
        $source->forceFill(['kingdom_id' => $newKingdom->id])->save();

        $this->actingAs($sourceOwner)->withSession($sourceSession)
            ->post("/alliance/kingdom-sharing/{$share->id}/revoke")
            ->assertRedirect();
        self::assertSame(KingdomIntelligenceShareState::Revoked, $share->refresh()->state);
        self::assertNotNull($share->revoked_at);

        $this->withSession($sourceSession)
            ->post("/alliance/kingdom-sharing/{$share->id}/revoke")
            ->assertRedirect();
        self::assertSame(KingdomIntelligenceShareState::Revoked, $share->refresh()->state);

        $secondIssued = $this->app->make(CreateKingdomIntelligenceShareInvitation::class)
            ->handle($other, $otherOwner);
        $this->actingAs($recipientOwner)->withSession($recipientSession)
            ->post('/alliance/kingdom-sharing/invitations/accept', ['token' => $secondIssued->token])
            ->assertRedirect();
        $second = KingdomIntelligenceShare::query()->whereKey($secondIssued->shareId)->sole();
        self::assertSame(KingdomIntelligenceShareState::Active, $second->state);

        $recipient->forceFill(['kingdom_id' => $newKingdom->id])->save();
        $this->actingAs($recipientOwner)->withSession($recipientSession)
            ->post("/alliance/kingdom-sharing/{$second->id}/leave")
            ->assertRedirect();
        self::assertSame(KingdomIntelligenceShareState::Declined, $second->refresh()->state);
        self::assertNotNull($second->declined_at);
    }

    private function issueViaHttp(User $owner, array $session): string
    {
        $response = $this->actingAs($owner)->withSession($session)
            ->postJson('/alliance/kingdom-sharing/invitations')
            ->assertCreated();

        return (string) $response->json('token');
    }

    /** @return array{0: User, 1: Alliance, 2: array<string, mixed>} */
    private function ownerAlliance(string $name, string $slug, int $kingdom): array
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, $name, $slug, $kingdom);

        return [$owner, $alliance, $this->confirmedSession((string) $alliance->id)];
    }

    private function member(Alliance $alliance): User
    {
        $member = User::factory()->create();
        $membership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'user_id' => $member->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);
        $role = Role::query()
            ->where('alliance_id', $alliance->id)
            ->where('key', DefaultAllianceRole::Member->value)
            ->sole();
        $membership->roles()->attach($role->id, ['alliance_id' => $alliance->id]);

        return $member;
    }

    /** @return array<string, mixed> */
    private function confirmedSession(string $allianceId): array
    {
        return [
            (string) config('identity.active_alliance_session_key') => $allianceId,
            'auth.password_confirmed_at' => time(),
        ];
    }
}
