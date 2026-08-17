<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Identity\Actions;

use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final readonly class AnonymizeAccount
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(int $userId, string $requestId): void
    {
        DB::transaction(function () use ($userId, $requestId): void {
            $user = User::query()->whereKey($userId)->lockForUpdate()->first();
            if (! $user instanceof User) {
                return;
            }

            if ($user->anonymized_at !== null) {
                return;
            }

            $user->tokens()->delete();
            $user->forceFill([
                'name' => 'Deleted User',
                'email' => 'deleted+'.$user->id.'@invalid.local',
                'email_verified_at' => null,
                'password' => Hash::make(Str::random(64)),
                'timezone' => 'UTC',
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'remember_token' => Str::random(60),
                'deletion_requested_at' => null,
                'anonymized_at' => now(),
            ])->save();

            $this->audit->record(
                event: 'account.anonymized',
                actor: null,
                subject: $user,
                metadata: ['deletion_request_id' => $requestId],
            );
        });
    }
}
