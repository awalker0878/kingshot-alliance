<?php

declare(strict_types=1);

namespace App\Domain\Alliances\Http\Controllers;

use App\Domain\Alliances\Models\Alliance;

use App\Domain\Content\Services\ContentPresenter;
use App\Domain\Content\Queries\ContentQuery;
use App\Domain\Events\Queries\AllianceEventQuery;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Content\Enums\ContentType;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Memberships\Enums\InvitationStatus;
use App\Domain\Platform\Http\Controllers\Controller;
use App\Domain\Alliances\Models\AllianceMembership;
use App\Domain\Events\Models\Event;
use App\Domain\Memberships\Models\Invitation;
use App\Domain\Authorization\Models\Role;
use App\Domain\Identity\Models\User;
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
        AllianceEventQuery $eventQuery,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $alliance = $context->alliance();
        $membership = $context->membership()->loadMissing('roles:id,alliance_id,key,name');
        $canManageInvitations = $authorization->allows($user, $alliance, PermissionKey::InvitationManage);
        $canManageMembers = $authorization->allows($user, $alliance, PermissionKey::MembershipManage);
        $canManageRoles = $authorization->allows($user, $alliance, PermissionKey::RoleManage);
        $canManageContent = $authorization->allows($user, $alliance, PermissionKey::ContentManage);
        $canManageEvents = $authorization->allows($user, $alliance, PermissionKey::EventManage);
        $canManageRecruitment = $authorization->allows($user, $alliance, PermissionKey::RecruitmentManage);

        /** @var list<array{key: string, name: string}> $roles */
        $roles = [];

        foreach ($membership->roles as $role) {
            if (! $role instanceof Role) {
                throw new LogicException('A membership role relation returned an unexpected model.');
            }

            $roles[] = [
                'key' => (string) $role->key,
                'name' => (string) $role->name,
            ];
        }

        /** @var list<array{id: string, email: string, status: string, expiresAt: string|null, createdAt: string|null}> $invitations */
        $invitations = [];

        if ($canManageInvitations) {
            foreach (Invitation::query()
                ->where('alliance_id', $alliance->id)
                ->latest('created_at')
                ->limit(50)
                ->get() as $invitation) {
                $status = $invitation->status;

                if ($status === InvitationStatus::Pending && $invitation->expires_at?->isPast()) {
                    $status = InvitationStatus::Expired;
                }

                $invitations[] = [
                    'id' => (string) $invitation->id,
                    'email' => (string) $invitation->email,
                    'status' => $status->value,
                    'expiresAt' => $invitation->expires_at?->toIso8601String(),
                    'createdAt' => $invitation->created_at?->toIso8601String(),
                ];
            }
        }

        /** @var list<array{id: string, user: array{id: int, name: string, email: string}, status: string, roles: list<array{id: string, key: string, name: string}>}> $members */
        $members = [];

        if ($canManageMembers || $canManageRoles) {
            foreach (AllianceMembership::query()
                ->where('alliance_id', $alliance->id)
                ->with([
                    'user:id,name,email',
                    'roles:id,alliance_id,key,name',
                ])
                ->orderBy('created_at')
                ->get() as $member) {
                $memberUser = $member->user;

                if (! $memberUser instanceof User) {
                    throw new LogicException('An alliance membership must reference a user.');
                }

                /** @var list<array{id: string, key: string, name: string}> $memberRoles */
                $memberRoles = [];

                foreach ($member->roles as $role) {
                    if (! $role instanceof Role) {
                        throw new LogicException('A membership role relation returned an unexpected model.');
                    }

                    $memberRoles[] = [
                        'id' => (string) $role->id,
                        'key' => (string) $role->key,
                        'name' => (string) $role->name,
                    ];
                }

                $members[] = [
                    'id' => (string) $member->id,
                    'user' => [
                        'id' => (int) $memberUser->id,
                        'name' => (string) $memberUser->name,
                        'email' => (string) $memberUser->email,
                    ],
                    'status' => $member->status->value,
                    'roles' => $memberRoles,
                ];
            }
        }

        /** @var list<array{id: string, key: string, name: string}> $roleCatalog */
        $roleCatalog = [];

        if ($canManageRoles) {
            foreach (Role::query()
                ->where('alliance_id', $alliance->id)
                ->orderBy('name')
                ->get() as $role) {
                $roleCatalog[] = [
                    'id' => (string) $role->id,
                    'key' => (string) $role->key,
                    'name' => (string) $role->name,
                ];
            }
        }

        $notices = $contentQuery
            ->memberList($alliance, null, ContentType::Announcement->value)
            ->take(5)
            ->map(fn ($item): array => $contentPresenter->item($item))
            ->values()
            ->all();

        /** @var list<array{id: string, title: string, startsAt: string, allianceTimezone: string}> $upcomingActivities */
        $upcomingActivities = [];

        foreach ($eventQuery->calendar($alliance, pastDays: 0, futureDays: 30)->take(5) as $occurrence) {
            $event = $occurrence->event;

            if (! $event instanceof Event) {
                throw new LogicException('An event occurrence must reference an event.');
            }

            $upcomingActivities[] = [
                'id' => (string) $occurrence->id,
                'title' => (string) $event->title,
                'startsAt' => $occurrence->starts_at->toIso8601String(),
                'allianceTimezone' => (string) $event->timezone,
            ];
        }

        return Inertia::render('Alliance/Overview', [
            'alliance' => [
                'id' => $alliance->id,
                'name' => $alliance->name,
                'slug' => $alliance->slug,
                'kingdom' => $alliance->kingdom,
                'language' => $alliance->language,
                'timezone' => $alliance->timezone,
                'publicUrl' => route('public.alliances.show', $alliance->slug),
            ],
            'membership' => [
                'id' => $membership->id,
                'roles' => $roles,
            ],
            'contentHub' => [
                'canManage' => $canManageContent,
                'canManageEvents' => $canManageEvents,
                'canManageRecruitment' => $canManageRecruitment,
                'notices' => $notices,
                'upcomingActivities' => $upcomingActivities,
            ],
            'invitationManagement' => [
                'allowed' => $canManageInvitations,
                'invitations' => $invitations,
                'issuedLink' => $request->session()->get('invitationLink'),
            ],
            'membershipManagement' => [
                'allowed' => $canManageMembers,
                'rolesAllowed' => $canManageRoles,
                'members' => $members,
                'roleCatalog' => $roleCatalog,
                'currentUserId' => $user->id,
            ],
        ]);
    }
}
