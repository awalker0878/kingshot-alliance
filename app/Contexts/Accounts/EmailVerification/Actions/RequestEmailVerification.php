<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\EmailVerification\Actions;

use App\Contexts\Accounts\EmailVerification\Enums\EmailVerificationTarget;
use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RequestEmailVerification
{
    public const EVENT_TYPE = 'account.email.verification_requested';

    public function handle(int $userId, EmailVerificationTarget $target): void
    {
        DB::transaction(static function () use ($userId, $target): void {
            $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
            $email = $target === EmailVerificationTarget::Account ? (string) $user->email : (string) $user->pending_email;
            if ($user->anonymized_at !== null || $email === ''
                || ($target === EmailVerificationTarget::Account && $user->hasVerifiedEmail())) {
                return;
            }

            OutboxMessage::query()->create([
                'alliance_id' => null,
                'event_type' => self::EVENT_TYPE,
                'aggregate_type' => User::class,
                'aggregate_id' => (string) $userId,
                'idempotency_key' => self::EVENT_TYPE.':'.$userId.':'.Str::ulid(),
                'payload' => ['target' => $target->value, 'email_hash' => hash('sha256', $email)],
                'occurred_at' => now(),
                'available_at' => now(),
                'attempts' => 0,
            ]);
        });
    }
}
