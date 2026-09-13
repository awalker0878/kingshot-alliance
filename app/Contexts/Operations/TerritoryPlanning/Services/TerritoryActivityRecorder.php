<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Services;

use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryActivity;
use App\Contexts\Operations\TerritoryPlanning\ValueObjects\TerritoryPlanMutationContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final class TerritoryActivityRecorder
{
    /** Durable owner intent, committed atomically; Workflow drains only committed rows.
     * @param  list<string>  $recipientPlayerIds
     */
    public function record(TerritoryPlanMutationContext $context, string $kind, string $meaningKey, array $recipientPlayerIds = []): string
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Territory activity must be recorded in its owner transaction.');
        }
        if (! in_array($kind, ['published', 'reviewed', 'assigned', 'review_requested'], true)
            || $meaningKey === '' || strlen($meaningKey) > 120 || count($recipientPlayerIds) > 100) {
            throw new InvalidArgumentException('Invalid Territory notification intent.');
        }
        $row = TerritoryActivity::query()->firstOrCreate([
            'territory_plan_id' => $context->plan->id, 'kind' => $kind, 'meaning_key' => $meaningKey,
        ], ['actor_player_id' => $context->actor->playerId, 'recipient_player_ids' => array_values(array_unique($recipientPlayerIds))]);

        return (string) $row->id;
    }

    /** @param list<array<string,mixed>> $previousObjects
     * @param list<array<string,mixed>> $objects
     */
    public function recordAssignments(TerritoryPlanMutationContext $context, array $previousObjects, array $objects): void
    {
        $previous = [];
        foreach ($previousObjects as $object) {
            $previous[(string) ($object['key'] ?? '')] = $object['player_id'] ?? null;
        }
        $recipients = [];
        foreach ($objects as $object) {
            $playerId = $object['player_id'] ?? null;
            if (is_string($playerId) && $playerId !== '' && ($previous[(string) ($object['key'] ?? '')] ?? null) !== $playerId) {
                $recipients[$playerId] = true;
            }
        }
        $ids = array_keys($recipients);
        sort($ids);
        foreach (array_chunk($ids, 100) as $page => $playerIds) {
            $this->record($context, 'assigned', $context->plan->revision.':'.$page.':'.hash('sha256', implode('|', $playerIds)), $playerIds);
        }
    }

}
