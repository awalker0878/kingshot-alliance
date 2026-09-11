<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Queries;

use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferBlockerState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferBlocker;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferReadinessTransition;
use App\Shared\Infrastructure\Pagination\PageSlice;
use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/** Current authorized, independently pageable workflow records; never an eligibility authority. */
final readonly class TransferWorkflowHistoryQuery
{
    private const int PAGE_SIZE = 25;

    public function __construct(private TransferAuthorization $authorization, private ScopedCursorCodec $cursors) {}

    /** @return PageSlice<TransferBlocker> */
    public function blockers(string $actorPlayerId, string $allianceId, string $planId, string $participantId, TransferBlockerState $state, ?string $cursor = null): PageSlice
    {
        $this->authorizeParticipant($actorPlayerId, $allianceId, $planId, $participantId);
        $query = TransferBlocker::query()->where('alliance_id', $allianceId)->where('transfer_plan_id', $planId)
            ->where('transfer_participant_id', $participantId)->where('state', $state->value)
            ->with(['createdBy:id,current_name', 'resolvedBy:id,current_name']);

        return $this->page($query, 'transfer-blockers|'.$allianceId.'|'.$planId.'|'.$participantId.'|'.$state->value, $cursor);
    }

    /** @return PageSlice<TransferReadinessTransition> */
    public function transitions(string $actorPlayerId, string $allianceId, string $planId, string $participantId, ?string $cursor = null): PageSlice
    {
        $this->authorizeParticipant($actorPlayerId, $allianceId, $planId, $participantId);
        $query = TransferReadinessTransition::query()->where('alliance_id', $allianceId)->where('transfer_plan_id', $planId)
            ->where('transfer_participant_id', $participantId)->with('actor:id,current_name');

        return $this->page($query, 'transfer-readiness-history|'.$allianceId.'|'.$planId.'|'.$participantId, $cursor);
    }

    private function authorizeParticipant(string $actorPlayerId, string $allianceId, string $planId, string $participantId): void
    {
        if (! $this->authorization->allows($actorPlayerId, $allianceId, TransferPermission::View)) {
            throw new AuthorizationException;
        }
        TransferParticipant::query()->where('alliance_id', $allianceId)->where('transfer_plan_id', $planId)
            ->whereKey($participantId)->firstOrFail();
    }

    /**
     * @template T of TransferBlocker|TransferReadinessTransition
     *
     * @param  Builder<T>  $query
     * @return PageSlice<T>
     */
    private function page(Builder $query, string $scope, ?string $cursor): PageSlice
    {
        if ($cursor !== null) {
            $position = $this->cursors->decode($cursor, $scope);
            $at = $position['at'] ?? null;
            $id = $position['id'] ?? null;
            if (! is_string($at) || ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(\.\d{1,6})?$/D', $at)
                || ! is_string($id) || ! preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/Di', $id)) {
                throw ValidationException::withMessages(['cursor' => 'The workflow history cursor is invalid.']);
            }
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $at, new DateTimeZone('UTC'));
            if ($parsed === false || $parsed->format('Y-m-d H:i:s.u') !== $at) {
                throw ValidationException::withMessages(['cursor' => 'The workflow history timestamp is invalid.']);
            }
            $query->where(function (Builder $older) use ($at, $id): void {
                $older->where('created_at', '<', $at)->orWhere(function (Builder $tie) use ($at, $id): void {
                    $tie->where('created_at', $at)->where('id', '<', $id);
                });
            });
        }
        $rows = $query->orderByDesc('created_at')->orderByDesc('id')->limit(self::PAGE_SIZE + 1)->get();
        $items = $rows->take(self::PAGE_SIZE)->values();
        $last = $items->last();

        /** @var list<T> $records */
        $records = array_values($items->all());

        return new PageSlice($records, $rows->count() > self::PAGE_SIZE && $last !== null
            ? $this->cursors->encode($scope, ['at' => $last->created_at?->format('Y-m-d H:i:s.u'), 'id' => (string) $last->id])
            : null, self::PAGE_SIZE, $cursor === null);
    }
}
