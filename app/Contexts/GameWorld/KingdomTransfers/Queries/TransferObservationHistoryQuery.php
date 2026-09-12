<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Queries;

use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Shared\Infrastructure\Pagination\PageSlice;
use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final readonly class TransferObservationHistoryQuery
{
    public function __construct(private TransferAuthorization $authorization, private ScopedCursorCodec $cursors) {}

    /** @return PageSlice<TransferObservation> */
    public function forParticipant(string $actorPlayerId, string $allianceId, string $planId, string $participantId, ?string $cursor = null): PageSlice
    {
        if (! $this->authorization->allows($actorPlayerId, $allianceId, TransferPermission::View)) {
            throw new AuthorizationException;
        }
        TransferParticipant::query()->where('alliance_id', $allianceId)->where('transfer_plan_id', $planId)->whereKey($participantId)->firstOrFail();
        $scope = 'transfer-observations|'.$allianceId.'|'.$planId.'|'.$participantId;
        $query = TransferObservation::query()->where('alliance_id', $allianceId)->where('transfer_plan_id', $planId)
            ->where('transfer_participant_id', $participantId)->with('targetKingdom:id,number');
        if ($cursor !== null) {
            $position = $this->cursors->decode($cursor, $scope);
            $at = $position['at'] ?? null;
            $id = $position['id'] ?? null;
            if (! is_string($at) || ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(\.\d{1,6})?$/D', $at)
                || ! is_string($id) || ! preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/Di', $id)) {
                throw ValidationException::withMessages(['cursor' => 'The observation history cursor is invalid.']);
            }
            $query->where(function (Builder $older) use ($at, $id): void {
                $older->where('observed_at', '<', $at)->orWhere(function (Builder $tie) use ($at, $id): void {
                    $tie->where('observed_at', $at)->where('id', '<', $id);
                });
            });
        }
        $rows = $query->orderByDesc('observed_at')->orderByDesc('id')->limit(26)->get();
        $items = $rows->take(25)->values();
        $last = $items->last();

        return new PageSlice(array_values($items->all()), $rows->count() > 25 && $last instanceof TransferObservation
            ? $this->cursors->encode($scope, ['at' => $last->observed_at->format('Y-m-d H:i:s.u'), 'id' => (string) $last->id])
            : null, 25, $cursor === null);
    }
}
