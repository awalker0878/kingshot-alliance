<?php

declare(strict_types=1);

namespace Tests\Feature\Alliance\Membership;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Membership\Actions\CreateInvitation;
use App\Contexts\Alliance\Membership\Enums\InvitationStatus;
use App\Contexts\Alliance\Membership\Models\Invitation;
use App\Contexts\Alliance\Membership\Queries\FindPendingInvitation;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Roster\Actions\SaveRosterEntry;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InvitationReplacementTest extends TestCase
{
    use RefreshDatabase;

    public function test_replacement_invitation_for_same_player_revokes_and_audits_previous_pending_token(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 4010, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'replacement-owner',
            'current_name' => 'Replacement Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($ownerPlayer, 'Replacement Invites', 'replacement-invites');
        $target = $this->app->make(SaveRosterEntry::class)->handle($alliance, $ownerPlayer, [
            'name' => 'Replacement Target',
            'game_player_id' => 'replacement-target',
        ])->player;
        $create = $this->app->make(CreateInvitation::class);

        $first = $create->handle($alliance, $ownerPlayer, $target, 'replace@example.com');
        $second = $create->handle($alliance, $ownerPlayer, $target, 'replace@example.com');
        $firstRecord = Invitation::query()->findOrFail($first->invitationId);
        $secondRecord = Invitation::query()->findOrFail($second->invitationId);

        self::assertSame($target->id, $firstRecord->player_id);
        self::assertSame($target->id, $secondRecord->player_id);
        self::assertSame(InvitationStatus::Revoked, $firstRecord->status);
        self::assertNotNull($firstRecord->revoked_at);
        self::assertSame(InvitationStatus::Pending, $secondRecord->status);
        self::assertNull($this->app->make(FindPendingInvitation::class)->byToken($first->token));
        self::assertNotNull($this->app->make(FindPendingInvitation::class)->byToken($second->token));

        $audit = AuditEvent::query()
            ->where('event', 'invitation.revoked')
            ->where('subject_id', $first->invitationId)
            ->sole();

        self::assertSame($ownerPlayer->id, $audit->actor_player_id);
        self::assertSame($target->id, $audit->metadata['player_id'] ?? null);
        self::assertSame('superseded', $audit->metadata['reason'] ?? null);
    }
}
