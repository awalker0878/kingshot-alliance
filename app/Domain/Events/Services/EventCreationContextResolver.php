<?php

declare(strict_types=1);

namespace App\Domain\Events\Services;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Models\KingdomRoleAssignment;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
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
            PermissionKey::EventPlayerCreate,
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
            PermissionKey::EventAllianceCreate,
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
                $query->where('permissions.key', PermissionKey::EventKingdomCreate->value);
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
                    PermissionKey::EventKingdomCreate,
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
