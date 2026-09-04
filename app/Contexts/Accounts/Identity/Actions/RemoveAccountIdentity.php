<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Identity\Actions;

use App\Contexts\Accounts\Authentication\Services\AccountSignInMethodPolicy;
use App\Contexts\Accounts\Identity\Models\AccountIdentity;
use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class RemoveAccountIdentity
{
    public function __construct(
        private AccountSignInMethodPolicy $methods,
        private AuditRecorder $audit,
    ) {}

    public function handle(int $userId, string $provider): void
    {
        $provider = Str::lower(trim($provider));

        DB::transaction(function () use ($userId, $provider): void {
            $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();

            if ($provider === 'google' && ! $this->methods->canDisconnectGoogle($user)) {
                throw ValidationException::withMessages([
                    'google' => 'Add another sign-in method before disconnecting Google.',
                ]);
            }

            $identity = AccountIdentity::query()
                ->where('user_id', $userId)
                ->where('provider', $provider)
                ->lockForUpdate()
                ->firstOrFail();

            $identity->delete();

            $this->audit->record(
                event: 'account.'.$provider.'.disconnected',
                actor: $user,
                subject: $user,
                metadata: ['provider' => $provider],
            );
        });
    }
}
