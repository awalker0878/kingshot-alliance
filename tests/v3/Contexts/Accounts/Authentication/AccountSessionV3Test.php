<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Accounts\Authentication;

use App\Contexts\Accounts\Authentication\Models\AccountSession;
use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\v3\TestCase;

final class AccountSessionV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_web_request_registers_privacy_safe_session_metadata(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0) AppleWebKit Chrome/120.0 Safari/537.36')
            ->get('/profile')
            ->assertOk();

        $session = AccountSession::query()->where('user_id', $user->id)->firstOrFail();
        self::assertSame('Chrome', $session->browser_family);
        self::assertSame('Windows', $session->platform_family);
        self::assertSame('Desktop', $session->device_family);
        self::assertNotSame('', $session->session_id);
        self::assertSame(64, strlen($session->session_id_hash));
    }

    public function test_user_cannot_revoke_another_users_registered_session(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $record = AccountSession::query()->create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $other->id,
            'session_id_hash' => hash('sha256', 'other-session'),
            'session_id' => 'other-session',
            'browser_family' => 'Browser',
            'platform_family' => 'Unknown platform',
            'device_family' => 'Desktop',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->delete('/profile/security/sessions/'.$record->public_id)
            ->assertNotFound();
    }
}
