<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Contributions\Actions;

use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Http\BulkActionResult;
use App\Shared\Infrastructure\Http\BulkItemResult;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

final readonly class BulkApproveContributionRecords
{
    public function __construct(
        private PreviewContributionBulkApproval $preview,
        private ApproveContributionRecord $approve,
        private PlayerReferenceQuery $players,
        private AuditRecorder $audit,
    ) {}

    /** @param non-empty-list<string> $recordIds */
    public function handle(string $actorPlayerId, string $allianceId, array $recordIds): BulkActionResult
    {
        $preview = $this->preview->handle($actorPlayerId, $allianceId, $recordIds);
        $items = [];

        foreach ($preview['items'] as $item) {
            if ($item['outcome'] === 'skipped') {
                $items[] = BulkItemResult::skipped($item['itemId'], $item['label'], $item['code']);
                continue;
            }
            if ($item['outcome'] !== 'ready') {
                $items[] = BulkItemResult::failed($item['itemId'], $item['label'], $item['code']);
                continue;
            }

            try {
                $this->approve->handle($actorPlayerId, $allianceId, $item['itemId']);
                $items[] = BulkItemResult::succeeded($item['itemId'], $item['label'], 'record-approved');
            } catch (AuthorizationException|ModelNotFoundException) {
                $items[] = BulkItemResult::failed($item['itemId'], $item['label'], 'record-unavailable');
            } catch (InvalidArgumentException) {
                $items[] = BulkItemResult::failed($item['itemId'], $item['label'], 'approval-not-allowed');
            }
        }

        /** @var non-empty-list<BulkItemResult> $items */
        $result = new BulkActionResult('contribution-approval', $items);
        $payload = $result->toArray();
        $this->audit->record(
            'contribution.records.bulk_approved',
            $this->players->require($actorPlayerId),
            null,
            $allianceId,
            [
                'record_ids' => $recordIds,
                'succeeded' => $payload['succeeded'],
                'failed' => $payload['failed'],
                'skipped' => $payload['skipped'],
            ],
        );

        return $result;
    }
}
