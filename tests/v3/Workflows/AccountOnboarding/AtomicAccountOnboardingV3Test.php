<?php

declare(strict_types=1);

namespace Tests\v3\Workflows\AccountOnboarding;

use App\Contexts\Accounts\EmailVerification\Notifications\VerifyKingshotAllianceEmail;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Registration\Data\RegistrationProviderIdentity;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Actions\CreateInvitation;
use App\Contexts\Alliance\Membership\Enums\InvitationStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\Alliance\Membership\Models\Invitation;
use App\Contexts\GameWorld\Players\Actions\ClaimPlayerAccount;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\Models\PlayerIdentityHistory;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use App\Shared\Infrastructure\Messaging\Outbox\Actions\PublishOutboxBatch;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use App\Workflows\AccountOnboarding\Actions\AcceptInvitationForAccount;
use App\Workflows\AccountOnboarding\Actions\RegisterAccount;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class AtomicAccountOnboardingV3Test extends TestCase
{
    use DatabaseMigrations;

    public function test_wrong_email_acceptance_does_not_claim_the_invited_player(): void
    {
        $fixture = $this->invitation();
        $user = User::factory()->create(['email' => 'wrong-invitee@example.test']);
        $before = $this->ownerState($fixture['playerId']);

        $this->actingAs($user)->postJson('/invitations/'.$fixture['token'].'/accept')->assertForbidden();

        self::assertSame($before, $this->ownerState($fixture['playerId']));
        self::assertSame(InvitationStatus::Pending, Invitation::query()->findOrFail($fixture['invitationId'])->status);
    }

    public function test_existing_account_acceptance_rolls_back_a_claim_when_the_alliance_is_inactive(): void
    {
        $fixture = $this->invitation();
        $user = User::factory()->create(['email' => $fixture['email']]);
        Alliance::query()->whereKey($fixture['allianceId'])->update(['status' => 'suspended']);
        $before = $this->ownerState($fixture['playerId']);

        $this->actingAs($user)->postJson('/invitations/'.$fixture['token'].'/accept')
            ->assertUnprocessable()->assertJsonValidationErrors('invitation');

        self::assertSame($before, $this->ownerState($fixture['playerId']));
    }

    /** @return iterable<string,array{bool,string}> */
    public static function rejectedRegistrations(): iterable
    {
        foreach ([false, true] as $google) {
            foreach (['inactive', 'roster-left', 'claimed'] as $reason) {
                yield ($google ? 'google' : 'password').'-'.$reason => [$google, $reason];
            }
        }
    }

    #[DataProvider('rejectedRegistrations')]
    public function test_failed_registration_rolls_back_all_owner_writes_and_verification_mail(bool $google, string $reason): void
    {
        $fixture = $this->invitation();
        if ($reason === 'inactive') {
            Alliance::query()->whereKey($fixture['allianceId'])->update(['status' => 'suspended']);
        } elseif ($reason === 'roster-left') {
            AllianceRosterEntry::query()->where('player_id', $fixture['playerId'])->update(['state' => 'left']);
        } else {
            app(ClaimPlayerAccount::class)->handle($fixture['playerId'], (int) User::factory()->create()->id);
        }
        $before = $this->ownerState($fixture['playerId']);
        Notification::fake();

        try {
            app(RegisterAccount::class)->handle(
                'Invitation account', $fixture['email'], $google ? null : 'Strong-Invitation-Password-41!',
                'UTC', $fixture['token'], $google,
                $google ? new RegistrationProviderIdentity('google', 'atomic-google-subject', $fixture['email'], true) : null,
            );
            self::fail('Current owner state must reject this invitation.');
        } catch (ValidationException $exception) {
            self::assertNotEmpty($exception->errors());
        }

        self::assertSame($before, $this->ownerState($fixture['playerId']));
        $this->assertDatabaseMissing('users', ['email' => $fixture['email']]);
        $this->assertDatabaseMissing('account_identities', ['provider_subject' => 'atomic-google-subject']);
        self::assertSame(InvitationStatus::Pending, Invitation::query()->findOrFail($fixture['invitationId'])->status);
        Notification::assertNothingSent();
    }

    public function test_invitation_revoked_after_registration_preflight_remains_revoked_and_rolls_back_the_new_account(): void
    {
        $fixture = $this->invitation();
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.onboarding_competitor', config('database.connections.'.$primary));
        DB::connection('onboarding_competitor')->statement("SET lock_timeout = '1s'");
        $revoked = false;
        DB::listen(static function (QueryExecuted $query) use ($fixture, &$revoked): void {
            if ($revoked || ! str_starts_with($query->sql, 'insert into "users"')) {
                return;
            }
            $revoked = true;
            DB::connection('onboarding_competitor')->table('invitations')
                ->where('id', $fixture['invitationId'])
                ->update(['status' => 'revoked', 'revoked_at' => now()]);
        });
        Notification::fake();
        $before = $this->ownerState($fixture['playerId']);

        try {
            try {
                app(RegisterAccount::class)->handle('Invitation account', $fixture['email'], 'Strong-Invitation-Password-41!', 'UTC', $fixture['token']);
                self::fail('A concurrently revoked invitation must reject onboarding.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('invitation', $exception->errors());
            }
            self::assertTrue($revoked);
            self::assertSame(InvitationStatus::Revoked, Invitation::query()->findOrFail($fixture['invitationId'])->status);
            self::assertSame($before, $this->ownerState($fixture['playerId']));
            $this->assertDatabaseMissing('users', ['email' => $fixture['email']]);
            Notification::assertNothingSent();
        } finally {
            DB::purge('onboarding_competitor');
        }
    }

    public function test_successful_registration_queues_verification_and_worker_delivers_after_every_owner_commits(): void
    {
        $fixture = $this->invitation();
        $deliveryState = null;
        Notification::shouldReceive('send')->once()->withArgs(static function (User $user, VerifyKingshotAllianceEmail $notification) use ($fixture, &$deliveryState): bool {
            $deliveryState = [
                'transaction_level' => DB::transactionLevel(),
                'email' => $user->email,
                'user_id' => (int) $user->id,
                'player_owner_id' => (int) Player::query()->findOrFail($fixture['playerId'])->user_id,
                'invitation_status' => Invitation::query()->findOrFail($fixture['invitationId'])->status,
                'active_membership' => AllianceMembership::query()->where('player_id', $fixture['playerId'])->where('status', 'active')->exists(),
            ];

            return true;
        });

        $result = app(RegisterAccount::class)->handle('Invitation account', $fixture['email'], 'Strong-Invitation-Password-41!', 'UTC', $fixture['token']);

        self::assertSame($fixture['playerId'], $result->playerId);
        self::assertSame($fixture['allianceId'], $result->allianceId);
        self::assertNotNull($result->membershipId);
        self::assertSame(1, OutboxMessage::query()->where('event_type', 'user.registered')->where('aggregate_id', (string) $result->userId)->count());
        self::assertSame(1, OutboxMessage::query()->where('event_type', 'invitation.accepted')->where('aggregate_id', $fixture['invitationId'])->count());
        self::assertNull($deliveryState, 'Registration returns after durable intent without contacting mail.');
        app(PublishOutboxBatch::class)->handle(100);
        self::assertSame([
            'transaction_level' => 0,
            'email' => $fixture['email'],
            'user_id' => $result->userId,
            'player_owner_id' => $result->userId,
            'invitation_status' => InvitationStatus::Accepted,
            'active_membership' => true,
        ], $deliveryState, 'Mail sees every committed owner and runs outside every transaction.');
    }

    public function test_existing_account_acceptance_commits_once_and_selects_the_claimed_player(): void
    {
        $fixture = $this->invitation();
        $user = User::factory()->create(['email' => $fixture['email']]);
        $this->actingAs($user)->post('/invitations/'.$fixture['token'].'/accept')->assertRedirect(route('alliance.overview'));
        self::assertSame($fixture['playerId'], session((string) config('game_world.active_player_session_key')));
        self::assertSame((int) $user->id, (int) Player::query()->findOrFail($fixture['playerId'])->user_id);
        $before = $this->ownerState($fixture['playerId']);

        $this->withCookie((string) config('session.cookie'), session()->getId())
            ->postJson('/invitations/'.$fixture['token'].'/accept')->assertUnprocessable();

        self::assertSame($before, $this->ownerState($fixture['playerId']));
        self::assertSame(1, OutboxMessage::query()->where('event_type', 'invitation.accepted')->where('aggregate_id', $fixture['invitationId'])->count());
    }

    public function test_finalized_account_is_rejected_before_a_player_claim(): void
    {
        $fixture = $this->invitation();
        $user = User::factory()->create(['email' => $fixture['email'], 'anonymized_at' => now()]);
        $before = $this->ownerState($fixture['playerId']);
        try {
            app(AcceptInvitationForAccount::class)->handle((int) $user->id, $fixture['token']);
            self::fail('A finalized account cannot accept invitations.');
        } catch (AuthorizationException) {
            self::assertSame($before, $this->ownerState($fixture['playerId']));
        }
    }

    /** @return array{playerId:string,allianceId:string,invitationId:string,token:string,email:string} */
    private function invitation(): array
    {
        $scenario = new ScenarioFactory;
        $owner = $scenario->player((int) User::factory()->create()->id, 80801);
        $alliance = $scenario->alliance($owner);
        $target = $scenario->unclaimedPlayer(80801);
        $scenario->roster($owner, $alliance, $target);
        $email = 'atomic-invitee@example.test';
        $invitation = app(CreateInvitation::class)->handle($alliance->allianceId, $owner->playerId, $target->playerId, $email);

        return ['playerId' => $target->playerId, 'allianceId' => $alliance->allianceId, 'invitationId' => $invitation->invitationId, 'token' => $invitation->token, 'email' => $email];
    }

    /** @return array<string,mixed> */
    private function ownerState(string $playerId): array
    {
        return [
            'owner' => Player::query()->findOrFail($playerId)->user_id,
            'history' => PlayerIdentityHistory::query()->where('player_id', $playerId)->orderBy('id')->get()->toArray(),
            'membership' => AllianceMembership::query()->where('player_id', $playerId)->get()->toArray(),
            'users' => User::query()->count(),
            'audit' => AuditEvent::query()->count(),
            'outbox' => OutboxMessage::query()->count(),
        ];
    }
}
