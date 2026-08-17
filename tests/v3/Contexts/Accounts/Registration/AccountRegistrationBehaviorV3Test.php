<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Accounts\Registration;

use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class AccountRegistrationBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_registration_canonicalizes_identity_and_commits_audit_and_outbox(): void
    {
        $registered = (new ScenarioFactory)->account('V3.USER@Example.Test');
        $user = User::query()->findOrFail($registered->userId);

        self::assertSame('v3.user@example.test', $registered->email);
        self::assertSame('v3.user@example.test', $user->email);
        self::assertTrue(Hash::check('Correct-Horse-Battery-Staple-V3!', (string) $user->password));
        self::assertTrue(AuditEvent::query()->where('event', 'user.registered')->where('actor_user_id', $registered->userId)->exists());
        self::assertTrue(OutboxMessage::query()->where('event_type', 'user.registered')->where('aggregate_id', (string) $registered->userId)->exists());
    }
}
