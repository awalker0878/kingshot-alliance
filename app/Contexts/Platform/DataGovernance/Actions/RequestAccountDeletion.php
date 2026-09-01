<?php

declare(strict_types=1);

namespace App\Contexts\Platform\DataGovernance\Actions;

use App\Contexts\Accounts\Identity\Actions\RecordAccountDeletionLifecycle;
use App\Contexts\Platform\DataGovernance\Models\AccountDeletionRequest;
use Illuminate\Support\Facades\DB;

final readonly class RequestAccountDeletion
{
    public function __construct(private RecordAccountDeletionLifecycle $lifecycle) {}

    public function handle(int $userId): string
    {
        $requestId = DB::transaction(function () use ($userId): string {
            $request = AccountDeletionRequest::query()->where('user_id', $userId)->lockForUpdate()->first();
            if (! $request instanceof AccountDeletionRequest) {
                $request = new AccountDeletionRequest(['user_id' => $userId]);
            }

            if ($request->status === 'processed') {
                return (string) $request->id;
            }

            $request->forceFill([
                'status' => 'pending',
                'requested_at' => now(),
                'eligible_at' => now()->addDays(7),
                'processed_at' => null,
                'blocked_reason' => null,
            ])->save();

            return (string) $request->id;
        });

        $this->lifecycle->requested($userId, $requestId);

        return $requestId;
    }
}
