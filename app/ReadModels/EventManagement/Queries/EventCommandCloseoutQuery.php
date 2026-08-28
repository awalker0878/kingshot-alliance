<?php

declare(strict_types=1);

namespace App\ReadModels\EventManagement\Queries;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Evidence\Queries\EventEvidenceCommandQuery;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Participation\Queries\EventParticipationQuery;
use App\Contexts\Operations\Rallies\Queries\EventRallyCommandQuery;
use App\Contexts\Operations\Results\Queries\EventResultCommandQuery;
use App\ReadModels\EventAnalysis\Queries\EventDebriefAvailabilityQuery;
use App\ReadModels\EventManagement\Enums\EventCommandItemStatus as Status;
use App\ReadModels\EventManagement\Enums\EventCommandSeverity as Severity;
use App\ReadModels\EventManagement\Support\EventCommandItems as Items;
use App\ReadModels\EventManagement\Support\EventCommandOwnerReader;

final readonly class EventCommandCloseoutQuery
{
    public function __construct(
        private EventParticipationQuery $participation,
        private EventRallyCommandQuery $rallies,
        private EventResultCommandQuery $results,
        private EventEvidenceCommandQuery $evidence,
        private EventDebriefAvailabilityQuery $debrief,
        private EventCommandOwnerReader $owners,
    ) {}

    /**
     * @param list<string> $dimensions
     * @return list<array<string, mixed>>
     */
    public function forOccurrence(PlayerReference $actor, Event $event, EventOccurrence $occurrence, array $dimensions): array
    {
        $sections = [];
        if ($this->has($dimensions, EventWorkflowDimension::Participation)) {
            $sections[] = $this->attendance($event, $occurrence);
        }
        if ($this->has($dimensions, EventWorkflowDimension::Rallies)) {
            $sections[] = $this->rallies($event, $occurrence);
        }
        if ($this->has($dimensions, EventWorkflowDimension::Results)) {
            $sections[] = $this->results($event, $occurrence);
        }
        if ($this->bearHuntEvidenceApplicable($event, $dimensions)) {
            $sections[] = $this->evidence($actor, $event, $occurrence);
        }
        if ($this->has($dimensions, EventWorkflowDimension::Debrief)) {
            $debrief = $this->debrief($event, $occurrence);
            if ($debrief !== null) {
                $sections[] = $debrief;
            }
        }

        return $sections;
    }

    /** @return array<string, mixed> */
    private function attendance(Event $event, EventOccurrence $occurrence): array
    {
        $summary = $this->owners->read('operations.participation', $event, $occurrence, fn (): array => $this->participation->commandSummary($occurrence));
        if ($summary === null) {
            return Items::section('attendance', 'events.command.sections.attendance', 'closeout', [$this->unknown($event, $occurrence, 'closeout.attendance_unavailable', 'operations.participation', 'events.command.items.attendanceUnavailable', 'participation')]);
        }
        $missing = (int) $summary['attendanceMissingCount'];

        return Items::section('attendance', 'events.command.sections.attendance', 'closeout', [
            Items::make('closeout.attendance_missing', 'closeout', $missing > 0 ? Status::NeedsAttention : Status::Complete, $missing > 0 ? Severity::Blocking : Severity::Informational, 'operations.participation', $missing > 0 ? 'events.command.items.attendanceMissing' : 'events.command.items.attendanceComplete', ['count' => $missing], $missing, handoff: Items::handoff($event, $occurrence, 'participation', 'events.command.actions.recordAttendance')),
        ]);
    }

    /** @return array<string, mixed> */
    private function rallies(Event $event, EventOccurrence $occurrence): array
    {
        $summary = $this->owners->read('operations.rallies', $event, $occurrence, fn (): array => $this->rallies->forOccurrence($occurrence));
        if ($summary === null) {
            return Items::section('rallies', 'events.command.sections.rallies', 'closeout', [$this->unknown($event, $occurrence, 'closeout.rallies_unavailable', 'operations.rallies', 'events.command.items.ralliesUnavailable', 'rallies')]);
        }
        if ((int) $summary['plannedAssignmentCount'] === 0) {
            return Items::section('rallies', 'events.command.sections.rallies', 'closeout', [Items::make('closeout.rallies_not_planned', 'closeout', Status::NotApplicable, Severity::Informational, 'operations.rallies', 'events.command.items.rallyActualsNotApplicable')]);
        }
        $missing = (int) $summary['missingActualCount'];

        return Items::section('rallies', 'events.command.sections.rallies', 'closeout', [
            Items::make('closeout.rally_actuals_missing', 'closeout', $missing > 0 ? Status::NeedsAttention : Status::Complete, $missing > 0 ? Severity::Blocking : Severity::Informational, 'operations.rallies', $missing > 0 ? 'events.command.items.rallyActualsMissing' : 'events.command.items.rallyActualsComplete', ['count' => $missing], $missing, handoff: Items::handoff($event, $occurrence, 'rallies', 'events.command.actions.recordRallyParticipation')),
        ]);
    }

    /** @return array<string, mixed> */
    private function results(Event $event, EventOccurrence $occurrence): array
    {
        $summary = $this->owners->read('operations.results', $event, $occurrence, fn (): array => $this->results->forOccurrence($occurrence));
        if ($summary === null) {
            return Items::section('results', 'events.command.sections.results', 'closeout', [$this->unknown($event, $occurrence, 'closeout.results_unavailable', 'operations.results', 'events.command.items.resultsUnavailable', 'results')]);
        }
        $exists = (bool) $summary['summaryExists'];
        $missing = (int) $summary['missingPlayerResultCount'];
        $items = [Items::make('closeout.results_summary', 'closeout', $exists ? Status::Complete : Status::NeedsAttention, $exists ? Severity::Informational : Severity::Blocking, 'operations.results', $exists ? 'events.command.items.resultsComplete' : 'events.command.items.resultsMissing', handoff: Items::handoff($event, $occurrence, 'results', 'events.command.actions.recordResults'))];
        if ($exists && $missing > 0) {
            $items[] = Items::make('closeout.player_results_missing', 'closeout', Status::Warning, Severity::Warning, 'operations.results', 'events.command.items.playerResultsMissing', ['count' => $missing], $missing, handoff: Items::handoff($event, $occurrence, 'results', 'events.command.actions.recordResults'));
        }
        if ((bool) $summary['correctionStateSupported'] === false) {
            $items[] = Items::make('closeout.corrections_unsupported', 'closeout', Status::NotApplicable, Severity::Informational, 'operations.results', 'events.command.items.correctionsUnsupported');
        }

        return Items::section('results', 'events.command.sections.results', 'closeout', $items);
    }

    /** @return array<string, mixed> */
    private function evidence(PlayerReference $actor, Event $event, EventOccurrence $occurrence): array
    {
        $summary = $this->owners->read('intelligence.evidence', $event, $occurrence, fn (): array => $this->evidence->forBearHuntOccurrence($actor->playerId, (string) $occurrence->id));
        if ($summary === null) {
            return Items::section('evidence', 'events.command.sections.evidence', 'closeout', [$this->unknown($event, $occurrence, 'closeout.evidence_unavailable', 'intelligence.evidence', 'events.command.items.evidenceUnavailable', 'results')]);
        }
        $href = '/events/'.(string) $occurrence->id.'/screenshot-intake';
        $handoff = ['href' => $href, 'labelKey' => 'events.command.actions.reviewEvidence'];
        $items = [];
        foreach ([
            ['closeout.evidence_processing', 'processingCount', 'events.command.items.evidenceProcessing'],
            ['closeout.evidence_review', 'awaitingReviewCount', 'events.command.items.evidenceAwaitingReview'],
            ['closeout.evidence_unmatched', 'unmatchedGovernorCount', 'events.command.items.evidenceUnmatched'],
            ['closeout.evidence_commit_pending', 'commitPendingCount', 'events.command.items.evidenceCommitPending'],
        ] as [$code, $key, $message]) {
            $count = (int) $summary[$key];
            if ($count > 0) {
                $items[] = Items::make($code, 'closeout', Status::NeedsAttention, Severity::Blocking, 'intelligence.evidence', $message, ['count' => $count], $count, 'evidence', $handoff);
            }
        }
        $failed = (int) $summary['commitFailedCount'] + (int) $summary['processingFailedCount'];
        if ($failed > 0) {
            $items[] = Items::make('closeout.evidence_failed', 'closeout', Status::NeedsAttention, Severity::Blocking, 'intelligence.evidence', 'events.command.items.evidenceFailed', ['count' => $failed], $failed, 'evidence', ['href' => $href, 'labelKey' => 'events.command.actions.recoverEvidence']);
        }
        if ($items === []) {
            $committed = (int) $summary['committedCount'];
            $items[] = Items::make('closeout.evidence_clear', 'closeout', Status::Complete, Severity::Informational, 'intelligence.evidence', 'events.command.items.evidenceClear', ['count' => $committed], $committed, 'evidence', $handoff);
        }

        return Items::section('evidence', 'events.command.sections.evidence', 'closeout', $items);
    }

    /** @return array<string, mixed>|null */
    private function debrief(Event $event, EventOccurrence $occurrence): ?array
    {
        $summary = $this->owners->read('readmodels.event_analysis', $event, $occurrence, fn (): array => $this->debrief->forOccurrence($occurrence));
        if ($summary === null || (bool) $summary['supported'] === false) {
            return null;
        }
        $available = (bool) $summary['available'];
        $handoff = $available && is_string($summary['href']) ? ['href' => $summary['href'], 'labelKey' => 'events.command.actions.openDebrief'] : null;

        return Items::section('debrief', 'events.command.sections.debrief', 'closeout', [Items::make('closeout.debrief', 'closeout', $available ? Status::Complete : Status::NotApplicable, Severity::Informational, 'readmodels.event_analysis', $available ? 'events.command.items.debriefAvailable' : 'events.command.items.debriefUnavailable', classification: 'derived', handoff: $handoff)]);
    }

    /** @return array<string, mixed> */
    private function unknown(Event $event, EventOccurrence $occurrence, string $code, string $owner, string $messageKey, string $anchor): array
    {
        return Items::make($code, 'closeout', Status::Unknown, Severity::Blocking, $owner, $messageKey, handoff: Items::handoff($event, $occurrence, $anchor, 'events.command.actions.openOwnerWorkflow'));
    }

    /** @param list<string> $dimensions */
    private function has(array $dimensions, EventWorkflowDimension $dimension): bool
    {
        return in_array($dimension->value, $dimensions, true);
    }

    /** @param list<string> $dimensions */
    private function bearHuntEvidenceApplicable(Event $event, array $dimensions): bool
    {
        return $event->eventType->slug === 'bear-hunt'
            && $event->scopeEnum() === EventScope::Alliance
            && $this->has($dimensions, EventWorkflowDimension::ScreenshotEvidence)
            && $this->has($dimensions, EventWorkflowDimension::Results);
    }
}
