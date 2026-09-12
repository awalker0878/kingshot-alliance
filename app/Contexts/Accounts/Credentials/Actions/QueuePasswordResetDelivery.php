<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Credentials\Actions;

use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use SensitiveParameter;

final readonly class QueuePasswordResetDelivery
{
    public const EVENT_TYPE = 'account.password.reset_delivery_requested';

    public function __construct(private OutboxRecorder $outbox) {}

    public function handle(int $userId, #[SensitiveParameter] string $token): void
    {
        DB::transaction(function () use ($userId, $token): void {
            $user = User::query()->whereKey($userId)->lockForUpdate()->first();
            if (! $user instanceof User || ! $user->isActive() || ! $user->supportsPasswordAuthentication()
                || ! Password::tokenExists($user, $token)) {
                return;
            }

            $record = DB::table('password_reset_tokens')->where('email', (string) $user->email)->first();
            if ($record === null || $record->delivery_id !== null) {
                return;
            }

            $deliveryId = (string) Str::uuid();
            DB::table('password_reset_tokens')->where('email', (string) $user->email)->update([
                'delivery_id' => $deliveryId,
                'encrypted_delivery_token' => Crypt::encryptString($token),
            ]);
            $this->outbox->record(
                eventType: self::EVENT_TYPE,
                allianceId: null,
                aggregate: $user,
                payload: ['delivery_id' => $deliveryId],
            );
        });
    }
}
