<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Accounts\Authentication;

use App\Contexts\Accounts\Authentication\Actions\ConnectGoogleAccount;
use App\Contexts\Accounts\Authentication\Actions\RecordAccountSession;
use App\Contexts\Accounts\Authentication\Models\AccountSession;
use App\Contexts\Accounts\Identity\Actions\RemoveAccountIdentity;
use App\Contexts\Accounts\Identity\Models\AccountIdentity;
use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\v3\TestCase;

final class GoogleCredentialAtomicityV3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:41::'.dechex(crc32($this->name()) & 0xFFFF)]);
    }

    /** @return iterable<string,array{bool}> */
    public static function mutations(): iterable
    {
        yield 'connect' => [false];
        yield 'disconnect' => [true];
    }

    #[DataProvider('mutations')]
    public function test_security_intent_failure_rolls_back_identity_and_required_effects(bool $disconnect): void
    {
        $user = User::factory()->create();
        if ($disconnect) {
            app(ConnectGoogleAccount::class)->handle((int) $user->id, 'verified-google-subject', 'provider@example.test');
        }
        app(RecordAccountSession::class)->handle((int) $user->id, 'other-google-browser', 'Chrome/');
        $before = $this->state($user);
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "notification_messages"')) {
                $failed = true;
                throw new RuntimeException('Injected Google security intent failure.');
            }
        });

        try {
            if ($disconnect) {
                app(RemoveAccountIdentity::class)->handle((int) $user->id, 'google', null);
            } else {
                app(ConnectGoogleAccount::class)->handle((int) $user->id, 'verified-google-subject', 'provider@example.test');
            }
            self::fail('The owner must persist security intent.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected Google security intent failure.', $exception->getMessage());
        }

        self::assertTrue($failed);
        self::assertSame($before, $this->state($user));
    }

    public function test_same_subject_replay_refreshes_provider_metadata_without_duplicate_connection_effects(): void
    {
        $user = User::factory()->create(['email' => 'account@example.test']);
        $connect = app(ConnectGoogleAccount::class);
        $identityId = $connect->handle((int) $user->id, 'verified-subject', 'first@example.test');

        self::assertSame($identityId, $connect->handle((int) $user->id, 'verified-subject', 'second@example.test'));
        self::assertSame('account@example.test', $user->refresh()->email);
        self::assertSame('second@example.test', AccountIdentity::query()->findOrFail($identityId)->provider_email);
        self::assertSame(1, DB::table('audit_events')->where('event', 'account.google.connected')->count());
        self::assertSame(1, DB::table('notification_messages')->where('subject_id', 'account.google.connected')->count());
        $before = $this->state($user);

        try {
            $connect->handle((int) $user->id, 'different-subject', 'second@example.test');
            self::fail('An existing provider identity cannot be replaced implicitly.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('google', $exception->errors());
        }
        self::assertSame($before, $this->state($user));
    }

    public function test_global_subject_conflict_preserves_the_owner_and_records_rejected_connection(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $connect = app(ConnectGoogleAccount::class);
        $identityId = $connect->handle((int) $owner->id, 'owned-subject', 'provider@example.test');

        try {
            $connect->handle((int) $other->id, 'owned-subject', 'provider@example.test');
            self::fail('One provider subject must belong to one account.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('google', $exception->errors());
        }

        self::assertSame((int) $owner->id, (int) AccountIdentity::query()->findOrFail($identityId)->user_id);
        $this->assertDatabaseMissing('account_identities', ['user_id' => $other->id]);
        $this->assertDatabaseMissing('notification_messages', ['recipient_user_id' => $other->id]);
        $this->assertDatabaseHas('audit_events', ['actor_user_id' => $other->id, 'event' => 'account.google.connection_rejected']);
    }

    public function test_actual_google_callback_connects_to_the_bound_account_and_records_current_proof(): void
    {
        $user = User::factory()->create(['email' => 'account@example.test']);
        $this->googleProvider();

        $this->actingAs($user)->withSession($this->operation((int) $user->id))
            ->get('/auth/google/callback')->assertRedirect(route('profile.show'))
            ->assertSessionMissing('accounts.google_operation')
            ->assertSessionHas('accounts.recent_authentication_method', 'google');

        $identity = AccountIdentity::query()->where('user_id', $user->id)->sole();
        self::assertSame((string) $identity->id, session()->get('accounts.recent_authentication_credential'));
        self::assertSame('account@example.test', $user->refresh()->email);
        $this->assertDatabaseHas('notification_messages', ['recipient_user_id' => $user->id, 'subject_id' => 'account.google.connected']);
    }

    public function test_callback_cannot_connect_a_subject_to_a_different_current_account(): void
    {
        $expected = User::factory()->create();
        $actual = User::factory()->create();
        $this->googleProvider();

        $this->actingAs($actual)->withSession($this->operation((int) $expected->id))
            ->get('/auth/google/callback')->assertForbidden();

        self::assertSame(0, AccountIdentity::query()->count());
        self::assertSame(0, DB::table('notification_messages')->count());
    }

    public function test_actual_disconnect_preserves_current_session_and_revokes_other_browsers_with_security_intent(): void
    {
        $user = User::factory()->create();
        app(ConnectGoogleAccount::class)->handle((int) $user->id, 'disconnect-subject', 'provider@example.test');
        $this->actingAs($user)->withSession(['accounts.recent_authentication_at' => now()->timestamp])
            ->get('/profile')->assertOk();
        $currentId = session()->getId();
        app(RecordAccountSession::class)->handle((int) $user->id, 'another-google-browser', 'Chrome/');

        $this->withCookie((string) config('session.cookie'), $currentId)
            ->delete('/profile/security/google')->assertRedirect(route('profile.show'))
            ->assertSessionMissing('accounts.recent_authentication_at');

        $this->assertDatabaseMissing('account_identities', ['user_id' => $user->id]);
        self::assertNull(AccountSession::query()->where('session_id_hash', hash('sha256', $currentId))->sole()->revoked_at);
        self::assertNotNull(AccountSession::query()->where('session_id_hash', hash('sha256', 'another-google-browser'))->sole()->revoked_at);
        $this->assertDatabaseHas('notification_messages', ['recipient_user_id' => $user->id, 'subject_id' => 'account.google.disconnected']);
    }

    /** @return array<string,mixed> */
    private function state(User $user): array
    {
        return [
            'account' => $user->refresh()->getRawOriginal(),
            'identities' => AccountIdentity::query()->where('user_id', $user->id)->orderBy('id')->get()->toArray(),
            'sessions' => AccountSession::query()->where('user_id', $user->id)->orderBy('id')->get()->toArray(),
            'audit' => DB::table('audit_events')->count(),
            'messages' => DB::table('notification_messages')->count(),
            'deliveries' => DB::table('notification_deliveries')->count(),
        ];
    }

    /** @return array<string,mixed> */
    private function operation(int $userId): array
    {
        return ['accounts.google_operation' => [
            'intent' => 'connect', 'user_id' => $userId, 'invitation_token' => null, 'started_at' => now()->timestamp,
        ]];
    }

    private function googleProvider(): void
    {
        config()->set('services.google', [
            'client_id' => 'fixture-client', 'client_secret' => 'fixture-secret', 'redirect' => 'http://localhost/auth/google/callback',
        ]);
        $identity = (new SocialiteUser)->setRaw(['email_verified' => true])->map([
            'id' => 'verified-callback-subject', 'name' => 'Provider fixture', 'email' => 'provider@example.test',
        ]);
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($identity);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
    }
}
