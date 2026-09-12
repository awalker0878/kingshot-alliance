<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\EmailVerification\Services;

use App\Contexts\Accounts\EmailVerification\Notifications\KingshotAllianceEmailChangedNotice;
use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\Messaging\Outbox\Events\OutboxPublished;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use UnexpectedValueException;

final readonly class EmailChangedNoticeOutbox
{
    public const EVENT_TYPE = 'account.email.changed_notice_requested';

    public function __construct(private OutboxRecorder $outbox) {}

    // The profile owner calls this with its locked User inside email promotion.
    public function queue(User $user, string $previousEmail): void
    {
        $this->outbox->record(
            eventType: self::EVENT_TYPE,
            allianceId: null,
            aggregate: $user,
            payload: ['encrypted_recipient' => Crypt::encryptString(json_encode([
                'previous_email' => $previousEmail,
                'new_email' => (string) $user->email,
            ], JSON_THROW_ON_ERROR))],
        );
    }

    public function deliver(OutboxPublished $event): void
    {
        if ($event->eventType !== self::EVENT_TYPE) {
            return;
        }
        if ($event->aggregateType !== User::class || $event->allianceId !== null || ! ctype_digit($event->aggregateId)) {
            throw new UnexpectedValueException('Invalid account email-change notice scope.');
        }
        $user = User::query()->find($event->aggregateId);
        if ($user === null || $user->anonymized_at !== null) {
            $this->forgetAccount((int) $event->aggregateId);

            return;
        }

        $encrypted = $event->payload['encrypted_recipient'] ?? null;
        if (! is_string($encrypted)) {
            throw new UnexpectedValueException('Missing account email-change notice recipient.');
        }
        $recipient = json_decode(Crypt::decryptString($encrypted), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($recipient) || ! is_string($recipient['previous_email'] ?? null)
            || ! is_string($recipient['new_email'] ?? null)
            || ! filter_var($recipient['previous_email'], FILTER_VALIDATE_EMAIL)
            || ! filter_var($recipient['new_email'], FILTER_VALIDATE_EMAIL)) {
            throw new UnexpectedValueException('Invalid account email-change notice recipient.');
        }

        Notification::route('mail', $recipient['previous_email'])
            ->notify(new KingshotAllianceEmailChangedNotice($recipient['new_email']));
    }

    public function forgetAccount(int $userId): void
    {
        OutboxMessage::query()->where('event_type', self::EVENT_TYPE)
            ->where('aggregate_type', User::class)->where('aggregate_id', (string) $userId)->delete();
    }
}
