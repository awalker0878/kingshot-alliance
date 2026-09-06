<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Actions;

use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class ExpireKingdomRoleAssignments
{
    public function __construct(private AuditRecorder $audit, private OutboxRecorder $outbox) {}

    public function handle(int $limit = 100): int
    {
        $limit = max(1, min(500, $limit));
        $ids = KingdomRoleAssignment::query()->whereNull('revoked_at')->whereNotNull('expires_at')->where('expires_at', '<=', now())->orderBy('expires_at')->limit($limit)->pluck('id')->map('strval')->all();
        $expired = 0;
        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$expired): void {
                $assignment = KingdomRoleAssignment::query()->whereKey($id)->whereNull('revoked_at')->with('role')->lockForUpdate()->first();
                if (! $assignment instanceof KingdomRoleAssignment || $assignment->expires_at === null || $assignment->expires_at->isFuture()) {
                    return;
                }
                $expiredAt = $assignment->expires_at;
                $assignment->forceFill(['revoked_at' => $expiredAt, 'revocation_reason' => 'Delegation expired'])->save();
                $metadata = ['kingdom_id' => (string) $assignment->kingdom_id, 'target_player_id' => (string) $assignment->player_id, 'role_key' => (string) $assignment->role->key, 'expired_at' => $expiredAt->toIso8601String()];
                $this->audit->record('kingdom.role_expired', null, $assignment, null, $metadata);
                $this->outbox->record('kingdom.role_expired', null, $assignment, $metadata);
                $expired++;
            });
        }

        return $expired;
    }
}
