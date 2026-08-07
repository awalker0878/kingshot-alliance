<?php

declare(strict_types=1);

namespace App\Domain\Memberships\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\DefaultAllianceRole;
use App\Domain\Authorization\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Enums\InvitationStatus;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Memberships\Models\Invitation;
use App\Domain\Memberships\Services\InvitationTokenService;
use App\Domain\Platform\Models\OutboxMessage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final readonly class AcceptInvitation
{
    public function __construct(
        private InvitationTokenService $tokens,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $user, string $token): Alliance
    {
        return DB::transaction(function () use ($user, $token): Alliance {
            $invitation = Invitation::query()
                ->where('token_hash', $this->tokens->hash($token))
                ->where('status', InvitationStatus::Pending->value)
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->firstOrFail();

            if (! hash_equals(Str::lower((string) $invitation->email), Str::lower((string) $user->email))) {
                throw new AuthorizationException;
            }

            $alliance = $invitation->alliance;

            if (! $alliance instanceof Alliance) {
                throw new LogicException('An invitation must reference an alliance.');
            }

            $membership = AllianceMembership::query()
                ->where('alliance_id', $alliance->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($membership === null) {
                $membership = AllianceMembership::query()->create([
                    'alliance_id' => $alliance->id,
                    'user_id' => $user->id,
                    'status' => MembershipStatus::Active,
                    'joined_at' => now(),
                ]);
            } elseif ($membership->status !== MembershipStatus::Active) {
                $membership->forceFill([
                    'status' => MembershipStatus::Active,
                    'joined_at' => $membership->joined_at ?? now(),
                    'left_at' => null,
                ])->save();
            }

            $memberRole = Role::query()
                ->where('alliance_id', $alliance->id)
                ->where('key', DefaultAllianceRole::Member->value)
                ->first();

            if (! $memberRole instanceof Role) {
                throw new LogicException('The default member role was not provisioned.');
            }

            $membership->roles()->syncWithoutDetaching([
                $memberRole->id => ['alliance_id' => $alliance->id],
            ]);

            $invitation->forceFill([
                'status' => InvitationStatus::Accepted,
                'accepted_by_user_id' => $user->id,
                'accepted_at' => now(),
            ])->save();

            $this->audit->record(
                event: 'invitation.accepted',
                actor: $user,
                subject: $invitation,
                alliance: $alliance,
                metadata: ['membership_id' => $membership->id],
            );

            OutboxMessage::query()->create([
                'alliance_id' => $alliance->id,
                'event_type' => 'invitation.accepted',
                'aggregate_type' => Invitation::class,
                'aggregate_id' => $invitation->id,
                'idempotency_key' => 'invitation.accepted:'.$invitation->id,
                'payload' => [
                    'invitation_id' => $invitation->id,
                    'alliance_id' => $alliance->id,
                    'membership_id' => $membership->id,
                    'user_id' => $user->id,
                ],
                'occurred_at' => now(),
                'available_at' => now(),
                'attempts' => 0,
            ]);

            return $alliance;
        });
    }
}
