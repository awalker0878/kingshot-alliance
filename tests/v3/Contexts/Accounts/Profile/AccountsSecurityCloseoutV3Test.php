<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Accounts\Profile;

use App\Contexts\Accounts\Authentication\Models\AccountSession;
use App\Contexts\Accounts\Identity\Actions\AnonymizeAccount;
use App\Contexts\Accounts\Identity\Actions\RecordAccountIdentityUse;
use App\Contexts\Accounts\Identity\Models\AccountIdentity;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Security\Services\SecurityNotificationService;
use App\Contexts\Platform\DataGovernance\Actions\CancelAccountDeletion;
use App\Contexts\Platform\DataGovernance\Actions\RequestAccountDeletion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\v3\TestCase;

final class AccountsSecurityCloseoutV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_account_email_change_stays_pending_until_signed_verification(): void
    {
        $user = User::factory()->create([
            'email' => 'current@example.test',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['accounts.recent_authentication_at' => now()->timestamp])
            ->patch('/profile/security/email', ['email' => 'next@example.test'])
            ->assertRedirect();

        $user->refresh();
        self::assertSame('current@example.test', $user->email);
        self::assertSame('next@example.test', $user->pending_email);
        self::assertNotNull($user->pending_email_requested_at);

        $verificationUrl = URL::temporarySignedRoute(
            'profile.security.email.verify',
            now()->addMinutes(10),
            ['id' => $user->id, 'hash' => sha1('next@example.test')],
        );

        $this->actingAs($user)->get($verificationUrl)->assertRedirect(route('profile.show'));

        $user->refresh();
        self::assertSame('next@example.test', $user->email);
        self::assertNull($user->pending_email);
        self::assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('audit_events', [
            'event' => 'auth.email.changed',
            'actor_user_id' => $user->id,
        ]);
    }

    public function test_google_provider_email_is_metadata_and_never_replaces_account_email(): void
    {
        $user = User::factory()->withoutPassword()->create(['email' => 'account@example.test']);
        $identity = AccountIdentity::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_subject' => 'stable-google-subject',
            'provider_email' => 'old-provider@example.test',
            'provider_email_verified_at' => now(),
            'linked_at' => now(),
            'last_used_at' => now(),
        ]);

        app(RecordAccountIdentityUse::class)->handle(
            $identity->id,
            'updated-provider@example.test',
            true,
        );

        self::assertSame('account@example.test', $user->refresh()->email);
        self::assertSame('stable-google-subject', $identity->refresh()->provider_subject);
        self::assertSame('updated-provider@example.test', $identity->provider_email);
    }

    public function test_account_security_alert_uses_communications_delivery(): void
    {
        $user = User::factory()->create();

        app(SecurityNotificationService::class)->publish(
            userId: (int) $user->id,
            event: 'auth.test',
            title: 'Security change',
            body: 'A security change occurred.',
            idempotencyKey: 'account-security-test:'.$user->id,
        );

        $this->assertDatabaseHas('notification_messages', [
            'notification_type' => 'account.security',
            'recipient_user_id' => $user->id,
            'player_id' => null,
            'title' => 'Security change',
        ]);
        $messageId = DB::table('notification_messages')
            ->where('notification_type', 'account.security')
            ->where('recipient_user_id', $user->id)
            ->value('id');
        self::assertIsString($messageId);
        $this->assertDatabaseHas('notification_deliveries', [
            'notification_message_id' => $messageId,
            'channel' => 'in_app',
            'status' => 'sent',
        ]);
    }

    public function test_deletion_request_can_be_cancelled_during_cooling_off_period(): void
    {
        $user = User::factory()->create();
        $requestDeletion = app(RequestAccountDeletion::class);
        $cancelDeletion = app(CancelAccountDeletion::class);

        $requestId = $requestDeletion->handle((int) $user->id);

        $this->assertDatabaseHas('account_deletion_requests', [
            'id' => $requestId,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
        self::assertNotNull($user->refresh()->deletion_requested_at);

        self::assertTrue($cancelDeletion->handle((int) $user->id));
        $this->assertDatabaseHas('account_deletion_requests', [
            'id' => $requestId,
            'status' => 'cancelled',
        ]);
        self::assertNull($user->refresh()->deletion_requested_at);
    }

    public function test_final_anonymization_invalidates_every_account_authentication_surface(): void
    {
        $user = User::factory()->create(['email' => 'delete-me@example.test']);
        AccountIdentity::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_subject' => 'delete-subject',
            'provider_email' => 'provider-delete@example.test',
            'provider_email_verified_at' => now(),
            'linked_at' => now(),
            'last_used_at' => now(),
        ]);
        DB::table('passkeys')->insert([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'name' => 'Deletion test passkey',
            'credential_id' => 'test-credential-id',
            'credential' => json_encode(['publicKeyCredentialSource' => 'test'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        AccountSession::query()->create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'session_id_hash' => hash('sha256', 'registered-session'),
            'session_id' => 'registered-session',
            'browser_family' => 'Chrome',
            'platform_family' => 'Windows',
            'device_family' => 'Desktop',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
        $user->createToken('test-token');
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => hash('sha256', 'reset-token'),
            'created_at' => now(),
        ]);

        app(AnonymizeAccount::class)->handle((int) $user->id, 'deletion-request-test');

        $user->refresh();
        self::assertNotNull($user->anonymized_at);
        self::assertNull($user->getRawOriginal('password'));
        self::assertNull($user->pending_email);
        self::assertNull($user->two_factor_secret);
        self::assertSame(0, $user->accountIdentities()->count());
        self::assertSame(0, $user->tokens()->count());
        self::assertSame(0, DB::table('passkeys')->where('user_id', $user->id)->count());
        self::assertSame(0, AccountSession::query()->where('user_id', $user->id)->count());
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'delete-me@example.test']);
    }
}
