<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Accounts\Authentication;

use App\Contexts\Accounts\Credentials\Actions\AddPassword;
use App\Contexts\Accounts\Credentials\Actions\RemovePassword;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\TotpService;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\TwoFactorManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\v3\TestCase;

final class CredentialSecurityIntentAtomicityV3Test extends TestCase
{
    use RefreshDatabase;

    /** @return iterable<string,array{string,string}> */
    public static function mutations(): iterable
    {
        yield 'add-password' => ['add-password', 'account.password.added'];
        yield 'remove-password' => ['remove-password', 'account.password.removed'];
        yield 'enable-mfa' => ['enable-mfa', 'auth.mfa.enabled'];
        yield 'regenerate-mfa' => ['regenerate-mfa', 'auth.mfa.recovery_codes_regenerated'];
        yield 'disable-mfa' => ['disable-mfa', 'auth.mfa.disabled'];
    }

    #[DataProvider('mutations')]
    public function test_failed_security_intent_rolls_back_credential_audit_outbox_and_reset_token_changes(string $operation, string $event): void
    {
        $user = $this->account($operation);
        $before = $this->state($user);
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "notification_messages"')) {
                $failed = true;
                throw new RuntimeException('Injected credential security intent failure.');
            }
        });

        try {
            $this->mutate($user, $operation);
            self::fail('Security intent persistence failure must reach the owner caller.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected credential security intent failure.', $exception->getMessage());
        }

        self::assertTrue($failed, $event.' must persist its security intent.');
        self::assertSame($before, $this->state($user));
    }

    #[DataProvider('mutations')]
    public function test_successful_mutation_keeps_its_credential_contract_and_security_intent(string $operation, string $event): void
    {
        $user = $this->account($operation);
        $codes = $this->mutate($user, $operation);
        $user->refresh();

        self::assertSame(1, DB::table('notification_messages')->where('recipient_user_id', $user->id)->where('subject_id', $event)->count());
        self::assertSame(1, DB::table('audit_events')->where('actor_user_id', $user->id)->where('event', $event)->count());

        if ($operation === 'add-password') {
            self::assertTrue(Hash::check('After-Password-321!', (string) $user->password));
        } elseif ($operation === 'remove-password') {
            self::assertNull($user->getRawOriginal('password'));
            self::assertSame(0, $user->tokens()->count());
            $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        } elseif ($operation === 'disable-mfa') {
            self::assertNull($user->two_factor_secret);
            self::assertNull($user->two_factor_confirmed_at);
            self::assertNull($user->two_factor_recovery_codes);
        } else {
            self::assertNotNull($user->two_factor_confirmed_at);
            self::assertCount(8, $codes);
            self::assertSame(array_map(static fn (string $code): string => hash('sha256', $code), $codes), $user->two_factor_recovery_codes);
        }
    }

    /** @return list<string> */
    private function mutate(User $user, string $operation): array
    {
        if ($operation === 'add-password') {
            app(AddPassword::class)->handle((int) $user->id, 'After-Password-321!');

            return [];
        }
        if ($operation === 'remove-password') {
            app(RemovePassword::class)->handle((int) $user->id);

            return [];
        }
        $mfa = app(TwoFactorManager::class);
        if ($operation === 'disable-mfa') {
            $mfa->disable($user);

            return [];
        }
        if ($operation === 'regenerate-mfa') {
            return $mfa->regenerateRecoveryCodes($user);
        }
        $code = app(TotpService::class)->codeForCounter((string) $user->two_factor_secret, intdiv(time(), 30));

        return $mfa->confirm($user, $code);
    }

    private function account(string $operation): User
    {
        $user = User::factory()->google()->create([
            'password' => $operation === 'remove-password' ? Hash::make('Before-Password-123!') : null,
            'two_factor_secret' => str_contains($operation, 'mfa') ? 'JBSWY3DPEHPK3PXP' : null,
            'two_factor_confirmed_at' => in_array($operation, ['disable-mfa', 'regenerate-mfa'], true) ? now() : null,
            'two_factor_recovery_codes' => in_array($operation, ['disable-mfa', 'regenerate-mfa'], true) ? [hash('sha256', 'prior-recovery-code')] : null,
        ]);
        $user->createToken('Credential transaction fixture');
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => hash('sha256', 'prior-reset-token'),
            'created_at' => now(),
        ]);

        return $user;
    }

    /** @return array<string,mixed> */
    private function state(User $user): array
    {
        return [
            'account' => $user->refresh()->getRawOriginal(),
            'tokens' => $user->tokens()->orderBy('id')->get()->toArray(),
            'reset' => (array) DB::table('password_reset_tokens')->where('email', $user->email)->first(),
            'audit' => DB::table('audit_events')->count(),
            'outbox' => DB::table('outbox_messages')->count(),
            'messages' => DB::table('notification_messages')->count(),
            'deliveries' => DB::table('notification_deliveries')->count(),
        ];
    }
}
