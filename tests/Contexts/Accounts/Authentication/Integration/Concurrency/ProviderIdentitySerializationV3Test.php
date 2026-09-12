<?php

declare(strict_types=1);

namespace Tests\Contexts\Accounts\Authentication\Integration\Concurrency;

use App\Contexts\Accounts\Authentication\Actions\ConnectGoogleAccount;
use App\Contexts\Accounts\Identity\Actions\RecordAccountIdentityUse;
use App\Contexts\Accounts\Identity\Actions\RemoveAccountIdentity;
use App\Contexts\Accounts\Identity\Models\AccountIdentity;
use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ProviderIdentitySerializationV3Test extends TestCase
{
    use DatabaseTruncation;

    public function test_provider_use_holds_account_before_identity_so_removal_cannot_take_the_opposite_lock_order(): void
    {
        $user = User::factory()->create();
        $identityId = app(ConnectGoogleAccount::class)->handle((int) $user->id, 'serialized-subject', 'before@example.test');
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.provider_competitor', array_replace(DB::connection()->getConfig(), ['name' => 'provider_competitor']));
        DB::connection('provider_competitor')->statement("SET lock_timeout = '100ms'");
        $attempted = false;
        $blockedOnAccount = false;

        DB::listen(static function (QueryExecuted $query) use ($user, $primary, &$attempted, &$blockedOnAccount): void {
            if ($attempted || $query->connectionName !== $primary
                || ! str_contains($query->sql, '"account_identities"') || ! str_contains($query->sql, 'for update')) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('provider_competitor');
            try {
                app(RemoveAccountIdentity::class)->handle((int) $user->id, 'google', null);
            } catch (QueryException $exception) {
                if (($exception->errorInfo[0] ?? null) !== '55P03') {
                    throw $exception;
                }
                $blockedOnAccount = str_contains($exception->getSql(), '"users"');
            } finally {
                DB::setDefaultConnection($primary);
            }
        });

        try {
            app(RecordAccountIdentityUse::class)->handle($identityId, 'after@example.test', true);

            self::assertTrue($attempted);
            self::assertTrue($blockedOnAccount, 'Competing removal must wait for the account before either operation can invert account/identity locks.');
            self::assertSame('after@example.test', AccountIdentity::query()->findOrFail($identityId)->provider_email);

            app(RemoveAccountIdentity::class)->handle((int) $user->id, 'google', null);
            $this->assertDatabaseMissing('account_identities', ['id' => $identityId]);
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('provider_competitor');
        }
    }
}
