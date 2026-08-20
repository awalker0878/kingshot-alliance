<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Policies\MemberCapacityPolicy;
use App\Contexts\Alliance\Membership\Services\MembershipAdministrationGuard;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class PreviewMembershipStatusBulkChange
{
    public function __construct(
        private AllianceWriteState $allianceWriteState,
        private AllianceAuthorization $authority,
        private MembershipAdministrationGuard $guard,
        private MemberCapacityPolicy $capacity,
        private PlayerReferenceQuery $players,
    ) {}

    /**
     * @param non-empty-list<string> $membershipIds
     * @return array{
     *   targetStatus: string,
     *   items: non-empty-list<array{itemId: string, label: string, fromStatus: string|null, outcome: string, code: string}>,
     *   ready: int,
     *   blocked: int,
     *   readyItemIds: list<string>
     * }
     */
    public function handle(
        string $actorPlayerId,
        string $allianceId,
        array $membershipIds,
        MembershipStatus $target,
    ): array {
        if (! in_array($target, [MembershipStatus::Active, MembershipStatus::Suspended, MembershipStatus::Removed], true)) {
            throw ValidationException::withMessages([
                'status' => 'This membership state is not available through administration.',
            ]);
        }

        return DB::transaction(function () use ($actorPlayerId, $allianceId, $membershipIds, $target): array {
            $context = $this->allianceWriteState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, AlliancePermission::MembershipManage);

            $memberships = AllianceMembership::query()
                ->where('alliance_id', $allianceId)
                ->whereIn('id', $membershipIds)
                ->get()
                ->keyBy(static fn (AllianceMembership $membership): string => (string) $membership->id);
            $playerReferences = $this->players->byIds(
                $memberships->pluck('player_id')->map(static fn ($id): string => (string) $id)->all(),
            );
            $activationSlots = $target === MembershipStatus::Active
                ? $this->capacity->remainingCapacity($context->alliance)
                : 0;
            $items = [];
            $readyItemIds = [];

            foreach ($membershipIds as $membershipId) {
                $membership = $memberships->get($membershipId);
                if (! $membership instanceof AllianceMembership) {
                    $items[] = $this->item($membershipId, $membershipId, null, 'blocked', 'member-unavailable');
                    continue;
                }

                $player = $playerReferences[(string) $membership->player_id] ?? null;
                $label = $player?->currentName ?? 'Unknown player';
                $from = $membership->status;

                if ($from === $target) {
                    $items[] = $this->item($membershipId, $label, $from, 'skipped', 'already-in-target-status');
                    continue;
                }

                try {
                    $this->guard->assertCanManage($context, $membership);
                    if ($target !== MembershipStatus::Active) {
                        $this->guard->assertCanDeactivate($membership);
                    }
                } catch (AuthorizationException|ValidationException) {
                    $items[] = $this->item($membershipId, $label, $from, 'blocked', 'member-protected');
                    continue;
                }

                if ($target === MembershipStatus::Active) {
                    if ($player === null || $player->kingdomId !== (string) $context->alliance->kingdom_id) {
                        $items[] = $this->item($membershipId, $label, $from, 'blocked', 'wrong-kingdom');
                        continue;
                    }

                    $hasOtherActiveMembership = AllianceMembership::query()
                        ->where('player_id', $membership->player_id)
                        ->where('status', MembershipStatus::Active->value)
                        ->where('id', '<>', $membership->id)
                        ->exists();
                    if ($hasOtherActiveMembership) {
                        $items[] = $this->item($membershipId, $label, $from, 'blocked', 'already-active-elsewhere');
                        continue;
                    }

                    if ($activationSlots < 1) {
                        $items[] = $this->item($membershipId, $label, $from, 'blocked', 'capacity-reached');
                        continue;
                    }
                    $activationSlots--;
                }

                $items[] = $this->item($membershipId, $label, $from, 'ready', 'ready');
                $readyItemIds[] = $membershipId;
            }

            return [
                'targetStatus' => $target->value,
                'items' => $items,
                'ready' => count($readyItemIds),
                'blocked' => count($membershipIds) - count($readyItemIds),
                'readyItemIds' => $readyItemIds,
            ];
        });
    }

    /** @return array{itemId: string, label: string, fromStatus: string|null, outcome: string, code: string} */
    private function item(
        string $itemId,
        string $label,
        ?MembershipStatus $from,
        string $outcome,
        string $code,
    ): array {
        return [
            'itemId' => $itemId,
            'label' => $label,
            'fromStatus' => $from?->value,
            'outcome' => $outcome,
            'code' => $code,
        ];
    }
}
