<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Contributions\Actions;

use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Contributions\Enums\ContributionRecordStatus;
use App\Contexts\Intelligence\Contributions\Models\ContributionRecord;
use Illuminate\Support\Facades\DB;

final readonly class PreviewContributionBulkApproval
{
    public function __construct(
        private AllianceIntelligenceWriteState $writeState,
        private PlayerReferenceQuery $players,
    ) {}

    /**
     * @param  non-empty-list<string>  $recordIds
     * @return array{
     *   operation: string,
     *   items: non-empty-list<array{itemId: string, label: string, fromStatus: string|null, outcome: string, code: string}>,
     *   ready: int,
     *   blocked: int,
     *   readyItemIds: list<string>
     * }
     */
    public function handle(string $actorPlayerId, string $allianceId, array $recordIds): array
    {
        return DB::transaction(function () use ($actorPlayerId, $allianceId, $recordIds): array {
            $this->writeState->authorize(
                $actorPlayerId,
                $allianceId,
                IntelligencePermission::ContributionManage,
            );
            $records = ContributionRecord::query()
                ->where('alliance_id', $allianceId)
                ->whereIn('id', $recordIds)
                ->with('category')
                ->get()
                ->keyBy(static fn (ContributionRecord $record): string => (string) $record->id);
            /** @var list<string> $playerIds */
            $playerIds = array_values($records->pluck('player_id')->map(static fn ($id): string => (string) $id)->all());
            $players = $this->players->byIds($playerIds);
            $items = [];
            $readyItemIds = [];

            foreach ($recordIds as $recordId) {
                $record = $records->get($recordId);
                if (! $record instanceof ContributionRecord) {
                    $items[] = $this->item($recordId, $recordId, null, 'blocked', 'record-unavailable');

                    continue;
                }

                $player = $players[(string) $record->player_id];
                $label = $player->currentName.' · '.($record->category->name ?? 'Unknown category');
                if ($record->status === ContributionRecordStatus::Approved) {
                    $items[] = $this->item($recordId, $label, $record->status, 'skipped', 'already-approved');
                } elseif ($record->status === ContributionRecordStatus::Reversed) {
                    $items[] = $this->item($recordId, $label, $record->status, 'blocked', 'record-reversed');
                } else {
                    $items[] = $this->item($recordId, $label, $record->status, 'ready', 'ready');
                    $readyItemIds[] = $recordId;
                }
            }

            return [
                'operation' => 'approve',
                'items' => $items,
                'ready' => count($readyItemIds),
                'blocked' => count($recordIds) - count($readyItemIds),
                'readyItemIds' => $readyItemIds,
            ];
        });
    }

    /** @return array{itemId: string, label: string, fromStatus: string|null, outcome: string, code: string} */
    private function item(
        string $itemId,
        string $label,
        ?ContributionRecordStatus $from,
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
