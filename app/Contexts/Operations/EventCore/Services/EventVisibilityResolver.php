<?php

declare(strict_types=1);

namespace App\Contexts\Operations\EventCore\Services;

use App\Contexts\Alliance\Access\Services\AllianceRankPermissions;
use App\Contexts\Alliance\Core\Enums\AllianceStatus;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use Illuminate\Database\Eloquent\Builder;

final class EventVisibilityResolver
{
    public function __construct(private AllianceRankPermissions $rankPermissions) {}

    /** @return array{alliance:list<string>,player:list<string>,kingdom:list<string>} */
    public function targetIds(Player $actor): array
    {
        [$allianceIds, $managedPlayerAllianceIds] = $this->allianceTargets(
            $actor,
            OperationsPermission::EventAllianceView,
            OperationsPermission::EventPlayerManage,
        );

        $playerIds = [(string) $actor->id];
        $playerIds = [...$playerIds, ...$this->managedPlayerIds($managedPlayerAllianceIds)];

        return [
            'alliance' => $allianceIds,
            'player' => array_values(array_unique($playerIds)),
            'kingdom' => $this->kingdomIds($actor, OperationsPermission::EventKingdomView),
        ];
    }

    /** @return array{alliance:list<string>,player:list<string>,kingdom:list<string>} */
    public function manageableTargetIds(Player $actor): array
    {
        [$allianceIds, $managedPlayerAllianceIds] = $this->allianceTargets(
            $actor,
            OperationsPermission::EventAllianceManage,
            OperationsPermission::EventPlayerManage,
        );

        $playerIds = [(string) $actor->id];
        $playerIds = [...$playerIds, ...$this->managedPlayerIds($managedPlayerAllianceIds)];

        return [
            'alliance' => $allianceIds,
            'player' => array_values(array_unique($playerIds)),
            'kingdom' => $this->kingdomIds($actor, OperationsPermission::EventKingdomManage),
        ];
    }

    /** @return array{0:list<string>,1:list<string>} */
    private function allianceTargets(
        Player $actor,
        OperationsPermission $alliancePermission,
        OperationsPermission $playerPermission,
    ): array {
        $membership = AllianceMembership::query()
            ->where('player_id', $actor->id)
            ->where('status', MembershipStatus::Active->value)
            ->with(['alliance', 'roles.permissions'])
            ->first();

        if (! $membership instanceof AllianceMembership
            || $membership->alliance === null
            || $membership->alliance->status !== AllianceStatus::Active
            || (string) $membership->alliance->kingdom_id !== (string) $actor->current_kingdom_id) {
            return [[], []];
        }

        $allianceId = (string) $membership->alliance_id;

        return [
            $this->membershipAllows($membership, $alliancePermission) ? [$allianceId] : [],
            $this->membershipAllows($membership, $playerPermission) ? [$allianceId] : [],
        ];
    }

    /**
     * @param  list<string>  $allianceIds
     * @return list<string>
     */
    private function managedPlayerIds(array $allianceIds): array
    {
        if ($allianceIds === []) {
            return [];
        }

        $ids = AllianceRosterEntry::query()
            ->where('state', RosterState::Active->value)
            ->whereIn('alliance_id', $allianceIds)
            ->with(['alliance:id,kingdom_id', 'player:id,current_kingdom_id'])
            ->get()
            ->filter(static function (AllianceRosterEntry $entry): bool {
                return $entry->alliance !== null
                    && $entry->player instanceof Player
                    && (string) $entry->alliance->kingdom_id === (string) $entry->player->current_kingdom_id;
            })
            ->pluck('player_id')
            ->map(static fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();

        return array_values($ids);
    }

    /** @return list<string> */
    private function kingdomIds(Player $actor, OperationsPermission $permission): array
    {
        $ids = KingdomRoleAssignment::query()
            ->where('player_id', $actor->id)
            ->where('kingdom_id', $actor->current_kingdom_id)
            ->whereHas('role.permissions', static fn (Builder $query) => $query
                ->where('permissions.key', $permission->value))
            ->pluck('kingdom_id')
            ->map(static fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();

        return array_values($ids);
    }

    private function membershipAllows(AllianceMembership $membership, OperationsPermission $permission): bool
    {
        if ($this->rankPermissions->allows($membership->rank, $permission)) {
            return true;
        }

        foreach ($membership->roles as $role) {
            if ((string) $role->alliance_id !== (string) $membership->alliance_id) {
                continue;
            }

            if ($role->permissions->contains(static fn ($candidate): bool => (string) $candidate->key === $permission->value)) {
                return true;
            }
        }

        return false;
    }
}
