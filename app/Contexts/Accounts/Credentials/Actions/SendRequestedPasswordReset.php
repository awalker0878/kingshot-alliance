<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Credentials\Actions;

use App\Contexts\Accounts\Credentials\Notifications\ResetKingshotAlliancePassword;
use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\Messaging\Outbox\Events\OutboxPublished;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

final class SendRequestedPasswordReset
{
    public function handle(OutboxPublished $event): void
    {
        if ($event->eventType !== QueuePasswordResetDelivery::EVENT_TYPE) {
            return;
        }
        $deliveryId = $event->payload['delivery_id'] ?? null;
        if ($event->aggregateType !== User::class || $event->allianceId !== null || ! ctype_digit($event->aggregateId)
            || ! is_string($deliveryId) || ! Str::isUuid($deliveryId)) {
            throw new UnexpectedValueException('Invalid password reset delivery scope.');
        }

        $delivery = DB::transaction(static function () use ($event, $deliveryId): ?array {
            $user = User::query()->whereKey($event->aggregateId)->lockForUpdate()->first();
            if (! $user instanceof User) {
                return null;
            }
            $query = DB::table('password_reset_tokens')->where('email', (string) $user->email)->where('delivery_id', $deliveryId);
            $record = $query->first();
            if ($record === null || ! is_string($record->encrypted_delivery_token) || $record->encrypted_delivery_token === '') {
                return null;
            }

            $token = Crypt::decryptString($record->encrypted_delivery_token);
            if (! $user->isActive() || ! $user->supportsPasswordAuthentication() || ! Password::tokenExists($user, $token)) {
                $query->update(['encrypted_delivery_token' => null]);

                return null;
            }

            return ['user' => $user, 'token' => $token];
        });
        if ($delivery === null) {
            return;
        }

        try {
            $delivery['user']->notify(new ResetKingshotAlliancePassword($delivery['token']));
        } catch (Throwable) {
            // A transport exception may contain the recovery URL; retain no original message or cause.
            throw new RuntimeException('Password reset email delivery failed.');
        }

        DB::table('password_reset_tokens')->where('email', (string) $delivery['user']->email)
            ->where('delivery_id', $deliveryId)->update(['encrypted_delivery_token' => null]);
        event(new PasswordResetLinkSent($delivery['user']));
    }
}
