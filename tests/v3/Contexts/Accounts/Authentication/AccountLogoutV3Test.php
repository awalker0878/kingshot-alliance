<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Accounts\Authentication;

use App\Contexts\Accounts\Authentication\Actions\RecordAccountSession;
use App\Contexts\Accounts\Authentication\Models\AccountSession;
use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\v3\TestCase;

final class AccountLogoutV3Test extends TestCase
{
    use DatabaseMigrations;

    public function test_logout_atomically_revokes_only_the_current_session_and_preserves_remembered_browsers(): void
    {
        $user = User::factory()->create();
        $rememberToken = $user->getRememberToken();
        $this->actingAs($user)->get('/profile')->assertOk();
        $currentSessionId = session()->getId();
        self::assertTrue(app(RecordAccountSession::class)->handle(
            (int) $user->id,
            'another-browser-session',
            'Firefox/',
        ));

        $this->delete(route('logout'))->assertRedirect(route('home'));

        $this->assertGuest();
        self::assertNotSame($currentSessionId, session()->getId());
        self::assertNotNull(AccountSession::query()
            ->where('session_id_hash', hash('sha256', $currentSessionId))->sole()->revoked_at);
        self::assertNull(AccountSession::query()
            ->where('session_id_hash', hash('sha256', 'another-browser-session'))->sole()->revoked_at);
        self::assertSame($rememberToken, $user->refresh()->getRememberToken());
        self::assertSame(1, DB::table('audit_events')->where('event', 'auth.logout')->count());
    }

    public function test_audit_failure_rolls_back_revocation_but_still_clears_the_current_browser(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/profile')->assertOk();
        $currentSessionId = session()->getId();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "audit_events"')
                && in_array('auth.logout', $query->bindings, true)) {
                $failed = true;
                throw new RuntimeException('Injected logout audit failure.');
            }
        });

        $this->withoutExceptionHandling();
        try {
            $this->delete(route('logout'));
            self::fail('The injected audit failure must abort the durable logout transaction.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected logout audit failure.', $exception->getMessage());
        }

        self::assertTrue($failed);
        $this->assertGuest();
        self::assertNotSame($currentSessionId, session()->getId());
        self::assertNull(AccountSession::query()->sole()->revoked_at);
        self::assertSame(0, DB::table('audit_events')->where('event', 'auth.logout')->count());
    }
}
