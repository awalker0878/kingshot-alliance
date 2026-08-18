<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Queries;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Access\Queries\AlliancePermissionQuery;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;

final readonly class PlayerIdentityContextQuery
{
    public function __construct(private AlliancePermissionQuery $permissions) {}

    /**
     * Return presentation-safe Alliance context for Player identities.
     *
     * This is a read-side projection only. Consumers may use it to describe the
     * active Governor and shape navigation, but writes must still be authorized
     * by the owning context from the server-resolved active Player reference.
     *
     * @param  list<string>  $playerIds
     * @return array<string, array{
     *     membershipId:string,
     *     allianceId:string,
     *     allianceName:string,
     *     rank:string,
     *     roles:list<array{key:string,name:string}>,
     *     capabilities:list<string>
     * }>
     */
    public function forPlayers(array $playerIds): array
    {
        if ($playerIds === []) {
            return [];
        }

        $memberships = AllianceMembership::query()
            ->with(['alliance', 'roles'])
            ->whereIn('player_id', $playerIds)
            ->where('status', MembershipStatus::Active->value)
            ->orderByDesc('joined_at')
            ->get();

        $result = [];

        foreach ($memberships as $membership) {
            $playerId = (string) $membership->player_id;
            if (isset($result[$playerId])) {
                continue;
            }

            $alliance = $membership->alliance;
            if (! $alliance instanceof Alliance) {
                continue;
            }

            $roles = $membership->roles
                ->map(static fn (Role $role): array => [
                    'key' => (string) $role->key,
                    'name' => (string) $role->name,
                ])
                ->sortBy('name')
                ->values()
                ->all();

            $capabilities = [];
            foreach (AlliancePermission::cases() as $permission) {
                if ($this->permissions->allows($playerId, (string) $alliance->id, $permission)) {
                    $capabilities[] = $permission->value;
                }
            }
            sort($capabilities);

            $result[$playerId] = [
                'membershipId' => (string) $membership->getKey(),
                'allianceId' => (string) $alliance->id,
                'allianceName' => (string) $alliance->name,
                'rank' => $membership->rank->value,
                'roles' => $roles,
                'capabilities' => $capabilities,
            ];
        }

        return $result;
    }
}
