<?php

declare(strict_types=1);

namespace Tests\Feature\Contexts\Accounts\Profile;

use App\Contexts\Accounts\EmailVerification\Actions\RequestEmailVerification;
use App\Contexts\Accounts\Identity\Models\User;
use Closure;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\RateLimiter as LaravelRateLimiter;
use Illuminate\Cache\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class EmailChangeThrottleV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_email_change_is_bounded_per_account_across_ips_and_recovers_after_the_window(): void
    {
        $this->freezeSecond();
        // Retain the application's real limiter definition with isolated storage.
        $definition = RateLimiter::limiter('account-email-change');
        self::assertInstanceOf(Closure::class, $definition);
        $limiter = new LaravelRateLimiter(new Repository(new ArrayStore));
        $limiter->for('account-email-change', $definition);
        RateLimiter::swap($limiter);
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->withSession(['accounts.recent_authentication_at' => now()->timestamp]);

        for ($index = 1; $index <= 6; $index++) {
            $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:34::'.$index])
                ->patch('/profile/security/email', ['email' => 'pending-'.$index.'@example.test'])
                ->assertRedirect()->assertSessionHasNoErrors();
        }
        $before = $this->state($user);
        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:34::7'])
            ->patch('/profile/security/email', ['email' => 'excess@example.test'])
            ->assertStatus(429)->assertHeader('Retry-After', '60');
        self::assertSame($before, $this->state($user));
        self::assertSame('pending-6@example.test', $user->refresh()->pending_email);
        self::assertSame(6, $this->verificationCount($user));

        // Another account retains its own budget even on the rejected client's IP.
        $this->actingAs($other)->patch('/profile/security/email', ['email' => 'other-pending@example.test'])
            ->assertRedirect()->assertSessionHasNoErrors();
        self::assertSame(1, $this->verificationCount($other));
        self::assertSame('other-pending@example.test', $other->refresh()->pending_email);

        $this->travel(61)->seconds();
        $this->actingAs($user)->patch('/profile/security/email', ['email' => 'after-window@example.test'])
            ->assertRedirect()->assertSessionHasNoErrors();
        self::assertSame(7, $this->verificationCount($user));
        self::assertSame('after-window@example.test', $user->refresh()->pending_email);
        self::assertSame(1, $this->verificationCount($other));
    }

    private function verificationCount(User $user): int
    {
        return DB::table('outbox_messages')->where('event_type', RequestEmailVerification::EVENT_TYPE)
            ->where('aggregate_id', (string) $user->id)->count();
    }

    /** @return array<string,mixed> */
    private function state(User $user): array
    {
        return [
            'user' => $user->refresh()->getRawOriginal(),
            'verification' => $this->verificationCount($user),
            'audit' => DB::table('audit_events')->where('actor_user_id', $user->id)->count(),
            'messages' => DB::table('notification_messages')->where('recipient_user_id', $user->id)->count(),
        ];
    }
}
