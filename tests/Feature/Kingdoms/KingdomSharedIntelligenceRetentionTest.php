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
use App\Domain\Kingdoms\Models\KingdomAllianceObservation;
use App\Domain\Kingdoms\Models\KingdomIntelligenceShare;
use App\Domain\Kingdoms\Models\KingdomIntelligenceShareTarget;
use App\Domain\Kingdoms\Models\TrackedKingdomAlliance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class KingdomSharedIntelligenceRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('kingdoms.shared_intelligence_retention', [
            'expired_invitation_days' => 10,
            'terminal_share_days' => 30,
            'removed_target_days' => 20,
        ]);
    }

    public function test_retention_purges_only_eligible_operational_rows_and_preserves_canonical_history(): void
    {
        [$sourceOwner, $source] = $this->ownerAlliance('Retention Source', 'retention-source', 7630);
        [$recipientOwner, $recipient] = $this->ownerAlliance('Retention Recipient', 'retention-recipient', 7630);

        $expired = $this->pendingShare($sourceOwner, $source);
        DB::table('kingdom_intelligence_shares')->where('id', $expired->id)->update([
            'invitation_expires_at' => now()->subDays(11),
            'updated_at' => now()->subDays(11),
        ]);

        $recentlyExpired = $this->pendingShare($sourceOwner, $source);
        DB::table('kingdom_intelligence_shares')->where('id', $recentlyExpired->id)->update([
            'invitation_expires_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        $terminalShare = $this->activeShare($sourceOwner, $source, $recipientOwner, $recipient);
        $terminalTracking = $this->tracking($sourceOwner, $source, 'retention-terminal', 'Terminal Target');
        $terminalObservation = $this->observation($sourceOwner, $source, $terminalTracking, 'Terminal Target');
        $terminalTarget = $this->app->make(AddKingdomIntelligenceShareTarget::class)
            ->handle($source, $sourceOwner, (string) $terminalShare->id, (string) $terminalTracking->id);
        $this->app->make(RevokeKingdomIntelligenceShare::class)
            ->handle($source, $sourceOwner, (string) $terminalShare->id);
        DB::table('kingdom_intelligence_shares')->where('id', $terminalShare->id)->update([
            'revoked_at' => now()->subDays(31),
            'updated_at' => now()->subDays(31),
        ]);

        $activeShare = $this->activeShare($sourceOwner, $source, $recipientOwner, $recipient);
        $removedTracking = $this->tracking($sourceOwner, $source, 'retention-removed', 'Removed Target');
        $removedObservation = $this->observation($sourceOwner, $source, $removedTracking, 'Removed Target');
        $removedTarget = $this->app->make(AddKingdomIntelligenceShareTarget::class)
            ->handle($source, $sourceOwner, (string) $activeShare->id, (string) $removedTracking->id);
        $this->app->make(RemoveKingdomIntelligenceShareTarget::class)
            ->handle($source, $sourceOwner, (string) $activeShare->id, (string) $removedTarget->id);
        DB::table('kingdom_intelligence_share_targets')->where('id', $removedTarget->id)->update([
            'removed_at' => now()->subDays(21),
            'updated_at' => now()->subDays(21),
        ]);

        $activeTracking = $this->tracking($sourceOwner, $source, 'retention-active', 'Active Target');
        $activeObservation = $this->observation($sourceOwner, $source, $activeTracking, 'Active Target');
        $activeTarget = $this->app->make(AddKingdomIntelligenceShareTarget::class)
            ->handle($source, $sourceOwner, (string) $activeShare->id, (string) $activeTracking->id);

        $result = $this->app->make(EnforceKingdomIntelligenceSharingRetention::class)->handle(20);

        self::assertSame(1, $result['expiredInvitationsPurged']);
        self::assertSame(1, $result['terminalSharesPurged']);
        self::assertSame(1, $result['removedTargetsPurged']);
        self::assertSame(3, $result['processed']);

        self::assertFalse(KingdomIntelligenceShare::query()->whereKey($expired->id)->exists());
        self::assertTrue(KingdomIntelligenceShare::query()->whereKey($recentlyExpired->id)->exists());
        self::assertFalse(KingdomIntelligenceShare::query()->whereKey($terminalShare->id)->exists());
        self::assertFalse(KingdomIntelligenceShareTarget::query()->whereKey($terminalTarget->id)->exists());
        self::assertFalse(KingdomIntelligenceShareTarget::query()->whereKey($removedTarget->id)->exists());

        $activeShare->refresh();
        self::assertSame(KingdomIntelligenceShareState::Active, $activeShare->state);
        self::assertTrue(KingdomIntelligenceShareTarget::query()->whereKey($activeTarget->id)->exists());

        foreach ([$terminalTracking, $removedTracking, $activeTracking] as $tracking) {
            self::assertTrue(TrackedKingdomAlliance::query()->whereKey($tracking->id)->exists());
        }
        foreach ([$terminalObservation, $removedObservation, $activeObservation] as $observation) {
            self::assertTrue(KingdomAllianceObservation::query()->whereKey($observation->id)->exists());
        }

        $this->assertDatabaseHas('audit_events', [
            'event' => 'kingdoms.shared_intelligence_revoked',
            'alliance_id' => $source->id,
        ]);
        $this->assertDatabaseHas('outbox_messages', [
            'event_type' => 'kingdoms.shared_intelligence_revoked',
            'alliance_id' => $source->id,
        ]);

        self::assertSame([
            'expiredInvitationsPurged' => 0,
            'terminalSharesPurged' => 0,
            'removedTargetsPurged' => 0,
            'processed' => 0,
        ], $this->app->make(EnforceKingdomIntelligenceSharingRetention::class)->handle(20));
    }

    public function test_retention_command_respects_one_total_budget_across_runs(): void
    {
        [$owner, $source] = $this->ownerAlliance('Retention Budget', 'retention-budget', 7631);

        $ids = [];
        for ($index = 0; $index < 3; $index++) {
            $share = $this->pendingShare($owner, $source);
            $ids[] = (string) $share->id;
            DB::table('kingdom_intelligence_shares')->where('id', $share->id)->update([
                'invitation_expires_at' => now()->subDays(20 + $index),
                'updated_at' => now()->subDays(20 + $index),
            ]);
        }

        self::assertSame(0, Artisan::call('kingdoms:enforce-sharing-retention', ['--limit' => 2]));
        /** @var array<string, int> $first */
        $first = json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(2, $first['processed']);
        self::assertSame(1, KingdomIntelligenceShare::query()->whereIn('id', $ids)->count());

        self::assertSame(0, Artisan::call('kingdoms:enforce-sharing-retention', ['--limit' => 2]));
        /** @var array<string, int> $second */
        $second = json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(1, $second['processed']);
        self::assertSame(0, KingdomIntelligenceShare::query()->whereIn('id', $ids)->count());

        self::assertSame(0, Artisan::call('kingdoms:enforce-sharing-retention', ['--limit' => 2]));
        /** @var array<string, int> $third */
        $third = json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(0, $third['processed']);
    }

    private function pendingShare(User $owner, Alliance $source): KingdomIntelligenceShare
    {
        return $this->app->make(CreateKingdomIntelligenceShareInvitation::class)
            ->handle($source, $owner)
            ->share;
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

    private function tracking(User $owner, Alliance $source, string $gameId, string $name): TrackedKingdomAlliance
    {
        return $this->app->make(StartTrackingKingdomAlliance::class)->handle($source, $owner, [
            'game_alliance_id' => $gameId,
            'current_name' => $name,
            'current_tag' => strtoupper(substr($gameId, -3)),
        ]);
    }

    private function observation(
        User $owner,
        Alliance $source,
        TrackedKingdomAlliance $tracking,
        string $name,
    ): KingdomAllianceObservation {
        return $this->app->make(RecordKingdomAllianceObservation::class)->handle(
            $source,
            $owner,
            (string) $tracking->id,
            [
                'observed_name' => $name,
                'observed_tag' => 'RET',
                'power' => '1000',
                'member_count' => 50,
                'captured_at' => now()->subDay()->toIso8601String(),
            ],
        );
    }

    /** @return array{User, Alliance} */
    private function ownerAlliance(string $name, string $slug, int $kingdom): array
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, $name, $slug, $kingdom);

        return [$owner, $alliance];
    }
}
