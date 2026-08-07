<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Application\Identity\CreateAlliance;
use App\Application\Identity\CreateInvitation;
use App\Application\Identity\FindPendingInvitation;
use App\Domain\Identity\Enums\InvitationStatus;
use App\Models\AuditEvent;
use App\Models\OutboxMessage;
use App\Models\User;
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

        self::assertSame(InvitationStatus::Revoked, $first->invitation->refresh()->status);
        self::assertNotNull($first->invitation->revoked_at);
        self::assertSame(InvitationStatus::Pending, $second->invitation->refresh()->status);
        self::assertNull($this->app->make(FindPendingInvitation::class)->byToken($first->token));
        self::assertNotNull($this->app->make(FindPendingInvitation::class)->byToken($second->token));

        $audit = AuditEvent::query()
            ->where('event', 'invitation.revoked')
            ->where('subject_id', $first->invitation->id)
            ->sole();

        self::assertSame('superseded', $audit->metadata['reason'] ?? null);
        self::assertSame(1, OutboxMessage::query()
            ->where('event_type', 'invitation.revoked')
            ->where('aggregate_id', $first->invitation->id)
            ->count());
    }
}
