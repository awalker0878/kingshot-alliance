<?php

declare(strict_types=1);

namespace Tests\Feature\Memberships;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Actions\CreateInvitation;
use App\Domain\Memberships\Enums\InvitationStatus;
use App\Domain\Memberships\Models\Invitation;
use App\Domain\Memberships\Queries\FindPendingInvitation;
use App\Domain\Platform\Models\OutboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InvitationReplacementTest extends TestCase
{
    use RefreshDatabase;

    public function test_replacement_invitation_revokes_and_audits_the_previous_pending_token(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Replacement Invites', 'replacement-invites');
        $create = $this->app->make(CreateInvitation::class);

        $first = $create->handle($alliance, $owner, 'replace@example.com');
        $second = $create->handle($alliance, $owner, 'replace@example.com');
        $firstRecord = Invitation::query()->findOrFail($first->invitationId);
        $secondRecord = Invitation::query()->findOrFail($second->invitationId);

        self::assertSame(InvitationStatus::Revoked, $firstRecord->status);
        self::assertNotNull($firstRecord->revoked_at);
        self::assertSame(InvitationStatus::Pending, $secondRecord->status);
        self::assertNull($this->app->make(FindPendingInvitation::class)->byToken($first->token));
        self::assertNotNull($this->app->make(FindPendingInvitation::class)->byToken($second->token));

        $audit = AuditEvent::query()
            ->where('event', 'invitation.revoked')
            ->where('subject_id', $first->invitationId)
            ->sole();

        self::assertSame('superseded', $audit->metadata['reason'] ?? null);
        self::assertSame(1, OutboxMessage::query()
            ->where('event_type', 'invitation.revoked')
            ->where('aggregate_id', $first->invitationId)
            ->count());
    }
}
