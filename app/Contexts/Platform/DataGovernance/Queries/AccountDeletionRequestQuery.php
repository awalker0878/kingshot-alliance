<?php

declare(strict_types=1);

namespace App\Contexts\Platform\DataGovernance\Queries;

use App\Contexts\Platform\DataGovernance\Models\AccountDeletionRequest;

final class AccountDeletionRequestQuery
{
    /** @return array{id:string,status:string,requestedAt:string,eligibleAt:string,processedAt:?string,blockedReason:?string}|null */
    public function forUser(int $userId): ?array
    {
        $request = AccountDeletionRequest::query()->where('user_id', $userId)->first();
        if (! $request instanceof AccountDeletionRequest || $request->status === 'cancelled') {
            return null;
        }

        return [
            'id' => (string) $request->id,
            'status' => (string) $request->status,
            'requestedAt' => $request->requested_at->toIso8601String(),
            'eligibleAt' => $request->eligible_at->toIso8601String(),
            'processedAt' => $request->processed_at?->toIso8601String(),
            'blockedReason' => $request->blocked_reason,
        ];
    }
}
