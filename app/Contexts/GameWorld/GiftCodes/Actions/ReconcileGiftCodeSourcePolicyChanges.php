<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeProvenance;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceReconciliationJob;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

final readonly class ReconcileGiftCodeSourcePolicyChanges
{
    public function __construct(
        private ReconcileGiftCodeStatus $trust,
        private ReconcileGiftCodeFacts $facts,
    ) {}

    /** @return array{examined:int,nextCursor:?string,completed:bool} */
    public function handle(int $limit = 200): array
    {
        $limit = max(1, min(1000, $limit));
        $job = GiftCodeSourceReconciliationJob::query()
            ->whereNull('completed_at')
            ->orderBy('id')
            ->first();
        if (! $job instanceof GiftCodeSourceReconciliationJob) {
            return ['examined' => 0, 'nextCursor' => null, 'completed' => true];
        }

        $rows = GiftCodeProvenance::query()
            ->select('gift_code_id')
            ->where('registered_source_id', $job->gift_code_source_id)
            ->when($job->cursor_gift_code_id !== null, static fn (Builder $query) =>
                $query->where('gift_code_id', '>', $job->cursor_gift_code_id))
            ->distinct()
            ->orderBy('gift_code_id')
            ->limit($limit + 1)
            ->pluck('gift_code_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
        $truncated = count($rows) > $limit;
        $giftCodeIds = array_slice($rows, 0, $limit);
        foreach ($giftCodeIds as $giftCodeId) {
            $this->trust->handle($giftCodeId);
            $this->facts->handle($giftCodeId);
        }
        $last = $giftCodeIds === [] ? null : $giftCodeIds[array_key_last($giftCodeIds)];
        $job->forceFill([
            'cursor_gift_code_id' => $truncated ? $last : null,
            'examined_count' => $job->examined_count + count($giftCodeIds),
            'completed_at' => $truncated ? null : now(),
        ])->save();
        $result = [
            'examined' => count($giftCodeIds),
            'nextCursor' => $truncated ? $last : null,
            'completed' => ! $truncated,
        ];
        Log::info('gift_codes.source_policy_reconciliation', [
            ...$result,
            'job_id' => (string) $job->id,
            'source_id' => $job->gift_code_source_id,
            'source_revision' => $job->source_revision,
        ]);

        return $result;
    }
}
