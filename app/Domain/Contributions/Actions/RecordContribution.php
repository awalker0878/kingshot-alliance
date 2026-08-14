<?php

declare(strict_types=1);

namespace App\Domain\Contributions\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Contributions\Enums\ContributionRecordSource;
use App\Domain\Contributions\Enums\ContributionRecordStatus;
use App\Domain\Contributions\Models\ContributionCategory;
use App\Domain\Contributions\Models\ContributionRecord;
use App\Domain\Contributions\Services\ContributionPeriodResolver;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RecordContribution
{
    public function __construct(
        private readonly ContributionPeriodResolver $periods,
        private readonly AuditRecorder $audit,
        private readonly OutboxRecorder $outbox,
    ) {}

    public function handle(
        Player $actor,
        Alliance $alliance,
        Player $player,
        ContributionCategory $category,
        float $value,
        ContributionRecordSource $source,
        ?string $evidence = null,
    ): ContributionRecord {
        if ($category->alliance_id !== $alliance->id || (string) $player->current_kingdom_id !== (string) $alliance->kingdom_id) {
            throw new InvalidArgumentException('Contribution record references must belong to the active alliance.');
        }

        if (! $category->is_active) {
            throw new InvalidArgumentException('Contribution category is inactive.');
        }

        if ($source === ContributionRecordSource::SelfReported && ! $category->allow_self_report) {
            throw new InvalidArgumentException('This contribution category does not allow member self-reporting.');
        }

        if ($category->evidence_required && trim((string) $evidence) === '') {
            throw new InvalidArgumentException('Evidence is required for this contribution category.');
        }

        $period = $this->periods->current($category, $alliance->timezone);

        return DB::transaction(function () use (
            $actor,
            $alliance,
            $player,
            $category,
            $value,
            $source,
            $evidence,
            $period,
        ): ContributionRecord {
            $record = ContributionRecord::query()->create([
                'alliance_id' => $alliance->id,
                'category_id' => $category->id,
                'player_id' => $player->id,
                'source' => $source,
                'data_class' => $category->data_class,
                'value' => $value,
                'period_start' => $period['start']->toDateString(),
                'period_end' => $period['end']->toDateString(),
                'status' => ContributionRecordStatus::Pending,
                'evidence' => $evidence,
                'calculation_key' => $category->calculation_key,
                'calculation_version' => $category->calculation_version,
                'recorded_at' => now(),
                'recorded_by_player_id' => $actor->id,
            ]);

            $this->audit->record('contribution.record.created', $actor, $record, $alliance, [
                'source' => $source->value,
                'player_id' => $player->id,
                'category_id' => $category->id,
            ]);
            $this->outbox->record('contribution.record.created', $alliance->id, $record, [
                'record_id' => $record->id,
                'status' => $record->status->value,
            ]);

            return $record;
        });
    }
}
