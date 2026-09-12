<?php

declare(strict_types=1);

namespace App\ReadModels\TransferManagement\Queries;

use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Shared\Infrastructure\Pagination\PageSlice;
use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class TransferGroupKingdomPageQuery
{
    public function __construct(private TransferAuthorization $authorization, private ScopedCursorCodec $cursors) {}

    /** @return array<string,mixed> */
    public function page(string $actorId, string $allianceId, string $planId, string $groupId, ?string $cursor = null): array
    {
        if (! $this->authorization->allows($actorId, $allianceId, TransferPermission::Manage)) {
            throw new AuthorizationException;
        }
        $plan = TransferPlan::query()->where('alliance_id', $allianceId)->whereKey($planId)->firstOrFail();
        TransferGroup::query()->where('alliance_id', $allianceId)->where('transfer_window_id', $plan->transfer_window_id)->whereKey($groupId)->firstOrFail();
        $query = DB::table('transfer_group_kingdoms as membership')->join('kingdoms as kingdom', 'kingdom.id', '=', 'membership.kingdom_id')
            ->where('membership.transfer_group_id', $groupId);
        $scope = implode('|', ['transfer-group-kingdoms', $actorId, $allianceId, $planId, $groupId]);
        if ($cursor !== null) {
            $position = $this->cursors->decode($cursor, $scope);
            $after = $position['after'] ?? null;
            if (array_keys($position) !== ['after'] || ! is_string($after) || ! preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/Di', $after)) {
                throw ValidationException::withMessages(['cursor' => 'The group membership cursor is invalid.']);
            }
            $query->where('kingdom.id', '>', $after);
        }
        // A recorded official-group revision has immutable membership.
        $rows = $query->select(['kingdom.id', 'kingdom.number'])->orderBy('kingdom.id')->limit(26)->get();
        $page = $rows->take(25);
        $last = $page->last();
        $items = $page->map(fn ($row): array => ['id' => (string) $row->id, 'number' => (string) $row->number])->values()->all();

        return (new PageSlice(array_values($items), $rows->count() > 25 && $last !== null
            ? $this->cursors->encode($scope, ['after' => (string) $last->id]) : null, 25, $cursor === null))->toArray();
    }
}
