<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceDashboard\Http\Controllers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Queries\ContentQuery;
use App\Contexts\Alliance\Content\Services\ContentPresenter;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\InvitationStatus;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\Alliance\Membership\Models\Invitation;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\ReadModels\AllianceDashboard\AllianceDashboardCapabilitiesQuery;
use App\ReadModels\AllianceDashboard\Queries\MembershipManagementQuery;
use App\ReadModels\AllianceDashboard\UpcomingAllianceActivitiesQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AllianceOverviewController extends Controller
{
    public function __invoke(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        AllianceIntelligenceAuthorization $intelligenceAuthorization,
        AllianceReferenceQuery $alliances,
        KingdomReferenceQuery $kingdoms,
        PlayerReferenceQuery $players,
        ContentQuery $contentQuery,
        ContentPresenter $contentPresenter,
        AllianceDashboardCapabilitiesQuery $capabilitiesQuery,
        MembershipManagementQuery $membershipManagementQuery,
        UpcomingAllianceActivitiesQuery $upcomingActivitiesQuery,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $scope = $context->scope();
        $validated = $request->validate([
            'member_cursor' => ['nullable', 'string', 'max:4096'],
        ]);
        $alliance = $alliances->require($scope->allianceId);
        $kingdom = $kingdoms->require($alliance->kingdomId);
        $membership = AllianceMembership::query()
            ->whereKey($scope->membershipId)
            ->where('alliance_id', $scope->allianceId)
            ->where('player_id', $scope->playerId)
            ->where('status', MembershipStatus::Active->value)
            ->with('roles:id,alliance_id,key,name')
            ->firstOrFail();

        $canManageInvitations = $authorization->allows($scope->playerId, $scope->allianceId, AlliancePermission::InvitationManage);
        $canManageMembers = $authorization->allows($scope->playerId, $scope->allianceId, AlliancePermission::MembershipManage);
        $canManageRoles = $authorization->allows($scope->playerId, $scope->allianceId, AlliancePermission::RoleManage);
        $canManageContent = $authorization->allows($scope->playerId, $scope->allianceId, AlliancePermission::ContentManage);
        $canManageRecruitment = $authorization->allows($scope->playerId, $scope->allianceId, AlliancePermission::RecruitmentManage);
        $canManageIntegrations = $authorization->allows($scope->playerId, $scope->allianceId, AlliancePermission::Manage);
        $canViewGovernance = $canManageMembers || $canManageRoles || $canManageIntegrations;
        $canManageRosterEvidence = $intelligenceAuthorization->allows(
            $scope->playerId,
            $scope->allianceId,
            IntelligencePermission::KingdomManage,
        );
        $dashboardCapabilities = $capabilitiesQuery->for($scope->playerId, $scope->allianceId);

        $roles = $membership->roles
            ->map(static fn (Role $role): array => [
                'key' => (string) $role->key,
                'name' => (string) $role->name,
            ])
            ->values()
            ->all();

        $invitations = [];
        $invitationCandidates = [];
        if ($canManageInvitations) {
            $invitationRows = Invitation::query()
                ->where('alliance_id', $scope->allianceId)
                ->latest('created_at')
                ->limit(50)
                ->get();
            $activeMemberPlayerIds = AllianceMembership::query()
                ->where('alliance_id', $scope->allianceId)
                ->where('status', MembershipStatus::Active->value)
                ->pluck('player_id');
            $candidateRows = AllianceRosterEntry::query()
                ->where('alliance_id', $scope->allianceId)
                ->where('state', RosterState::Active->value)
                ->whereNotIn('player_id', $activeMemberPlayerIds)
                ->orderBy('observed_name')
                ->get();

            $playerReferences = $players->byIds([
                ...$invitationRows->pluck('player_id')->map(static fn ($id): string => (string) $id)->all(),
                ...$candidateRows->pluck('player_id')->map(static fn ($id): string => (string) $id)->all(),
            ]);

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

            $invitationCandidates = $candidateRows->map(static function (AllianceRosterEntry $entry) use ($playerReferences): array {
                $player = $playerReferences[(string) $entry->player_id] ?? null;

                return [
                    'id' => (string) $entry->player_id,
                    'name' => $player->currentName ?? (string) $entry->observed_name,
                    'gamePlayerId' => $player?->gamePlayerId,
                    'claimed' => $player?->claimed() ?? false,
                ];
            })->values()->all();
        }

        $memberPage = [
            'items' => [],
            'nextCursor' => null,
            'hasMore' => false,
            'pageSize' => MembershipManagementQuery::PAGE_SIZE,
            'isFirstPage' => true,
        ];
        $memberTotal = 0;
        if ($canManageMembers || $canManageRoles) {
            $memberManagement = $membershipManagementQuery->forAlliance(
                $scope->allianceId,
                isset($validated['member_cursor']) ? (string) $validated['member_cursor'] : null,
            );
            $memberPage = $memberManagement['page'];
            $memberTotal = $memberManagement['total'];
        }

        $roleCatalog = [];
        if ($canManageRoles) {
            $roleCatalog = Role::query()
                ->where('alliance_id', $scope->allianceId)
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

        $notices = $contentQuery
            ->memberList($scope->allianceId, null, ContentType::Announcement->value)
            ->take(5)
            ->map(fn ($item): array => $contentPresenter->item($item))
            ->values()
            ->all();

        return Inertia::render('Alliance/Overview', [
            'user' => ['name' => (string) $user->name, 'email' => (string) $user->email],
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
                'upcomingActivities' => $upcomingActivitiesQuery->handle($scope->allianceId),
            ],
            'governance' => [
                'canManageSettings' => $canManageIntegrations,
                'canManageRoles' => $canManageRoles,
                'canManageMembers' => $canManageMembers,
                'canViewHistory' => $canViewGovernance,
                'canManageRosterEvidence' => $canManageRosterEvidence,
            ],
            'invitationManagement' => [
                'allowed' => $canManageInvitations,
                'candidates' => $invitationCandidates,
                'invitations' => $invitations,
                'issuedLink' => $request->session()->get('invitationLink'),
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
                'memberPage' => $memberPage,
                'total' => $memberTotal,
                'roleCatalog' => $roleCatalog,
                'currentPlayerId' => $scope->playerId,
            ],
            'membershipBulkPreview' => $request->session()->get('membershipBulkPreview'),
            'membershipBulkResult' => $request->session()->get('membershipBulkResult'),
        ]);
    }
}
