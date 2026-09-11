<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Queries;

use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\ValueObjects\AnnouncementDeliverySummary;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use UnexpectedValueException;

/** Complete owner facts for a bounded set already authorized by the consuming Content projection. */
final class AnnouncementDeliverySummaryQuery
{
    private const MAX_RUNS = 100;

    private const RETRY_PAGE = 50;

    /**
     * @param  array<string,string>  $contentByRun  Run ID to its actual Content subject ID.
     * @return array<string,AnnouncementDeliverySummary>
     */
    public function forRuns(string $allianceId, array $contentByRun): array
    {
        if (trim($allianceId) === '' || count($contentByRun) > self::MAX_RUNS) {
            throw new InvalidArgumentException('A nonempty Alliance and at most 100 broadcast runs are required.');
        }
        foreach ($contentByRun as $run => $content) {
            if (! is_string($run) || $run === '' || ! is_string($content) || $content === '') {
                throw new InvalidArgumentException('Every run requires its explicit Content subject.');
            }
        }
        if ($contentByRun === []) {
            return [];
        }
        $messages = DB::table('notification_messages as m')
            ->where('m.notification_type', 'alliance.announcement')->where('m.subject_type', 'content_item')
            ->where('m.metadata->alliance_id', $allianceId)
            ->where(function (Builder $scope) use ($contentByRun): void {
                foreach ($contentByRun as $run => $content) {
                    $scope->orWhere(static fn (Builder $entry) => $entry
                        ->where('m.metadata->broadcast_run_id', $run)->where('m.subject_id', $content)
                        ->where('m.metadata->content_item_id', $content));
                }
            });
        $totals = (clone $messages)->leftJoin('notification_deliveries as d', 'd.notification_message_id', '=', 'm.id')
            ->select('m.metadata->broadcast_run_id as run_id')
            ->selectRaw('COUNT(DISTINCT CASE WHEN m.read_at IS NOT NULL THEN m.id END) AS read_count')
            ->selectRaw('COUNT(d.id) AS delivery_count')
            ->selectRaw('SUM(CASE WHEN d.status = ? AND d.attempt_count < d.max_attempts THEN 1 ELSE 0 END) AS retry_count', [DeliveryStatus::Failed->value])
            ->groupBy('m.metadata->broadcast_run_id');
        foreach (DeliveryStatus::cases() as $status) {
            $totals->selectRaw('SUM(CASE WHEN d.status = ? THEN 1 ELSE 0 END) AS '.$status->value.'_count', [$status->value]);
        }
        $rows = $totals->get()->keyBy('run_id');
        $failed = (clone $messages)->join('notification_deliveries as d', 'd.notification_message_id', '=', 'm.id')
            ->where('d.status', DeliveryStatus::Failed->value)->whereColumn('d.attempt_count', '<', 'd.max_attempts')
            ->select('m.metadata->broadcast_run_id as run_id', 'd.id', 'd.created_at');
        $ranked = DB::query()->fromSub($failed, 'failed')->select('run_id', 'id')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY run_id ORDER BY created_at DESC, id DESC) AS position');
        $candidates = DB::query()->fromSub($ranked, 'ranked')->where('position', '<=', self::RETRY_PAGE)
            ->orderBy('run_id')->orderBy('position')->get(['run_id', 'id'])->groupBy('run_id');

        $result = [];
        foreach ($contentByRun as $runId => $content) {
            $row = $rows->get($runId);
            $counts = [];
            foreach (DeliveryStatus::cases() as $status) {
                $counts[$status->value] = (int) ($row->{$status->value.'_count'} ?? 0);
            }
            if ($row !== null && array_sum($counts) !== (int) $row->delivery_count) {
                throw new UnexpectedValueException('An announcement delivery has an unrecognized status.');
            }
            $ids = [];
            foreach ($candidates->get($runId, collect()) as $candidate) {
                $ids[] = (string) $candidate->id;
            }
            $result[$runId] = new AnnouncementDeliverySummary((int) ($row->read_count ?? 0), $counts, (int) ($row->retry_count ?? 0), $ids);
        }

        return $result;
    }
}
