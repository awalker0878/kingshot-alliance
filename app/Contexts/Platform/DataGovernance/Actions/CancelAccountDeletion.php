<?php

declare(strict_types=1);

namespace App\Contexts\Platform\DataGovernance\Actions;

use App\Contexts\Accounts\Identity\Actions\RecordAccountDeletionLifecycle;
use App\Contexts\Platform\DataGovernance\Models\AccountDeletionRequest;
use Illuminate\Support\Facades\DB;

final readonly class CancelAccountDeletion
{
    public function __construct(private RecordAccountDeletionLifecycle $lifecycle) {}

    public function handle(int $userId): bool
    {
        $requestId = DB::transaction(function () use ($userId): ?string {
            $request = AccountDeletionRequest::query()
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (! $request instanceof AccountDeletionRequest || ! in_array($request->status, ['pending', 'blocked'], true)) {
                return null;
            }

            $request->forceFill([
                'status' => 'cancelled',
                'blocked_reason' => null,
            ])->save();

            return (string) $request->id;
        });

        if ($requestId === null) {
            return false;
        }

        $this->lifecycle->cancelled($userId, $requestId);

        return true;
    }
}
