<?php

declare(strict_types=1);

namespace App\Contexts\Platform\DataGovernance\Actions;

use App\Contexts\Accounts\Identity\Actions\RecordAccountDeletionLifecycle;
use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Platform\DataGovernance\Models\AccountDeletionRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RequestAccountDeletion
{
    public function __construct(
        private AccountIdentityQuery $accounts,
        private RecordAccountDeletionLifecycle $lifecycle,
    ) {}

    public function handle(int $userId): string
    {
        return DB::transaction(function () use ($userId): string {
            $account = $this->accounts->lockCurrent($userId);
            $request = AccountDeletionRequest::query()->where('user_id', $userId)->lockForUpdate()->first();
            if (! $request instanceof AccountDeletionRequest) {
                $request = new AccountDeletionRequest(['user_id' => $userId]);
            }

            if ($request->exists && in_array($request->status, ['pending', 'blocked', 'processed'], true)) {
                return (string) $request->id;
            }

            if ($account->anonymized) {
                throw ValidationException::withMessages(['account' => 'This account has already been deleted.']);
            }

            $request->forceFill([
                'status' => 'pending',
                'requested_at' => now(),
                'eligible_at' => now()->addDays(7),
                'next_attempt_at' => null,
                'processed_at' => null,
                'blocked_reason' => null,
            ])->save();

            $this->lifecycle->requested($userId, (string) $request->id);

            return (string) $request->id;
        });
    }
}
