<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Enums\DefaultAllianceRole;
use App\Domain\Authorization\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Actions\AcceptKingdomIntelligenceShareInvitation;
use App\Domain\Kingdoms\Actions\AddKingdomIntelligenceShareTarget;
use App\Domain\Kingdoms\Actions\CreateKingdomIntelligenceShareInvitation;
use App\Domain\Kingdoms\Actions\RecordKingdomAllianceObservation;
use App\Domain\Kingdoms\Actions\StartTrackingKingdomAlliance;
use App\Domain\Kingdoms\Models\KingdomIntelligenceShare;
use App\Domain\Kingdoms\Models\TrackedKingdomAlliance;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class KingdomSharedIntelligencePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_page_exposes_only_safe_current_and_bounded_history_props(): void
    {
        $asOf = now()->startOfSecond();
        [$sourceOwner, $source] = $this->ownerAlliance('Presentation Source', 'presentation-source', 7620);
        [$recipientOwner, $recipient] = $this->ownerAlliance('Presentation Recipient', 'presentation-recipient', 7620);
        $recipientMember = $this->member($recipient);
        $share = $this->activeShare($sourceOwner, $source, $recipientOwner, $recipient);
        $tracking = $this->tracking($sourceOwner, $source, 'ga-7620', 'Presentation Target', 'PRE');
        $tracking->forceFill(['manager_notes' => 'PRIVATE TRACKING NOTE'])->save();
        $this->observation(
            $sourceOwner,
            $source,
            $tracking,
            'Presentation Target',
            'PRE',
            '12345',
            77,
            $asOf->copy()->subDay(),
        );
        $target = $this->app->make(AddKingdomIntelligenceShareTarget::class)
            ->handle($source, $sourceOwner, (string) $share->id, (string) $tracking->id);

        $response = $this->actingAs($recipientMember)
            ->withSession($this->activeAllianceSession((string) $recipient->id))
            ->withHeader('X-Inertia', 'true')
            ->get('/alliance/kingdom-sharing?target='.$target->id.'&asOf=2000-01-01T00:00:00Z');

        $response->assertOk();
        $response->assertJsonPath('component', 'Alliance/KingdomSharing');
        $response->assertJsonPath('props.canManage', false);
        $response->assertJsonCount(1, 'props.current');
        $response->assertJsonPath('props.current.0.sourceAlliance.name', 'Presentation Source');
        $response->assertJsonPath('props.current.0.gameAlliance.name', 'Presentation Target');
        $response->assertJsonPath('props.current.0.latestObservation.power', '12345');
        $response->assertJsonCount(1, 'props.selectedHistory.items');
        $response->assertJsonPath('props.selectedHistory.items.0.observedName', 'Presentation Target');

        $encodedProps = json_encode($response->json('props'), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('PRIVATE TRACKING NOTE', $encodedProps);
        self::assertStringNotContainsString((string) $tracking->id, $encodedProps);
        self::assertStringNotContainsString('invitation_token_hash', $encodedProps);
        self::assertStringNotContainsString('actor_user_id', $encodedProps);
        self::assertStringNotContainsString('correction_reason', $encodedProps);
        self::assertStringNotContainsString('source_adapter_key', $encodedProps);
        self::assertStringNotContainsString('2000-01-01T00:00:00Z', $encodedProps);
        self::assertStringNotContainsString('passwordConfirmUrl', $encodedProps);
        self::assertStringNotContainsString('"sharing"', $encodedProps);
    }

    public function test_manager_workspace_is_manager_only_and_never_persists_invitation_plaintext_in_props(): void
    {
        [$sourceOwner, $source] = $this->ownerAlliance('Manager Source', 'manager-source', 7621);
        [$recipientOwner, $recipient] = $this->ownerAlliance('Manager Recipient', 'manager-recipient', 7621);
        $sourceMember = $this->member($source);
        $share = $this->activeShare($sourceOwner, $source, $recipientOwner, $recipient);
        $tracking = $this->tracking($sourceOwner, $source, 'ga-7621', 'Manager Target', 'MGR');
        $tracking->forceFill(['manager_notes' => 'PRIVATE MANAGER NOTE'])->save();
        $this->observation($sourceOwner, $source, $tracking, 'Manager Target', 'MGR', '999', 55, now()->subDay());
        $this->app->make(AddKingdomIntelligenceShareTarget::class)
            ->handle($source, $sourceOwner, (string) $share->id, (string) $tracking->id);

        $this->actingAs($sourceMember)
            ->withSession($this->activeAllianceSession((string) $source->id))
            ->get('/alliance/kingdom-sharing/manage')
            ->assertForbidden();

        $issued = $this->actingAs($sourceOwner)
            ->withSession($this->activeAllianceSession((string) $source->id, true))
            ->postJson('/alliance/kingdom-sharing/invitations')
            ->assertCreated();
        $token = (string) $issued->json('token');
        self::assertNotSame('', $token);

        $response = $this->actingAs($sourceOwner)
            ->withSession($this->activeAllianceSession((string) $source->id))
            ->withHeader('X-Inertia', 'true')
            ->get('/alliance/kingdom-sharing/manage');

        $response->assertOk();
        $response->assertJsonPath('component', 'Alliance/KingdomSharingManage');
        $response->assertJsonPath('props.alliance.name', 'Manager Source');

        /** @var array<int, array<string, mixed>> $outbound */
        $outbound = $response->json('props.sharing.outbound');
        $acceptedShare = collect($outbound)->first(
            static fn (array $row): bool => ($row['id'] ?? null) === (string) $share->id,
        );
        self::assertIsArray($acceptedShare);
        self::assertSame('Manager Recipient', $acceptedShare['recipientAlliance']['name'] ?? null);
        self::assertSame('Manager Target', $acceptedShare['targets'][0]['name'] ?? null);
        self::assertSame('active', $acceptedShare['targets'][0]['state'] ?? null);

        $encodedProps = json_encode($response->json('props'), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($token, $encodedProps);
        self::assertStringNotContainsString(hash('sha256', $token), $encodedProps);
        self::assertStringNotContainsString('PRIVATE MANAGER NOTE', $encodedProps);
        self::assertStringNotContainsString('invitation_token_hash', $encodedProps);
        self::assertStringNotContainsString('observedName', $encodedProps);
        self::assertStringNotContainsString('actor_user_id', $encodedProps);
        self::assertStringNotContainsString('source_adapter_key', $encodedProps);
    }

    public function test_member_page_manager_link_flag_follows_kingdom_manage(): void
    {
        [$owner, $alliance] = $this->ownerAlliance('Presentation Permissions', 'presentation-permissions', 7622);
        $member = $this->member($alliance);

        $this->actingAs($member)
            ->withSession($this->activeAllianceSession((string) $alliance->id))
            ->withHeader('X-Inertia', 'true')
            ->get('/alliance/kingdom-sharing')
            ->assertOk()
            ->assertJsonPath('props.canManage', false);

        $this->actingAs($owner)
            ->withSession($this->activeAllianceSession((string) $alliance->id))
            ->withHeader('X-Inertia', 'true')
            ->get('/alliance/kingdom-sharing')
            ->assertOk()
            ->assertJsonPath('props.canManage', true);
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
    ): void {
        $this->app->make(RecordKingdomAllianceObservation::class)->handle(
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
    private function activeAllianceSession(string $allianceId, bool $passwordConfirmed = false): array
    {
        $session = [
            (string) config('identity.active_alliance_session_key') => $allianceId,
        ];

        if ($passwordConfirmed) {
            $session['auth.password_confirmed_at'] = time();
        }

        return $session;
    }
}
