<?php

declare(strict_types=1);

namespace App\Contexts\Platform\DataGovernance\Actions;

use App\Contexts\Accounts\Identity\Actions\RecordAccountDeletionLifecycle;
use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Platform\DataGovernance\Models\AccountDeletionRequest;
use Illuminate\Support\Facades\DB;

final readonly class CancelAccountDeletion
{
    public function __construct(
        private AccountIdentityQuery $accounts,
        private RecordAccountDeletionLifecycle $lifecycle,
    ) {}

    public function handle(int $userId): bool
    {
        return DB::transaction(function () use ($userId): bool {
            $account = $this->accounts->lockCurrent($userId);
            if ($account->anonymized) {
                return false;
            }

            $request = AccountDeletionRequest::query()
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (! $request instanceof AccountDeletionRequest || ! in_array($request->status, ['pending', 'blocked'], true)) {
                return false;
            }

            $request->forceFill([
                'status' => 'cancelled',
                'blocked_reason' => null,
            ])->save();

            $this->lifecycle->cancelled($userId, (string) $request->id);

            return true;
        });
    }
}
