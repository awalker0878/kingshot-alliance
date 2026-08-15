<?php

declare(strict_types=1);

namespace App\Domain\Alliances\Http\Controllers;

use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Models\Role;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Content\Enums\ContentType;
use App\Domain\Content\Queries\ContentQuery;
use App\Domain\Content\Services\ContentPresenter;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Queries\EventCalendarQuery;
use App\Contexts\Accounts\Models\User;
use App\Domain\Kingdoms\Enums\RosterState;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Memberships\Enums\AllianceRank;
use App\Domain\Memberships\Enums\InvitationStatus;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Memberships\Models\Invitation;
use App\Shared\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class AllianceOverviewController extends Controller
{
    public function __invoke(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        ContentQuery $contentQuery,
        ContentPresenter $contentPresenter,
        EventCalendarQuery $eventQuery,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $actor = $context->player();
        $alliance = $context->alliance()->load('kingdom');
        $membership = $context->membership()->loadMissing('roles:id,alliance_id,key,name');
        $canManageInvitations = $authorization->allows($actor, $alliance, PermissionKey::InvitationManage);
        $canManageMembers = $authorization->allows($actor, $alliance, PermissionKey::MembershipManage);
        $canManageRoles = $authorization->allows($actor, $alliance, PermissionKey::RoleManage);
        $canManageContent = $authorization->allows($actor, $alliance, PermissionKey::ContentManage);
        $canManageEvents = $authorization->allows($actor, $alliance, PermissionKey::EventAllianceManage);
        $canManageRecruitment = $authorization->allows($actor, $alliance, PermissionKey::RecruitmentManage);
        $canManageIntegrations = $authorization->allows($actor, $alliance, PermissionKey::AllianceManage);

        $roles = $membership->roles->map(static function (Role $role): array {
            return [
                'key' => (string) $role->key,
                'name' => (string) $role->name,
            ];
        })->values()->all();

        $invitations = [];
        $invitationCandidates = [];

        if ($canManageInvitations) {
            $invitations = Invitation::query()
                ->where('alliance_id', $alliance->id)
                ->with('player:id,current_name,game_player_id')
                ->latest('created_at')
                ->limit(50)
                ->get()
                ->map(static function (Invitation $invitation): array {
                    $status = $invitation->status;
                    if ($status === InvitationStatus::Pending && $invitation->expires_at?->isPast()) {
                        $status = InvitationStatus::Expired;
                    }

                    return [
                        'id' => (string) $invitation->id,
                        'player' => [
                            'id' => (string) $invitation->player_id,
                            'name' => (string) $invitation->player->current_name,
                            'gamePlayerId' => $invitation->player->game_player_id,
                        ],
                        'email' => (string) $invitation->email,
                        'status' => $status->value,
                        'expiresAt' => $invitation->expires_at?->toIso8601String(),
                        'createdAt' => $invitation->created_at?->toIso8601String(),
                    ];
                })
                ->values()
                ->all();

            $activeMemberPlayerIds = AllianceMembership::query()
                ->where('alliance_id', $alliance->id)
                ->where('status', MembershipStatus::Active->value)
                ->pluck('player_id');

            $invitationCandidates = AllianceRosterEntry::query()
                ->where('alliance_id', $alliance->id)
                ->where('state', RosterState::Active->value)
                ->whereNotIn('player_id', $activeMemberPlayerIds)
                ->with('player:id,current_name,game_player_id,user_id')
                ->orderBy('observed_name')
                ->get()
                ->map(static fn (AllianceRosterEntry $entry): array => [
                    'id' => (string) $entry->player_id,
                    'name' => (string) $entry->player->current_name,
                    'gamePlayerId' => $entry->player->game_player_id,
                    'claimed' => $entry->player->user_id !== null,
                ])
                ->values()
                ->all();
        }

        $members = [];
        if ($canManageMembers || $canManageRoles) {
            $members = AllianceMembership::query()
                ->where('alliance_id', $alliance->id)
                ->with([
                    'player:id,current_name,game_player_id,user_id',
                    'roles:id,alliance_id,key,name',
                ])
                ->orderBy('created_at')
                ->get()
                ->map(static function (AllianceMembership $member): array {
                    $memberRoles = $member->roles->map(static fn (Role $role): array => [
                        'id' => (string) $role->id,
                        'key' => (string) $role->key,
                        'name' => (string) $role->name,
                    ])->values()->all();

                    return [
                        'id' => (string) $member->id,
                        'player' => [
                            'id' => (string) $member->player_id,
                            'name' => (string) $member->player->current_name,
                            'gamePlayerId' => $member->player->game_player_id,
                            'claimed' => $member->player->user_id !== null,
                        ],
                        'status' => $member->status->value,
                        'rank' => $member->rank->value,
                        'roles' => $memberRoles,
                    ];
                })
                ->values()
                ->all();
        }

        $roleCatalog = [];
        if ($canManageRoles) {
            $roleCatalog = Role::query()
                ->where('alliance_id', $alliance->id)
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
            ->memberList($alliance, null, ContentType::Announcement->value)
            ->take(5)
            ->map(fn ($item): array => $contentPresenter->item($item))
            ->values()
            ->all();

        $upcomingActivities = [];
        foreach ($eventQuery->forAlliance($actor, $alliance, pastDays: 0, futureDays: 30)->take(5) as $occurrence) {
            $event = $occurrence->event;
            if (! $event instanceof Event) {
                throw new LogicException('An Event occurrence must reference an Event.');
            }

            $upcomingActivities[] = [
                'id' => (string) $occurrence->id,
                'title' => (string) $event->title,
                'startsAt' => $occurrence->starts_at->toIso8601String(),
                'allianceTimezone' => (string) $event->timezone,
            ];
        }

        return Inertia::render('Alliance/Overview', [
            'user' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
            'alliance' => [
                'id' => $alliance->id,
                'name' => $alliance->name,
                'slug' => $alliance->slug,
                'kingdom' => $alliance->kingdom === null ? null : (string) $alliance->kingdom->number,
                'language' => $alliance->language,
                'timezone' => $alliance->timezone,
                'publicUrl' => route('public.alliances.show', $alliance->slug),
            ],
            'membership' => [
                'id' => $membership->id,
                'rank' => $membership->rank->value,
                'roles' => $roles,
            ],
            'contentHub' => [
                'canManage' => $canManageContent,
                'canManageEvents' => $canManageEvents,
                'canManageRecruitment' => $canManageRecruitment,
                'canManageIntegrations' => $canManageIntegrations,
                'notices' => $notices,
                'upcomingActivities' => $upcomingActivities,
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
                'members' => $members,
                'roleCatalog' => $roleCatalog,
                'currentPlayerId' => (string) $actor->id,
            ],
        ]);
    }
}
