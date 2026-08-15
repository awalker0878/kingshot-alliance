<?php

declare(strict_types=1);

namespace App\Domain\Platform\Actions;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Actions\LeaveAlliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Platform\Models\AccountDeletionRequest;
use App\Contexts\Platform\Access\Models\PlatformAdministrator;
use App\Domain\Platform\Services\LegalHoldService;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

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
            ->orderBy('id')
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
        return DB::transaction(function () use ($request): bool {
            $lockedRequest = AccountDeletionRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedRequest instanceof AccountDeletionRequest
                || ! in_array($lockedRequest->status, ['pending', 'blocked'], true)
                || $lockedRequest->eligible_at->isFuture()) {
                return false;
            }

            // User is the account-deletion and legal-hold subject anchor. Legal hold
            // placement locks this same row before creating an active hold.
            $user = User::query()
                ->whereKey($lockedRequest->user_id)
                ->lockForUpdate()
                ->first();

            if (! $user instanceof User) {
                $lockedRequest->forceFill([
                    'status' => 'processed',
                    'processed_at' => now(),
                    'blocked_reason' => null,
                ])->save();

                return true;
            }

            $blockedReason = $this->blockReason($user);
            if ($blockedReason !== null) {
                $lockedRequest->forceFill([
                    'status' => 'blocked',
                    'blocked_reason' => $blockedReason,
                ])->save();

                return false;
            }

            // Route without locks, then acquire the cross-domain order used by
            // Memberships: Alliance(s) -> Player(s) -> membership rows.
            $playerIds = Player::query()
                ->where('user_id', $user->id)
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn ($id): string => (string) $id)
                ->all();

            $routedAllianceIds = AllianceMembership::query()
                ->whereIn('player_id', $playerIds)
                ->where('status', MembershipStatus::Active->value)
                ->orderBy('alliance_id')
                ->pluck('alliance_id')
                ->map(static fn ($id): string => (string) $id)
                ->unique()
                ->values()
                ->all();

            $alliances = Alliance::query()
                ->whereIn('id', $routedAllianceIds)
                ->orderBy('id')
                ->sharedLock()
                ->get()
                ->keyBy(fn (Alliance $alliance): string => (string) $alliance->id);

            $players = Player::query()
                ->whereIn('id', $playerIds)
                ->where('user_id', $user->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Player $player): string => (string) $player->id);

            $activeMemberships = AllianceMembership::query()
                ->whereIn('player_id', $players->keys())
                ->where('status', MembershipStatus::Active->value)
                ->orderBy('alliance_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            // A membership may have been activated after routing but before the Player
            // locks. Do not acquire a new Alliance after Player; abort and retry instead.
            foreach ($activeMemberships as $membership) {
                if (! $alliances->has((string) $membership->alliance_id)) {
                    throw new RuntimeException('Account deletion membership set changed during processing; retry the request.');
                }
                if ($membership->rank === AllianceRank::R5) {
                    $lockedRequest->forceFill([
                        'status' => 'blocked',
                        'blocked_reason' => 'R5 leadership must be transferred before deleting the account.',
                    ])->save();

                    return false;
                }
            }

            foreach ($activeMemberships as $membership) {
                /** @var Alliance $alliance */
                $alliance = $alliances->get((string) $membership->alliance_id);
                /** @var Player $player */
                $player = $players->get((string) $membership->player_id);
                $this->leaveAlliance->handle($alliance, $player);
            }

            Player::query()
                ->whereIn('id', $players->keys())
                ->where('user_id', $user->id)
                ->update(['user_id' => null, 'updated_at' => now()]);

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

            $lockedRequest->forceFill([
                'status' => 'processed',
                'processed_at' => now(),
                'blocked_reason' => null,
            ])->save();

            // This is a system retention/privacy workflow. The deleted User is the
            // subject, not the actor who performed the anonymization.
            $this->audit->record('platform.account-deletion.processed', null, $user, null, [
                'request_id' => $lockedRequest->id,
            ]);
            OutboxMessage::query()->create([
                'alliance_id' => null,
                'event_type' => 'platform.account-deletion.processed',
                'aggregate_type' => User::class,
                'aggregate_id' => (string) $user->id,
                'idempotency_key' => 'platform.account-deletion.processed:'.$lockedRequest->id,
                'payload' => [
                    'user_id' => $user->id,
                    'request_id' => $lockedRequest->id,
                    'origin' => 'system',
                ],
                'occurred_at' => now(),
                'available_at' => now(),
                'attempts' => 0,
            ]);

            return true;
        });
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
