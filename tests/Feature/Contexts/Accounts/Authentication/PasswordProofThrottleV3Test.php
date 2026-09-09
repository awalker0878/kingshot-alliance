<?php

declare(strict_types=1);

namespace Tests\Feature\Contexts\Accounts\Authentication;

use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\RateLimiter as LaravelRateLimiter;
use Illuminate\Cache\Repository;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class PasswordProofThrottleV3Test extends TestCase
{
    use DatabaseMigrations;

    public function test_confirmation_and_password_changes_share_a_per_account_attempt_budget(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $password = $user->getRawOriginal('password');
        $this->actingAs($user);
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($attempt + 1)]);
            if ($attempt % 2 === 0) {
                $this->postJson('/confirm-password', ['password' => 'wrong'])
                    ->assertUnprocessable()->assertJsonValidationErrors('password');
            } else {
                $this->putJson('/profile/password', [
                    'current_password' => 'wrong',
                    'password' => 'ChangedPassword123!',
                    'password_confirmation' => 'ChangedPassword123!',
                ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
            }
        }
        $this->flushSession();
        $this->postJson('/confirm-password', ['password' => 'password'])
            ->assertStatus(429)->assertHeader('Retry-After')->assertSessionMissing('accounts.recent_authentication_at');
        $this->putJson('/profile/password', [
            'current_password' => 'password',
            'password' => 'ChangedPassword123!',
            'password_confirmation' => 'ChangedPassword123!',
        ])->assertStatus(429);
        self::assertSame($password, $user->refresh()->getRawOriginal('password'));
        self::assertSame(0, DB::table('audit_events')->whereIn('event', ['auth.password.confirmed', 'profile.password.updated'])->count());

        $this->actingAs($other)->post('/confirm-password', ['password' => 'password'])
            ->assertRedirect()->assertSessionHas('accounts.recent_authentication_at');
    }

    public function test_password_proof_budget_recovers_after_its_bounded_window(): void
    {
        $this->freezeSecond();
        // A clock-aware store makes window expiry deterministic; the other
        // HTTP case exercises the configured store, including Redis in CI.
        RateLimiter::swap(new LaravelRateLimiter(new Repository(new ArrayStore)));
        $this->actingAs(User::factory()->create());
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postJson('/confirm-password', ['password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson('/confirm-password', ['password' => 'password'])->assertStatus(429)->assertHeader('Retry-After', '60');
        $this->travel(61)->seconds();
        $this->post('/confirm-password', ['password' => 'password'])
            ->assertRedirect()->assertSessionHas('accounts.recent_authentication_at', now()->timestamp);
    }
}
