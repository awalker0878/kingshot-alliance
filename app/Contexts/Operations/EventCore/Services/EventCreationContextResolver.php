<?php

declare(strict_types=1);

namespace App\Contexts\Operations\EventCore\Services;

use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use Illuminate\Database\Eloquent\Builder;

final class EventCreationContextResolver
{
    public function __construct(private EventAuthorization $eventAuthorization) {}

    /**
     * @return list<array{
     *   scope: string,
     *   targetId: string,
     *   label: string,
     *   allianceId?: string,
     *   kingdomId?: string,
     *   kingdomNumber?: int
     * }>
     */
    public function forPlayer(Player $actor): array
    {
        $contexts = [];

        if ($this->eventAuthorization->allows(
            $actor,
            EventScope::Player,
            $actor,
            OperationsPermission::EventPlayerCreate,
        )) {
            $kingdom = $actor->currentKingdom;
            if ($kingdom instanceof Kingdom) {
                $contexts[] = [
                    'scope' => EventScope::Player->value,
                    'targetId' => (string) $actor->id,
                    'label' => (string) $actor->current_name,
                    'kingdomId' => (string) $actor->current_kingdom_id,
                    'kingdomNumber' => (int) $kingdom->number,
                ];
            }
        }

        $membership = AllianceMembership::query()
            ->where('player_id', $actor->id)
            ->where('status', MembershipStatus::Active->value)
            ->with('alliance')
            ->first();

        $alliance = $membership?->alliance;
        if ($alliance !== null && $this->eventAuthorization->allows(
            $actor,
            EventScope::Alliance,
            $alliance,
            OperationsPermission::EventAllianceCreate,
        )) {
            $context = [
                'scope' => EventScope::Alliance->value,
                'targetId' => (string) $alliance->id,
                'label' => (string) $alliance->name,
                'allianceId' => (string) $alliance->id,
            ];
            if ($alliance->kingdom_id !== null) {
                $context['kingdomId'] = (string) $alliance->kingdom_id;
            }
            $contexts[] = $context;
        }

        $kingdomAssignments = KingdomRoleAssignment::query()
            ->where('player_id', $actor->id)
            ->where('kingdom_id', $actor->current_kingdom_id)
            ->whereHas('role.permissions', static function (Builder $query): void {
                $query->where('permissions.key', OperationsPermission::EventKingdomCreate->value);
            })
            ->with('kingdom')
            ->get();

        /** @var array<string, true> $seenKingdoms */
        $seenKingdoms = [];
        foreach ($kingdomAssignments as $assignment) {
            $kingdom = $assignment->kingdom;
            if (! $kingdom instanceof Kingdom
                || isset($seenKingdoms[(string) $kingdom->id])
                || ! $this->eventAuthorization->allows(
                    $actor,
                    EventScope::Kingdom,
                    $kingdom,
                    OperationsPermission::EventKingdomCreate,
                )) {
                continue;
            }

            $seenKingdoms[(string) $kingdom->id] = true;
            $contexts[] = [
                'scope' => EventScope::Kingdom->value,
                'targetId' => (string) $kingdom->id,
                'label' => '#'.(int) $kingdom->number,
                'kingdomId' => (string) $kingdom->id,
                'kingdomNumber' => (int) $kingdom->number,
            ];
        }

        return $contexts;
    }
}
