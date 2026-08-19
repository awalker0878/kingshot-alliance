<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceDashboard;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Queries\ContentQuery;
use App\Contexts\Alliance\Content\Services\ContentPresenter;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\InvitationStatus;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\Alliance\Membership\Models\Invitation;
use App\Contexts\Alliance\Membership\ValueObjects\AllianceScopeReference;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;

/**
 * Cross-context presentation read model for the Alliance command overview.
 *
 * The query composes scalar/read-only projections only. It is not an authority
 * object and must never be reused by writes; owning contexts re-authorize writes.
 */
final readonly class CommandOverviewQuery
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AllianceReferenceQuery $alliances,
        private KingdomReferenceQuery $kingdoms,
        private PlayerReferenceQuery $players,
        private ContentQuery $contentQuery,
        private ContentPresenter $contentPresenter,
        private AllianceDashboardCapabilitiesQuery $capabilities,
        private UpcomingAllianceActivitiesQuery $upcomingActivities,
    ) {}

    /** @return array<string, mixed> */
    public function forScope(AllianceScopeReference $scope): array
    {
        $alliance = $this->alliances->require($scope->allianceId);
        $kingdom = $this->kingdoms->require($alliance->kingdomId);
        $membership = AllianceMembership::query()
            ->whereKey($scope->membershipId)
            ->where('alliance_id', $scope->allianceId)
            ->where('player_id', $scope->playerId)
            ->where('status', MembershipStatus::Active->value)
            ->with('roles:id,alliance_id,key,name')
            ->firstOrFail();

        $canManageInvitations = $this->authorization->allows(
            $scope->playerId,
            $scope->allianceId,
            AlliancePermission::InvitationManage,
        );
        $canManageMembers = $this->authorization->allows(
            $scope->playerId,
            $scope->allianceId,
            AlliancePermission::MembershipManage,
        );
        $canManageRoles = $this->authorization->allows(
            $scope->playerId,
            $scope->allianceId,
            AlliancePermission::RoleManage,
        );
        $canManageContent = $this->authorization->allows(
            $scope->playerId,
            $scope->allianceId,
            AlliancePermission::ContentManage,
        );
        $canManageRecruitment = $this->authorization->allows(
            $scope->playerId,
            $scope->allianceId,
            AlliancePermission::RecruitmentManage,
        );
        $canManageIntegrations = $this->authorization->allows(
            $scope->playerId,
            $scope->allianceId,
            AlliancePermission::Manage,
        );
        $dashboardCapabilities = $this->capabilities->for($scope->playerId, $scope->allianceId);

        $roles = $membership->roles
            ->map(static fn (Role $role): array => [
                'key' => (string) $role->key,
                'name' => (string) $role->name,
            ])
            ->values()
            ->all();

        [$invitationCandidates, $invitations] = $canManageInvitations
            ? $this->invitationManagement($scope->allianceId)
            : [[], []];

        [$members, $roleCatalog] = $this->membershipManagement(
            $scope->allianceId,
            $canManageMembers,
            $canManageRoles,
        );

        $notices = $this->contentQuery
            ->memberList($scope->allianceId, null, ContentType::Announcement->value)
            ->take(5)
            ->map(fn ($item): array => $this->contentPresenter->item($item))
            ->values()
            ->all();

        return [
            'alliance' => [
                'id' => $alliance->allianceId,
                'name' => $alliance->name,
                'slug' => $alliance->slug,
                'kingdom' => (string) $kingdom->number,
                'language' => $alliance->language,
                'timezone' => $alliance->timezone,
                'publicUrl' => route('public.alliances.show', $alliance->slug),
            ],
            'membership' => [
                'id' => (string) $membership->id,
                'rank' => $membership->rank->value,
                'roles' => $roles,
            ],
            'contentHub' => [
                'canManage' => $canManageContent,
                'canManageEvents' => $dashboardCapabilities['canManageEvents'],
                'canManageRecruitment' => $canManageRecruitment,
                'canManageIntegrations' => $canManageIntegrations,
                'notices' => $notices,
                'upcomingActivities' => $this->upcomingActivities->handle($scope->allianceId),
            ],
            'invitationManagement' => [
                'allowed' => $canManageInvitations,
                'candidates' => $invitationCandidates,
                'invitations' => $invitations,
            ],
            'membershipManagement' => [
                'allowed' => $canManageMembers,
                'rolesAllowed' => $canManageRoles,
                'rankAllowed' => $canManageRoles,
                'leadershipTransferAllowed' => $membership->rank === AllianceRank::R5,
                'rankOptions' => array_map(
                    static fn (AllianceRank $rank): string => $rank->value,
                    [AllianceRank::R1, AllianceRank::R2, AllianceRank::R3, AllianceRank::R4],
                ),
                'members' => $members,
                'roleCatalog' => $roleCatalog,
                'currentPlayerId' => $scope->playerId,
            ],
        ];
    }

    /** @return array{0:list<array<string,mixed>>,1:list<array<string,mixed>>} */
    private function invitationManagement(string $allianceId): array
    {
        $invitationRows = Invitation::query()
            ->where('alliance_id', $allianceId)
            ->latest('created_at')
            ->limit(50)
            ->get();
        $activeMemberPlayerIds = AllianceMembership::query()
            ->where('alliance_id', $allianceId)
            ->where('status', MembershipStatus::Active->value)
            ->pluck('player_id');
        $candidateRows = AllianceRosterEntry::query()
            ->where('alliance_id', $allianceId)
            ->where('state', RosterState::Active->value)
            ->whereNotIn('player_id', $activeMemberPlayerIds)
            ->orderBy('observed_name')
            ->get();

        $playerIds = array_values(array_unique([
            ...$invitationRows->pluck('player_id')->map(static fn ($id): string => (string) $id)->all(),
            ...$candidateRows->pluck('player_id')->map(static fn ($id): string => (string) $id)->all(),
        ]));
        $playerReferences = $this->players->byIds($playerIds);

        $invitations = $invitationRows->map(static function (Invitation $invitation) use ($playerReferences): array {
            $status = $invitation->status;
            if ($status === InvitationStatus::Pending && $invitation->expires_at?->isPast()) {
                $status = InvitationStatus::Expired;
            }
            $player = $playerReferences[(string) $invitation->player_id] ?? null;

            return [
                'id' => (string) $invitation->id,
                'player' => [
                    'id' => (string) $invitation->player_id,
                    'name' => $player->currentName ?? 'Unknown player',
                    'gamePlayerId' => $player?->gamePlayerId,
                ],
                'email' => (string) $invitation->email,
                'status' => $status->value,
                'expiresAt' => $invitation->expires_at?->toIso8601String(),
                'createdAt' => $invitation->created_at?->toIso8601String(),
            ];
        })->values()->all();

        $candidates = $candidateRows->map(static function (AllianceRosterEntry $entry) use ($playerReferences): array {
            $player = $playerReferences[(string) $entry->player_id] ?? null;

            return [
                'id' => (string) $entry->player_id,
                'name' => $player->currentName ?? (string) $entry->observed_name,
                'gamePlayerId' => $player?->gamePlayerId,
                'claimed' => $player?->claimed() ?? false,
            ];
        })->values()->all();

        return [array_values($candidates), array_values($invitations)];
    }

    /** @return array{0:list<array<string,mixed>>,1:list<array<string,mixed>>} */
    private function membershipManagement(
        string $allianceId,
        bool $canManageMembers,
        bool $canManageRoles,
    ): array {
        $members = [];
        if ($canManageMembers || $canManageRoles) {
            $memberRows = AllianceMembership::query()
                ->where('alliance_id', $allianceId)
                ->with('roles:id,alliance_id,key,name')
                ->orderBy('created_at')
                ->get();
            $playerIds = array_values(
                $memberRows->pluck('player_id')->map(static fn ($id): string => (string) $id)->all(),
            );
            $playerReferences = $this->players->byIds($playerIds);

            $members = $memberRows->map(static function (AllianceMembership $member) use ($playerReferences): array {
                $player = $playerReferences[(string) $member->player_id] ?? null;
                $memberRoles = $member->roles
                    ->map(static fn (Role $role): array => [
                        'id' => (string) $role->id,
                        'key' => (string) $role->key,
                        'name' => (string) $role->name,
                    ])
                    ->values()
                    ->all();

                return [
                    'id' => (string) $member->id,
                    'player' => [
                        'id' => (string) $member->player_id,
                        'name' => $player->currentName ?? 'Unknown player',
                        'gamePlayerId' => $player?->gamePlayerId,
                        'claimed' => $player?->claimed() ?? false,
                    ],
                    'status' => $member->status->value,
                    'rank' => $member->rank->value,
                    'roles' => $memberRoles,
                ];
            })->values()->all();
        }

        $roleCatalog = [];
        if ($canManageRoles) {
            $roleCatalog = Role::query()
                ->where('alliance_id', $allianceId)
                ->orderBy('name')
                ->get()
                ->map(static fn (Role $role): array => [
                    'id' => (string) $role->id,
                    'key' => (string) $role->key,
                    'name' => (string) $role->name,
                ])
                ->values()
                ->all();
        }

        return [array_values($members), array_values($roleCatalog)];
    }
}
