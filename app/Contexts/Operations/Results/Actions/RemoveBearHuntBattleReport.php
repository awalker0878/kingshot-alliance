<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Actions;

use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWorkflowGuard;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\Results\Enums\BearHuntBattleReportStatus;
use App\Contexts\Operations\Results\Models\BearHuntBattleReport;
use App\Contexts\Operations\Results\Services\BearHuntResultProjector;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RemoveBearHuntBattleReport
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $authorization,
        private EventWorkflowGuard $workflows,
        private BearHuntResultProjector $projector,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $actorPlayerId, string $reportId, string $reason): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['reason' => 'Explain in 10 to 1000 characters why this battle report is being removed.']);
        }
        $route = BearHuntBattleReport::query()->select(['id', 'occurrence_id'])->findOrFail($reportId);

        DB::transaction(function () use ($actorPlayerId, $reportId, $reason, $route): void {
            $occurrenceRoute = EventOccurrence::query()->select(['id', 'event_id'])->whereKey($route->occurrence_id)->firstOrFail();
            $context = $this->eventWriteState->lockEventScope($actorPlayerId, (string) $occurrenceRoute->event_id);
            $this->authorization->authorizeManager($context);
            if ($context->event->scopeEnum() !== EventScope::Alliance
                || $context->event->eventType->slug !== 'bear-hunt') {
                throw ValidationException::withMessages(['event' => 'This occurrence is not an Alliance Bear Hunt Event.']);
            }
            $this->workflows->requireAll($context->event, [
                EventWorkflowDimension::Results,
                EventWorkflowDimension::ScreenshotEvidence,
            ]);
            $report = BearHuntBattleReport::query()->whereKey($reportId)->where('occurrence_id', $occurrenceRoute->id)->lockForUpdate()->firstOrFail();
            if ($report->getRawOriginal('status') === BearHuntBattleReportStatus::Removed->value) {
                return;
            }
            $report->forceFill([
                'status' => BearHuntBattleReportStatus::Removed,
                'removed_by_player_id' => $actorPlayerId,
                'removed_at' => now(),
                'removal_reason' => $reason,
            ])->save();
            $this->projector->recompute((string) $report->occurrence_id, $actorPlayerId);
            $metadata = ['report_id' => (string) $report->id, 'occurrence_id' => (string) $report->occurrence_id, 'actor_player_id' => $actorPlayerId];
            $this->audit->record('bear_hunt.battle_report_removed', $context->actor, $report, $context->target->allianceId, $metadata);
            $this->outbox->record('bear_hunt.battle_report_removed', $context->target->allianceId, $report, $metadata, partitionKey: $context->target->partitionKey());
        });
    }
}
