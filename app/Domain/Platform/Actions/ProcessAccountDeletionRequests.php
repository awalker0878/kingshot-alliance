<?php

declare(strict_types=1);

namespace App\Domain\Platform\Actions;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Memberships\Actions\LeaveAlliance;
use App\Domain\Memberships\Enums\AllianceRank;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Platform\Models\AccountDeletionRequest;
use App\Domain\Platform\Models\OutboxMessage;
use App\Domain\Platform\Models\PlatformAdministrator;
use App\Domain\Platform\Services\LegalHoldService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final readonly class ProcessAccountDeletionRequests
{
    public function __construct(
        private LegalHoldService $legalHolds,
        private LeaveAlliance $leaveAlliance,
        private AuditRecorder $audit,
    ) {}

    public function handle(int $limit = 100): int
    {
        $processed = 0;
        $requests = AccountDeletionRequest::query()
            ->whereIn('status', ['pending', 'blocked'])
            ->where('eligible_at', '<=', now())
            ->orderBy('eligible_at')
            ->limit(max(1, min(500, $limit)))
            ->get();

        foreach ($requests as $request) {
            if ($this->process($request)) {
                $processed++;
            }
        }

        return $processed;
    }

    private function process(AccountDeletionRequest $request): bool
    {
        $user = User::query()->find($request->user_id);
        if (! $user instanceof User) {
            $request->forceFill(['status' => 'processed', 'processed_at' => now(), 'blocked_reason' => null])->save();

            return true;
        }

        $blockedReason = $this->blockReason($user);
        if ($blockedReason !== null) {
            $request->forceFill(['status' => 'blocked', 'blocked_reason' => $blockedReason])->save();

            return false;
        }

        DB::transaction(function () use ($request, $user): void {
            $playerIds = Player::query()->where('user_id', $user->id)->pluck('id');

            $activeMemberships = AllianceMembership::query()
                ->whereIn('player_id', $playerIds)
                ->where('status', MembershipStatus::Active->value)
                ->with(['alliance', 'player'])
                ->lockForUpdate()
                ->get();

            foreach ($activeMemberships as $membership) {
                $this->leaveAlliance->handle($membership->alliance, $membership->player);
            }

            Player::query()->whereIn('id', $playerIds)->update(['user_id' => null, 'updated_at' => now()]);

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

            $request->forceFill([
                'status' => 'processed',
                'processed_at' => now(),
                'blocked_reason' => null,
            ])->save();

            $this->audit->record('identity.account-deletion.processed', $user, $user, null, [
                'request_id' => $request->id,
            ]);
            OutboxMessage::query()->create([
                'alliance_id' => null,
                'event_type' => 'identity.account-deletion.processed',
                'aggregate_type' => User::class,
                'aggregate_id' => (string) $user->id,
                'idempotency_key' => 'identity.account-deletion.processed:'.$request->id,
                'payload' => ['user_id' => $user->id, 'request_id' => $request->id],
                'occurred_at' => now(),
                'available_at' => now(),
                'attempts' => 0,
            ]);
        });

        return true;
    }

    private function blockReason(User $user): ?string
    {
        if ($this->legalHolds->active('user', (string) $user->id)) {
            return 'An active legal hold prevents account deletion.';
        }
        if (PlatformAdministrator::activeFor($user)) {
            return 'Platform administrator access is still active.';
        }

        $leadsAlliance = AllianceMembership::query()
            ->whereIn('player_id', Player::query()->where('user_id', $user->id)->select('id'))
            ->where('status', MembershipStatus::Active->value)
            ->where('rank', AllianceRank::R5->value)
            ->exists();

        return $leadsAlliance ? 'R5 leadership must be transferred before deleting the account.' : null;
    }
}
