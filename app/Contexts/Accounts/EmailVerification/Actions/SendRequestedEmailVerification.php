<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\EmailVerification\Actions;

use App\Contexts\Accounts\EmailVerification\Notifications\VerifyKingshotAllianceEmail;
use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\Messaging\Outbox\Events\OutboxPublished;
use UnexpectedValueException;

final class SendRequestedEmailVerification
{
    public function handle(OutboxPublished $event): void
    {
        if ($event->eventType !== RequestEmailVerification::EVENT_TYPE) {
            return;
        }
        $emailHash = $event->payload['email_hash'] ?? null;
        if ($event->aggregateType !== User::class || $event->allianceId !== null
            || ! ctype_digit($event->aggregateId) || ! is_string($emailHash) || strlen($emailHash) !== 64) {
            throw new UnexpectedValueException('Invalid account verification intent.');
        }

        $user = User::query()->find($event->aggregateId);
        if ($user === null || $user->anonymized_at !== null || $user->hasVerifiedEmail()
            || ! hash_equals($emailHash, hash('sha256', (string) $user->email))) {
            return;
        }

        // Outbox publication invokes consumers after its claim commits. Failure
        // propagates to the existing retry policy; signed links are made at send.
        $user->notify(new VerifyKingshotAllianceEmail);
    }
}
