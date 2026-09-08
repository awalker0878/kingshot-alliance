<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\EmailVerification\Actions;

use App\Contexts\Accounts\EmailVerification\Enums\EmailVerificationTarget;
use App\Contexts\Accounts\EmailVerification\Notifications\VerifyKingshotAllianceEmail;
use App\Contexts\Accounts\EmailVerification\Notifications\VerifyPendingKingshotAllianceEmail;
use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\Messaging\Outbox\Events\OutboxPublished;
use Illuminate\Support\Facades\Notification;
use UnexpectedValueException;

final class SendRequestedEmailVerification
{
    public function handle(OutboxPublished $event): void
    {
        if ($event->eventType !== RequestEmailVerification::EVENT_TYPE) {
            return;
        }
        $emailHash = $event->payload['email_hash'] ?? null;
        $targetValue = $event->payload['target'] ?? null;
        $target = is_string($targetValue) ? EmailVerificationTarget::tryFrom($targetValue) : null;
        if ($event->aggregateType !== User::class || $event->allianceId !== null
            || ! ctype_digit($event->aggregateId) || $target === null || ! is_string($emailHash) || strlen($emailHash) !== 64) {
            throw new UnexpectedValueException('Invalid account verification intent.');
        }

        $user = User::query()->find($event->aggregateId);
        if ($user === null || ! $user->isActive()
            || ($target === EmailVerificationTarget::Account && $user->hasVerifiedEmail())) {
            return;
        }

        $email = $target === EmailVerificationTarget::Account ? (string) $user->email : (string) $user->pending_email;
        if ($email === '' || ! hash_equals($emailHash, hash('sha256', $email))) {
            return;
        }

        // Outbox publication invokes consumers after its claim commits. Failure
        // propagates to the existing retry policy; signed links are made at send.
        if ($target === EmailVerificationTarget::Account) {
            $user->notify(new VerifyKingshotAllianceEmail);
        } else {
            Notification::route('mail', $email)->notify(new VerifyPendingKingshotAllianceEmail((int) $user->id, sha1($email)));
        }
    }
}
