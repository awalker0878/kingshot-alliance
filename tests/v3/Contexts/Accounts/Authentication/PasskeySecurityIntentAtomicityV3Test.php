<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Accounts\Authentication;

use App\Contexts\Accounts\Authentication\Actions\RecordAccountSession;
use App\Contexts\Accounts\Authentication\Actions\RenameAccountPasskey;
use App\Contexts\Accounts\Authentication\Models\AccountPasskey;
use App\Contexts\Accounts\Authentication\Models\AccountSession;
use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\v3\Support\WebAuthnRegistrationFixture;
use Tests\v3\TestCase;

final class PasskeySecurityIntentAtomicityV3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('passkeys.relying_party_id', 'accounts.example.test');
        config()->set('passkeys.allowed_origins', ['https://accounts.example.test']);
    }

    public function test_registration_failure_rolls_back_the_real_package_credential_and_event_effects(): void
    {
        $user = User::factory()->create();
        $before = $this->state($user);
        $this->failNextSecurityIntent();

        try {
            $this->register($user);
            self::fail('The registration event must persist security intent.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected passkey security intent failure.', $exception->getMessage());
        }

        self::assertSame($before, $this->state($user));
    }

    public function test_successful_registration_uses_maintained_validation_and_rejects_a_duplicate_credential(): void
    {
        $user = User::factory()->create();
        $passkey = $this->register($user);
        self::assertSame((int) $user->id, (int) $passkey->user_id);
        self::assertTrue($passkey->credential['uvInitialized']);
        $this->assertDatabaseHas('notification_messages', ['recipient_user_id' => $user->id, 'subject_id' => 'account.passkey.registered']);
        $this->assertDatabaseHas('audit_events', ['actor_user_id' => $user->id, 'event' => 'account.passkey.registered']);

        $this->expectException(InvalidPasskeyException::class);
        // A different account's options avoid the excluded-credential check,
        // exercising the maintained globally unique credential boundary.
        $this->register(User::factory()->create());
    }

    /** @return iterable<string,array{string}> */
    public static function invalidCeremonies(): iterable
    {
        yield 'wrong origin' => ['origin'];
        yield 'wrong challenge' => ['challenge'];
        yield 'no user verification' => ['verification'];
    }

    #[DataProvider('invalidCeremonies')]
    public function test_account_adapter_preserves_maintained_ceremony_rejections(string $invalid): void
    {
        $user = User::factory()->create();
        $options = app(GenerateRegistrationOptions::class)($user);
        $credential = WebAuthnRegistrationFixture::credential(
            $options,
            origin: $invalid === 'origin' ? 'https://foreign.example.test' : 'https://accounts.example.test',
            challenge: $invalid === 'challenge' ? 'another-challenge' : null,
            userVerified: $invalid !== 'verification',
        );
        $before = $this->state($user);

        try {
            app(StorePasskey::class)($user, 'Rejected key', $credential, $options);
            self::fail('The maintained validator must reject the invalid ceremony.');
        } catch (InvalidPasskeyException $exception) {
            self::assertArrayHasKey('credential', $exception->errors());
            self::assertSame($before, $this->state($user));
        }
    }

    public function test_delete_failure_rolls_back_passkey_session_audit_and_preserves_recent_proof(): void
    {
        $user = User::factory()->create();
        $passkey = $this->register($user);
        $this->actingAs($user)->withSession(['accounts.recent_authentication_at' => now()->timestamp])
            ->get('/profile')->assertOk();
        $currentId = session()->getId();
        app(RecordAccountSession::class)->handle((int) $user->id, 'another-passkey-browser', 'Chrome/');
        $before = $this->state($user);
        $this->failNextSecurityIntent();
        $this->withoutExceptionHandling();

        try {
            $this->withCredentials()->withCookie((string) config('session.cookie'), $currentId)
                ->deleteJson('/user/passkeys/'.$passkey->public_id);
            self::fail('The deletion event must persist security intent.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected passkey security intent failure.', $exception->getMessage());
        }

        self::assertSame($before, $this->state($user));
        self::assertNotNull(session()->get('accounts.recent_authentication_at'));
    }

    public function test_rename_replay_is_a_noop_but_returning_to_a_prior_name_has_a_new_security_intent(): void
    {
        $user = User::factory()->create();
        $passkey = $this->register($user);
        $rename = app(RenameAccountPasskey::class);

        $rename->handle((int) $user->id, $passkey->public_id, 'Second name');
        $rename->handle((int) $user->id, $passkey->public_id, 'Second name');
        $rename->handle((int) $user->id, $passkey->public_id, 'First name');
        $rename->handle((int) $user->id, $passkey->public_id, 'Second name');

        self::assertSame('Second name', $passkey->refresh()->name);
        self::assertSame(3, DB::table('notification_messages')->where('subject_id', 'account.passkey.renamed')->count());
        self::assertSame(3, DB::table('audit_events')->where('event', 'account.passkey.renamed')->count());
    }

    public function test_rename_failure_preserves_the_prior_name_and_security_state(): void
    {
        $user = User::factory()->create();
        $passkey = $this->register($user);
        $before = $this->state($user);
        $this->failNextSecurityIntent();

        try {
            app(RenameAccountPasskey::class)->handle((int) $user->id, $passkey->public_id, 'Uncommitted name');
            self::fail('The rename must persist security intent.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected passkey security intent failure.', $exception->getMessage());
        }

        self::assertSame($before, $this->state($user));
    }

    private function register(User $user): AccountPasskey
    {
        $options = app(GenerateRegistrationOptions::class)($user);
        $passkey = app(StorePasskey::class)($user, 'First name', WebAuthnRegistrationFixture::credential($options), $options);
        self::assertInstanceOf(AccountPasskey::class, $passkey);

        return $passkey;
    }

    private function failNextSecurityIntent(): void
    {
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "notification_messages"')) {
                $failed = true;
                throw new RuntimeException('Injected passkey security intent failure.');
            }
        });
    }

    /** @return array<string,mixed> */
    private function state(User $user): array
    {
        return [
            'remember' => $user->refresh()->getRememberToken(),
            'passkeys' => AccountPasskey::query()->where('user_id', $user->id)->orderBy('id')->get()->toArray(),
            'sessions' => AccountSession::query()->where('user_id', $user->id)->orderBy('id')->get(['id', 'revoked_at'])->toArray(),
            'audit' => DB::table('audit_events')->count(),
            'messages' => DB::table('notification_messages')->count(),
            'deliveries' => DB::table('notification_deliveries')->count(),
        ];
    }
}
