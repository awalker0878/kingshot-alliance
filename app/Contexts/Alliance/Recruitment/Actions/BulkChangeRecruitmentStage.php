<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Actions;

use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Http\BulkActionResult;
use App\Shared\Infrastructure\Http\BulkItemResult;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

final readonly class BulkChangeRecruitmentStage
{
    public function __construct(
        private PreviewRecruitmentStageBulkChange $preview,
        private ChangeRecruitmentStage $change,
        private PlayerReferenceQuery $players,
        private AuditRecorder $audit,
    ) {}

    /** @param non-empty-list<string> $candidateIds */
    public function handle(
        string $actorPlayerId,
        string $allianceId,
        array $candidateIds,
        RecruitmentStage $target,
        ?string $reason = null,
        ?CarbonImmutable $nextActionAt = null,
    ): BulkActionResult {
        $preview = $this->preview->handle($actorPlayerId, $allianceId, $candidateIds, $target);
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
                $this->change->handle(
                    $actorPlayerId,
                    $allianceId,
                    $item['itemId'],
                    $target,
                    $reason,
                    $nextActionAt,
                );
                $items[] = BulkItemResult::succeeded($item['itemId'], $item['label'], 'stage-updated');
            } catch (ModelNotFoundException) {
                $items[] = BulkItemResult::failed(
                    $item['itemId'],
                    $item['label'],
                    'candidate-unavailable',
                );
            } catch (ValidationException $exception) {
                $items[] = BulkItemResult::failed(
                    $item['itemId'],
                    $item['label'],
                    array_key_exists('candidate', $exception->errors())
                        ? 'candidate-unavailable'
                        : 'transition-not-allowed',
                );
            }
        }

        /** @var non-empty-list<BulkItemResult> $items */
        $result = new BulkActionResult('recruitment-stage-change', $items);
        $payload = $result->toArray();
        $this->audit->record(
            'recruitment.candidates.bulk_stage_changed',
            $this->players->require($actorPlayerId),
            null,
            $allianceId,
            [
                'target_stage' => $target->value,
                'candidate_ids' => $candidateIds,
                'succeeded' => $payload['succeeded'],
                'failed' => $payload['failed'],
                'skipped' => $payload['skipped'],
            ],
        );

        return $result;
    }
}
