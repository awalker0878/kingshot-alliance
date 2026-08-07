<?php

declare(strict_types=1);

namespace App\Http\Controllers\Alliance;

use App\Application\Content\ContentPresenter;
use App\Application\Content\ContentQuery;
use App\Application\Identity\AllianceAuthorization;
use App\Application\Identity\AllianceContext;
use App\Domain\Content\Enums\ContentType;
use App\Domain\Identity\Authorization\PermissionKey;
use App\Domain\Identity\Enums\InvitationStatus;
use App\Http\Controllers\Controller;
use App\Models\AllianceMembership;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\User;
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
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $alliance = $context->alliance();
        $membership = $context->membership()->loadMissing('roles:id,alliance_id,key,name');
        $canManageInvitations = $authorization->allows($user, $alliance, PermissionKey::InvitationManage);
        $canManageMembers = $authorization->allows($user, $alliance, PermissionKey::MembershipManage);
        $canManageRoles = $authorization->allows($user, $alliance, PermissionKey::RoleManage);
        $canManageContent = $authorization->allows($user, $alliance, PermissionKey::ContentManage);

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
                'notices' => $notices,
                'upcomingActivities' => [],
                'upcomingActivitiesPhase' => 3,
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
