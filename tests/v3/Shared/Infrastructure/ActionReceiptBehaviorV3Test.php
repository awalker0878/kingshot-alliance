<?php

declare(strict_types=1);

namespace Tests\v3\Shared\Infrastructure;

use App\Shared\Infrastructure\Http\ActionReceipt;
use App\Shared\Infrastructure\Http\BulkActionResult;
use App\Shared\Infrastructure\Http\BulkItemResult;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ActionReceiptBehaviorV3Test extends TestCase
{
    public function test_success_receipt_serializes_a_stable_localizable_contract(): void
    {
        self::assertSame([
            'code' => 'roster-import-previewed',
            'parameters' => ['records' => 42],
            'tone' => 'success',
        ], ActionReceipt::success('roster-import-previewed', ['records' => 42])->toArray());
    }

    public function test_warning_receipt_uses_the_supported_warning_tone(): void
    {
        self::assertSame('warning', ActionReceipt::warning('roster-import-already-committed')->tone);
    }

    public function test_receipt_rejects_unstable_codes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ActionReceipt::success('Roster import complete');
    }

    public function test_bulk_result_preserves_per_item_outcomes_and_failed_item_retry_ids(): void
    {
        $result = new BulkActionResult('candidate-stage-change', [
            BulkItemResult::succeeded('01', 'Ready Candidate', 'stage-updated'),
            BulkItemResult::failed('02', 'Blocked Candidate', 'transition-not-allowed'),
            BulkItemResult::skipped('03', 'Complete Candidate', 'already-in-target-stage'),
        ]);

        self::assertSame([
            'action' => 'candidate-stage-change',
            'items' => [
                ['itemId' => '01', 'label' => 'Ready Candidate', 'outcome' => 'succeeded', 'code' => 'stage-updated'],
                ['itemId' => '02', 'label' => 'Blocked Candidate', 'outcome' => 'failed', 'code' => 'transition-not-allowed'],
                ['itemId' => '03', 'label' => 'Complete Candidate', 'outcome' => 'skipped', 'code' => 'already-in-target-stage'],
            ],
            'succeeded' => 1,
            'failed' => 1,
            'skipped' => 1,
            'failedItemIds' => ['02'],
        ], $result->toArray());
    }
}
