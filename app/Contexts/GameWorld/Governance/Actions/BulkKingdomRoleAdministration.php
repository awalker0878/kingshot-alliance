<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Actions;

use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Players\Models\Player;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class BulkKingdomRoleAdministration
{
    public function __construct(private AssignKingdomRole $assign, private RemoveKingdomRole $remove) {}

    /** @param list<string> $playerIds @return array{eligible:list<string>,ineligible:array<string,string>} */
    public function preview(string $kingdomId, string $roleId, string $operation, array $playerIds): array
    {
        $playerIds = array_values(array_unique(array_map('strval', $playerIds)));
        if (count($playerIds) > 50) {
            throw ValidationException::withMessages(['players' => 'Bulk Kingdom role administration is limited to 50 Governors.']);
        }
        if (! in_array($operation, ['assign', 'remove'], true)) {
            throw ValidationException::withMessages(['operation' => 'Unsupported Kingdom role bulk operation.']);
        }
        $role = KingdomRole::query()->whereKey($roleId)->where('kingdom_id', $kingdomId)->whereNull('archived_at')->firstOrFail();
        $players = Player::query()->whereIn('id', $playerIds)->get()->keyBy('id');
        $eligible = [];
        $ineligible = [];
        foreach ($playerIds as $playerId) {
            $player = $players->get($playerId);
            if (! $player instanceof Player || (string) $player->current_kingdom_id !== $kingdomId) {
                $ineligible[$playerId] = 'Governor is not currently in this Kingdom.';
                continue;
            }
            $assignment = KingdomRoleAssignment::query()->effective()->where('kingdom_id', $kingdomId)->where('player_id', $playerId)->where('kingdom_role_id', $roleId)->first();
            if ($operation === 'assign' && $assignment instanceof KingdomRoleAssignment) {
                $ineligible[$playerId] = 'Governor already has this effective role.';
                continue;
            }
            if ($operation === 'remove' && ! $assignment instanceof KingdomRoleAssignment) {
                $ineligible[$playerId] = 'Governor does not have this effective role.';
                continue;
            }
            $eligible[] = $playerId;
        }
        if ($operation === 'remove' && $role->key === DefaultKingdomRole::Administrator->value && $eligible !== []) {
            $adminCount = KingdomRoleAssignment::query()->effective()->where('kingdom_id', $kingdomId)->whereHas('role', static fn ($query) => $query->where('key', DefaultKingdomRole::Administrator->value))->distinct('player_id')->count('player_id');
            if ($adminCount - count($eligible) < 1) {
                foreach ($eligible as $playerId) {
                    $ineligible[$playerId] = 'Bulk removal would leave the Kingdom without an effective administrator.';
                }
                $eligible = [];
            }
        }

        return ['eligible' => $eligible, 'ineligible' => $ineligible];
    }

    /** @param list<string> $playerIds @return array{applied:list<string>,skipped:array<string,string>} */
    public function handle(string $actorPlayerId, string $kingdomId, string $roleId, string $operation, array $playerIds, ?string $reason = null): array
    {
        $preview = $this->preview($kingdomId, $roleId, $operation, $playerIds);
        $applied = [];
        DB::transaction(function () use ($actorPlayerId, $kingdomId, $roleId, $operation, $reason, $preview, &$applied): void {
            foreach ($preview['eligible'] as $playerId) {
                if ($operation === 'assign') {
                    $this->assign->handle($actorPlayerId, $kingdomId, $playerId, $roleId, null, null, $reason);
                } else {
                    $assignment = KingdomRoleAssignment::query()->effective()->where('kingdom_id', $kingdomId)->where('player_id', $playerId)->where('kingdom_role_id', $roleId)->firstOrFail();
                    $this->remove->handle($actorPlayerId, $kingdomId, (string) $assignment->id, $reason);
                }
                $applied[] = $playerId;
            }
        });

        return ['applied' => $applied, 'skipped' => $preview['ineligible']];
    }
}
