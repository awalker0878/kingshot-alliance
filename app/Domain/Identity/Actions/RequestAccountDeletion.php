<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\DefaultAllianceRole;
use App\Domain\Identity\Models\User;
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
        if (PlatformAdministrator::activeFor($user)) {
            throw ValidationException::withMessages([
                'account' => 'Platform administrator access must be revoked before account deletion can be requested.',
            ]);
        }

        $ownsAlliance = AllianceMembership::query()
            ->where('user_id', $user->id)
            ->where('status', MembershipStatus::Active->value)
            ->whereHas('roles', static fn ($query) => $query->where('roles.key', DefaultAllianceRole::Owner->value))
            ->exists();

        if ($ownsAlliance) {
            throw ValidationException::withMessages([
                'account' => 'Transfer or close every owned alliance before requesting account deletion.',
            ]);
        }

        return DB::transaction(function () use ($user): AccountDeletionRequest {
            $requestedAt = now();
            $deletion = AccountDeletionRequest::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'status' => 'pending',
                    'requested_at' => $requestedAt,
                    'eligible_at' => $requestedAt->copy()->addDays(7),
                    'processed_at' => null,
                    'blocked_reason' => null,
                ],
            );

            $user->forceFill(['deletion_requested_at' => $requestedAt])->save();
            $this->audit->record('identity.account-deletion.requested', $user, $user, null, [
                'eligible_at' => $deletion->eligible_at->toIso8601String(),
            ]);

            OutboxMessage::query()->create([
                'alliance_id' => null,
                'event_type' => 'identity.account-deletion.requested',
                'aggregate_type' => User::class,
                'aggregate_id' => (string) $user->id,
                'idempotency_key' => 'identity.account-deletion.requested:'.$user->id.':'.$requestedAt->format('Uu'),
                'payload' => [
                    'user_id' => $user->id,
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
