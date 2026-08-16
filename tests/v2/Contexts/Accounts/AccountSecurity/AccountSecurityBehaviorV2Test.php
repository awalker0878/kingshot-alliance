<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Accounts\AccountSecurity;

use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\v2\Support\ScenarioFactory;
use Tests\v2\TestCase;

final class AccountSecurityBehaviorV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_registration_canonicalizes_identity_and_commits_audit_and_outbox(): void
    {
        $user = (new ScenarioFactory)->user('V2.USER@Example.Test');

        self::assertSame('v2.user@example.test', $user->email);
        self::assertTrue(Hash::check('Correct-Horse-Battery-Staple-1!', $user->password));
        self::assertTrue(AuditEvent::query()->where('event', 'user.registered')->where('actor_user_id', $user->id)->exists());
        self::assertTrue(OutboxMessage::query()->where('event_type', 'user.registered')->where('aggregate_id', (string) $user->id)->exists());
    }
}
