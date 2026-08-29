<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Queries;

use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventType;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWorkflowGuard;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\Results\ValueObjects\BearHuntEvidenceTarget;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class BearHuntEvidenceTargetQuery
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $authorization,
        private EventWorkflowGuard $workflows,
    ) {}

    public function authorizeManage(string $actorPlayerId, string $occurrenceId): BearHuntEvidenceTarget
    {
        return DB::transaction(function () use ($actorPlayerId, $occurrenceId): BearHuntEvidenceTarget {
            $route = EventOccurrence::query()->select(['id', 'event_id'])->whereKey($occurrenceId)->firstOrFail();
            $context = $this->eventWriteState->lockEventScope($actorPlayerId, (string) $route->event_id);
            $this->authorization->authorizeManager($context);
            $this->workflows->requireAll($context->event, [
                EventWorkflowDimension::Results,
                EventWorkflowDimension::ScreenshotEvidence,
            ]);

            if ($context->event->scopeEnum() !== EventScope::Alliance || ! is_string($context->event->alliance_id)) {
                throw ValidationException::withMessages(['event' => 'Screenshot Intake currently supports Alliance Bear Hunt Events only.']);
            }

            $eventType = EventType::query()->select(['id', 'slug'])->whereKey($context->event->event_type_id)->sharedLock()->firstOrFail();
            if ($eventType->slug !== 'bear-hunt') {
                throw ValidationException::withMessages(['event' => 'Screenshot Intake currently supports Bear Hunt battle reports only.']);
            }

            EventOccurrence::query()
                ->whereKey($occurrenceId)
                ->where('event_id', $context->event->id)
                ->sharedLock()
                ->firstOrFail();

            return new BearHuntEvidenceTarget(
                occurrenceId: $occurrenceId,
                eventId: (string) $context->event->id,
                allianceId: (string) $context->event->alliance_id,
            );
        });
    }
}
