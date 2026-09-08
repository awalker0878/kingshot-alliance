<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Accounts\Authentication;

use App\Contexts\Accounts\Authentication\Models\AccountPasskey;
use App\Contexts\Accounts\Authentication\Services\AccountSignInMethodPolicy;
use App\Contexts\Accounts\Credentials\Actions\RemovePassword;
use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Actions\DeletePasskey;
use Tests\v3\TestCase;

final class PasskeyMutationSerializationV3Test extends TestCase
{
    // The competing connection must see committed fixtures, outside a test transaction.
    use DatabaseMigrations;

    public function test_competing_password_removal_cannot_pass_the_check_during_passkey_deletion(): void
    {
        $user = User::factory()->create();
        $passkey = new AccountPasskey;
        $passkey->forceFill([
            'user_id' => $user->id, 'name' => 'Concurrent removal', 'credential_id' => 'concurrent-removal',
            'credential' => ['test' => true],
        ])->saveOrFail();

        $primaryConnection = DB::getDefaultConnection();
        config()->set('database.connections.credential_competitor', array_replace(DB::connection()->getConfig(), ['name' => 'credential_competitor']));
        $competing = DB::connection('credential_competitor');
        $competing->statement("SET lock_timeout = '100ms'");
        $attempted = false;
        $blocked = false;

        DB::listen(static function (QueryExecuted $query) use ($user, $primaryConnection, &$attempted, &$blocked): void {
            if ($attempted || $query->connectionName !== $primaryConnection
                || ! str_contains($query->sql, '"users"') || ! str_contains($query->sql, 'for update')) {
                return;
            }
            $attempted = true;

            DB::setDefaultConnection('credential_competitor');
            try {
                app(RemovePassword::class)->handle((int) $user->id, null);
            } catch (QueryException $error) {
                if (($error->errorInfo[0] ?? null) !== '55P03') {
                    throw $error;
                }
                $blocked = true;
            } finally {
                DB::setDefaultConnection($primaryConnection);
            }
        });

        try {
            app(DeletePasskey::class)($user, $passkey);

            self::assertTrue($attempted, 'Exercise the competing owner Action while passkey deletion holds the account lock.');
            self::assertTrue($blocked, 'The competing credential mutation must wait for the account lock.');
            self::assertTrue($user->refresh()->supportsPasswordAuthentication());
            self::assertSame(1, app(AccountSignInMethodPolicy::class)->usableMethodCount($user));
            $this->assertDatabaseMissing('passkeys', ['id' => $passkey->id]);

            $this->expectException(ValidationException::class);
            app(RemovePassword::class)->handle((int) $user->id, null);
        } finally {
            DB::setDefaultConnection($primaryConnection);
            DB::purge('credential_competitor');
        }
    }
}
