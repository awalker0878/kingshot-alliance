<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Actions\AcceptKingdomIntelligenceShareInvitation;
use App\Domain\Kingdoms\Actions\AddKingdomIntelligenceShareTarget;
use App\Domain\Kingdoms\Actions\CreateKingdomIntelligenceShareInvitation;
use App\Domain\Kingdoms\Actions\EnforceKingdomIntelligenceSharingRetention;
use App\Domain\Kingdoms\Actions\RecordKingdomAllianceObservation;
use App\Domain\Kingdoms\Actions\RemoveKingdomIntelligenceShareTarget;
use App\Domain\Kingdoms\Actions\RevokeKingdomIntelligenceShare;
use App\Domain\Kingdoms\Actions\StartTrackingKingdomAlliance;
use App\Domain\Kingdoms\Enums\KingdomIntelligenceShareState;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\KingdomAllianceObservation;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Kingdoms\Models\KingdomIntelligenceShare;
use App\Domain\Kingdoms\Models\KingdomIntelligenceShareTarget;
use App\Domain\Kingdoms\Models\TrackedKingdomAlliance;
use App\Domain\Kingdoms\Queries\SharedKingdomIntelligenceCurrentQuery;
use App\Domain\Kingdoms\Queries\SharedKingdomIntelligenceHistoryQuery;
use App\Domain\Memberships\Enums\AllianceRank;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class KingdomSharedIntelligenceAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_whole_increment_preserves_consent_safe_projection_tenant_isolation_revocation_and_retention_boundaries(): void
    {
        config()->set('kingdoms.shared_intelligence_retention', [
            'expired_invitation_days' => 10,
            'terminal_share_days' => 30,
            'removed_target_days' => 20,
        ]);

        $asOf = now()->startOfSecond();
        [$sourceOwner, $sourcePlayer, $source] = $this->ownerAlliance('K5 Acceptance Source', 'k5-acceptance-source', 7640);
        [$recipientOwner, $recipientPlayer, $recipient] = $this->ownerAlliance('K5 Acceptance Recipient', 'k5-acceptance-recipient', 7640);
        [$unrelatedOwner, $unrelatedPlayer, $unrelated] = $this->ownerAlliance('K5 Acceptance Unrelated', 'k5-acceptance-unrelated', 7640);
        [$recipientMember, $recipientMemberPlayer] = $this->member($recipient);

        $issued = $this->app->make(CreateKingdomIntelligenceShareInvitation::class)
            ->handle($source, $sourcePlayer);
        $share = KingdomIntelligenceShare::query()->findOrFail($issued->shareId);

        self::assertSame(KingdomIntelligenceShareState::Pending, $share->state);
        self::assertSame(hash('sha256', $issued->token), $share->invitation_token_hash);
        self::assertSame([], $this->app->make(SharedKingdomIntelligenceCurrentQuery::class)->forRecipient($recipient, $asOf));

        $share = $this->app->make(AcceptKingdomIntelligenceShareInvitation::class)
            ->handle($recipient, $recipientPlayer, $issued->token);

        self::assertSame(KingdomIntelligenceShareState::Active, $share->state);
        self::assertSame($recipient->id, $share->recipient_alliance_id);
        self::assertNull($share->invitation_token_hash);
        self::assertNotNull($share->invitation_used_at);

        $tracking = $this->tracking($sourcePlayer, $source, 'ga-k5-acceptance', 'Acceptance Target', 'K5A');
        $tracking->forceFill(['manager_notes' => 'PRIVATE K5 ACCEPTANCE NOTE'])->save();

        $original = $this->observation(
            $sourcePlayer,
            $source,
            $tracking,
            'Acceptance Original',
            'OLD',
            '100',
            10,
            $asOf->copy()->subDays(3),
        );
        $replacement = $this->app->make(RecordKingdomAllianceObservation::class)->handle(
            $source,
            $sourcePlayer,
            (string) $tracking->id,
            [
                'observed_name' => 'Acceptance Corrected',
                'observed_tag' => 'FIX',
                'power' => '200',
                'member_count' => 20,
                'captured_at' => $asOf->copy()->subDays(2)->toIso8601String(),
                'corrects_observation_id' => (string) $original->id,
                'correction_reason' => 'PRIVATE K5 ACCEPTANCE CORRECTION',
            ],
        );
        $latest = $this->observation(
            $sourcePlayer,
            $source,
            $tracking,
            'Acceptance Latest',
            'NEW',
            '300',
            30,
            $asOf->copy()->subDay(),
        );

        self::assertNotNull($original->refresh()->invalidated_at);

        $currentQuery = $this->app->make(SharedKingdomIntelligenceCurrentQuery::class);
        self::assertSame([], $currentQuery->forRecipient($recipient, $asOf));

        $target = $this->app->make(AddKingdomIntelligenceShareTarget::class)
            ->handle($source, $sourcePlayer, (string) $share->id, (string) $tracking->id);

        $current = $currentQuery->forRecipient($recipient, $asOf);
        self::assertCount(1, $current);
        self::assertSame(
            ['shareTargetId', 'sourceAlliance', 'gameAlliance', 'freshness', 'latestObservation'],
            array_keys($current[0]),
        );
        self::assertSame((string) $target->id, $current[0]['shareTargetId']);
        self::assertSame('K5 Acceptance Source', $current[0]['sourceAlliance']['name']);
        self::assertSame('Acceptance Latest', $current[0]['latestObservation']['observedName']);
        self::assertSame('current', $current[0]['freshness']);
        self::assertSame([], $currentQuery->forRecipient($unrelated, $asOf));
        self::assertSame(250, SharedKingdomIntelligenceCurrentQuery::CURRENT_LIMIT);

        $encodedCurrent = json_encode($current, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('PRIVATE K5 ACCEPTANCE NOTE', $encodedCurrent);
        self::assertStringNotContainsString('PRIVATE K5 ACCEPTANCE CORRECTION', $encodedCurrent);
        self::assertStringNotContainsString((string) $tracking->id, $encodedCurrent);
        self::assertStringNotContainsString((string) $original->id, $encodedCurrent);
        self::assertStringNotContainsString((string) $replacement->id, $encodedCurrent);
        self::assertStringNotContainsString((string) $latest->id, $encodedCurrent);

        $historyQuery = $this->app->make(SharedKingdomIntelligenceHistoryQuery::class);
        $history = $historyQuery->forRecipientTarget(
            $recipient,
            (string) $target->id,
            pageSize: 50,
            asOf: $asOf,
        );

        self::assertSame(['Acceptance Latest', 'Acceptance Corrected'], array_column($history['items'], 'observedName'));
        self::assertNull($history['nextCursor']);
        self::assertSame(250, SharedKingdomIntelligenceHistoryQuery::HISTORY_LIMIT);

        $encodedHistory = json_encode($history, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('Acceptance Original', $encodedHistory);
        self::assertStringNotContainsString('PRIVATE K5 ACCEPTANCE NOTE', $encodedHistory);
        self::assertStringNotContainsString('PRIVATE K5 ACCEPTANCE CORRECTION', $encodedHistory);
        self::assertStringNotContainsString((string) $tracking->id, $encodedHistory);
        self::assertStringNotContainsString((string) $original->id, $encodedHistory);
        self::assertStringNotContainsString((string) $replacement->id, $encodedHistory);
        self::assertStringNotContainsString((string) $latest->id, $encodedHistory);
        $this->assertHistoryNotFound(
            fn () => $historyQuery->forRecipientTarget($unrelated, (string) $target->id, asOf: $asOf),
        );

        self::assertFalse(TrackedKingdomAlliance::query()->where('alliance_id', $recipient->id)->exists());
        self::assertFalse(KingdomAllianceObservation::query()->where('alliance_id', $recipient->id)->exists());
        self::assertFalse(TrackedKingdomAlliance::query()->where('alliance_id', $unrelated->id)->exists());
        self::assertFalse(KingdomAllianceObservation::query()->where('alliance_id', $unrelated->id)->exists());

        $this->actingAs($recipientOwner)
            ->withSession($this->activePlayerSession($recipientPlayer->id, true))
            ->post("/alliance/kingdom-sharing/{$share->id}/targets/{$tracking->id}")
            ->assertNotFound();
        $this->actingAs($unrelatedOwner)
            ->withSession($this->activePlayerSession($unrelatedPlayer->id, true))
            ->post("/alliance/kingdom-sharing/{$share->id}/targets/{$tracking->id}")
            ->assertNotFound();

        $memberPage = $this->actingAs($recipientMember)
            ->withSession($this->activePlayerSession($recipientMemberPlayer->id))
            ->withHeader('X-Inertia', 'true')
            ->get('/alliance/kingdom-sharing?target='.$target->id.'&asOf=2000-01-01T00:00:00Z');

        $memberPage->assertOk();
        $memberPage->assertJsonPath('component', 'Alliance/KingdomSharing');
        $memberPage->assertJsonPath('props.canManage', false);
        $memberPage->assertJsonCount(1, 'props.current');
        $memberPage->assertJsonCount(2, 'props.selectedHistory.items');

        $encodedMemberProps = json_encode($memberPage->json('props'), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($issued->token, $encodedMemberProps);
        self::assertStringNotContainsString(hash('sha256', $issued->token), $encodedMemberProps);
        self::assertStringNotContainsString('PRIVATE K5 ACCEPTANCE NOTE', $encodedMemberProps);
        self::assertStringNotContainsString('PRIVATE K5 ACCEPTANCE CORRECTION', $encodedMemberProps);
        self::assertStringNotContainsString((string) $tracking->id, $encodedMemberProps);
        self::assertStringNotContainsString('2000-01-01T00:00:00Z', $encodedMemberProps);
        self::assertStringNotContainsString('passwordConfirmUrl', $encodedMemberProps);
        self::assertStringNotContainsString('"sharing"', $encodedMemberProps);

        $managerPage = $this->actingAs($sourceOwner)
            ->withSession($this->activePlayerSession($sourcePlayer->id))
            ->withHeader('X-Inertia', 'true')
            ->get('/alliance/kingdom-sharing/manage');

        $managerPage->assertOk();
        $managerPage->assertJsonPath('component', 'Alliance/KingdomSharingManage');
        $managerPage->assertJsonPath('props.alliance.name', 'K5 Acceptance Source');
        $encodedManagerProps = json_encode($managerPage->json('props'), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($issued->token, $encodedManagerProps);
        self::assertStringNotContainsString(hash('sha256', $issued->token), $encodedManagerProps);
        self::assertStringNotContainsString('PRIVATE K5 ACCEPTANCE NOTE', $encodedManagerProps);
        self::assertStringNotContainsString('PRIVATE K5 ACCEPTANCE CORRECTION', $encodedManagerProps);
        self::assertStringNotContainsString('observedName', $encodedManagerProps);
        self::assertStringNotContainsString('actor_player_id', $encodedManagerProps);
        self::assertStringNotContainsString('source_adapter_key', $encodedManagerProps);

        self::assertFalse(Route::has('alliance.kingdom-sharing.current.index'));
        self::assertFalse(Route::has('alliance.kingdom-sharing.history.index'));
        self::assertFalse(Route::has('alliance.kingdom-sharing.observations.index'));

        $this->app->make(RemoveKingdomIntelligenceShareTarget::class)
            ->handle($source, $sourcePlayer, (string) $share->id, (string) $target->id);
        self::assertSame([], $currentQuery->forRecipient($recipient, $asOf));
        $this->assertHistoryNotFound(
            fn () => $historyQuery->forRecipientTarget($recipient, (string) $target->id, asOf: $asOf),
        );

        $target = $this->app->make(AddKingdomIntelligenceShareTarget::class)
            ->handle($source, $sourcePlayer, (string) $share->id, (string) $tracking->id);
        self::assertCount(1, $currentQuery->forRecipient($recipient, $asOf));

        $this->app->make(RevokeKingdomIntelligenceShare::class)
            ->handle($source, $sourcePlayer, (string) $share->id);
        $share->refresh();
        self::assertSame(KingdomIntelligenceShareState::Revoked, $share->state);
        self::assertNull($share->invitation_token_hash);
        self::assertSame([], $currentQuery->forRecipient($recipient, $asOf));
        $this->assertHistoryNotFound(
            fn () => $historyQuery->forRecipientTarget($recipient, (string) $target->id, asOf: $asOf),
        );

        DB::table('kingdom_intelligence_shares')->where('id', $share->id)->update([
            'revoked_at' => now()->subDays(31),
            'updated_at' => now()->subDays(31),
        ]);

        $retention = $this->app->make(EnforceKingdomIntelligenceSharingRetention::class);
        $retained = $retention->handle(20);
        self::assertSame(0, $retained['expiredInvitationsPurged']);
        self::assertSame(1, $retained['terminalSharesPurged']);
        self::assertSame(0, $retained['removedTargetsPurged']);
        self::assertSame(1, $retained['processed']);

        self::assertFalse(KingdomIntelligenceShare::query()->whereKey($share->id)->exists());
        self::assertFalse(KingdomIntelligenceShareTarget::query()->whereKey($target->id)->exists());
        self::assertTrue(TrackedKingdomAlliance::query()->whereKey($tracking->id)->exists());
        self::assertTrue(KingdomAllianceObservation::query()->whereKey($original->id)->exists());
        self::assertTrue(KingdomAllianceObservation::query()->whereKey($replacement->id)->exists());
        self::assertTrue(KingdomAllianceObservation::query()->whereKey($latest->id)->exists());

        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $source->id,
            'event' => 'kingdoms.shared_intelligence_revoked',
        ]);
        $this->assertDatabaseHas('outbox_messages', [
            'alliance_id' => $source->id,
            'event_type' => 'kingdoms.shared_intelligence_revoked',
        ]);

        self::assertSame([
            'expiredInvitationsPurged' => 0,
            'terminalSharesPurged' => 0,
            'removedTargetsPurged' => 0,
            'processed' => 0,
        ], $retention->handle(20));
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

    private function observation(
        Player $actor,
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
            $actor,
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

    /** @return array{0: User, 1: Player, 2: Alliance} */
    private function ownerAlliance(string $name, string $slug, int $kingdomNumber): array
    {
        $ownerUser = User::factory()->create();
        $kingdom = Kingdom::query()->firstOrCreate(
            ['number' => $kingdomNumber],
            ['status' => 'active'],
        );
        $ownerPlayer = $this->player($ownerUser, $kingdom, $slug.'-r5', $name.' R5');
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, $name, $slug);

        return [$ownerUser, $ownerPlayer, $alliance];
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
            'acceptance-member-'.$memberUser->id,
            'Acceptance Member',
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
    private function activePlayerSession(string $playerId, bool $passwordConfirmed = false): array
    {
        $session = [
            (string) config('identity.active_player_session_key') => $playerId,
        ];

        if ($passwordConfirmed) {
            $session['auth.password_confirmed_at'] = time();
        }

        return $session;
    }

    private function assertHistoryNotFound(Closure $callback): void
    {
        try {
            $callback();
            self::fail('Expected shared history authorization to fail closed.');
        } catch (ModelNotFoundException) {
            self::assertTrue(true);
        }
    }
}
