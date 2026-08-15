<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Memberships\Enums\AllianceRank;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Platform\Models\AccountDeletionRequest;
use App\Domain\Platform\Models\OutboxMessage;
use App\Domain\Platform\Models\PlatformAdministrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RequestAccountDeletion
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(User $user): AccountDeletionRequest
    {
        return DB::transaction(function () use ($user): AccountDeletionRequest {
            // User is the account/privacy subject anchor. Legal-hold placement and final
            // deletion processing lock the same row before making durable decisions.
            $currentUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if (PlatformAdministrator::query()
                ->where('user_id', $currentUser->id)
                ->whereNull('revoked_at')
                ->exists()) {
                throw ValidationException::withMessages([
                    'account' => 'Platform administrator access must be revoked before account deletion can be requested.',
                ]);
            }

            $playerIds = Player::query()
                ->where('user_id', $currentUser->id)
                ->orderBy('id')
                ->pluck('id');
            $leadsAlliance = AllianceMembership::query()
                ->whereIn('player_id', $playerIds)
                ->where('status', MembershipStatus::Active->value)
                ->where('rank', AllianceRank::R5->value)
                ->exists();
            if ($leadsAlliance) {
                throw ValidationException::withMessages([
                    'account' => 'Transfer R5 leadership or close every led alliance before requesting account deletion.',
                ]);
            }

            $requestedAt = now();
            AccountDeletionRequest::query()->upsert([[
                'user_id' => $currentUser->id,
                'status' => 'pending',
                'requested_at' => $requestedAt,
                'eligible_at' => $requestedAt->copy()->addDays(7),
                'processed_at' => null,
                'blocked_reason' => null,
                'created_at' => $requestedAt,
                'updated_at' => $requestedAt,
            ]], ['user_id'], [
                'status',
                'requested_at',
                'eligible_at',
                'processed_at',
                'blocked_reason',
                'updated_at',
            ]);

            $deletion = AccountDeletionRequest::query()
                ->where('user_id', $currentUser->id)
                ->lockForUpdate()
                ->firstOrFail();
            $currentUser->forceFill(['deletion_requested_at' => $requestedAt])->save();

            $this->audit->record('identity.account-deletion.requested', $currentUser, $currentUser, null, [
                'eligible_at' => $deletion->eligible_at->toIso8601String(),
            ]);
            OutboxMessage::query()->create([
                'alliance_id' => null,
                'event_type' => 'identity.account-deletion.requested',
                'aggregate_type' => User::class,
                'aggregate_id' => (string) $currentUser->id,
                'idempotency_key' => 'identity.account-deletion.requested:'.$currentUser->id.':'.$requestedAt->format('Uu'),
                'payload' => [
                    'user_id' => $currentUser->id,
                    'eligible_at' => $deletion->eligible_at->toIso8601String(),
                ],
                'occurred_at' => $requestedAt,
                'available_at' => $requestedAt,
                'attempts' => 0,
            ]);

            return $deletion;
        });
    }
}
