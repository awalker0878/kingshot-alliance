<?php

declare(strict_types=1);

namespace App\Http\Controllers\Alliance;

use App\Application\Identity\AllianceAuthorization;
use App\Application\Identity\AllianceContext;
use App\Domain\Identity\Authorization\PermissionKey;
use App\Domain\Identity\Enums\InvitationStatus;
use App\Http\Controllers\Controller;
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
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $alliance = $context->alliance();
        $membership = $context->membership()->loadMissing('roles:id,alliance_id,key,name');
        $canManageInvitations = $authorization->allows($user, $alliance, PermissionKey::InvitationManage);

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

        return Inertia::render('Alliance/Overview', [
            'alliance' => [
                'id' => $alliance->id,
                'name' => $alliance->name,
                'slug' => $alliance->slug,
                'kingdom' => $alliance->kingdom,
                'language' => $alliance->language,
                'timezone' => $alliance->timezone,
            ],
            'membership' => [
                'id' => $membership->id,
                'roles' => $roles,
            ],
            'invitationManagement' => [
                'allowed' => $canManageInvitations,
                'invitations' => $invitations,
                'issuedLink' => $request->session()->get('invitationLink'),
            ],
        ]);
    }
}
