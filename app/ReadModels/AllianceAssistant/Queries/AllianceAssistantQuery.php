<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceAssistant\Queries;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Content\Queries\ContentQuery;
use App\Contexts\Alliance\Content\Services\ContentPresenter;
use App\Contexts\Alliance\Membership\ValueObjects\AllianceScopeReference;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferSelfEligibilityQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\GameWorld\Progression\Enums\ProgressionFactResolution;
use App\Contexts\GameWorld\Progression\Queries\ProgressionFactQuery;
use App\Contexts\Intelligence\Observations\Queries\AssistantObservationQuery;
use App\Contexts\Operations\BattlePlans\Queries\PlayerBattlePlanQuery;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Queries\EventCalendarQuery;
use App\Contexts\Operations\Participation\Queries\EventParticipationQuery;
use App\Contexts\Operations\Rosters\Queries\EventRosterQuery;
use App\Contexts\Operations\TerritoryPlanning\Queries\PublishedEventTerritoryRevisionQuery;
use App\ReadModels\AllianceAssistant\Enums\AssistantIntent;
use App\ReadModels\AllianceAssistant\Enums\AssistantPrompt;
use App\ReadModels\AllianceAssistant\Enums\AssistantStatus;
use App\ReadModels\AllianceAssistant\Enums\EvidenceClassification;
use App\ReadModels\AllianceAssistant\Enums\EvidenceSourceType;
use App\ReadModels\AllianceAssistant\Services\AssistantQuestionInterpreter;
use App\ReadModels\AllianceAssistant\ValueObjects\AssistantEvidence;
use App\ReadModels\AllianceAssistant\ValueObjects\AssistantNavigationHandoff;
use App\ReadModels\AllianceAssistant\ValueObjects\AssistantResult;
use App\ReadModels\AllianceAssistant\ValueObjects\ParsedQuestion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

final readonly class AllianceAssistantQuery
{
    public function __construct(
        private AssistantQuestionInterpreter $interpreter,
        private EventCalendarQuery $events,
        private EventRosterQuery $rosters,
        private EventParticipationQuery $participation,
        private PlayerBattlePlanQuery $battlePlans,
        private ProgressionFactQuery $progressionFacts,
        private TransferSelfEligibilityQuery $transfers,
        private PublishedEventTerritoryRevisionQuery $territoryPlans,
        private AllianceAuthorization $allianceAuthorization,
        private ContentQuery $content,
        private ContentPresenter $contentPresenter,
        private AssistantObservationQuery $observations,
    ) {}

    public function ask(
        PlayerReference $actor,
        AllianceScopeReference $scope,
        string $question,
        ?AssistantPrompt $prompt = null,
    ): AssistantResult {
        $parsed = $this->interpreter->interpret($question, $prompt);

        return match ($parsed->intent) {
            AssistantIntent::Help => $this->help(),
            AssistantIntent::EventTime => $this->eventAnswer($actor, $parsed, false),
            AssistantIntent::EventRosterSelf => $this->eventAnswer($actor, $parsed, true),
            AssistantIntent::EventParticipationSelf => $this->participationAnswer($actor, $parsed),
            AssistantIntent::BattlePlanSelf => $this->battlePlanAnswer($actor, $parsed),
            AssistantIntent::GameFact => $this->gameFactAnswer($parsed),
            AssistantIntent::TransferStatusSelf => $this->transferAnswer($actor, $scope, $parsed),
            AssistantIntent::TerritoryPlan => $this->territoryPlanAnswer($actor, $parsed),
            AssistantIntent::AllianceContent => $this->contentAnswer($actor, $scope, $parsed),
            AssistantIntent::AllianceObservation => $this->observationAnswer($scope, $parsed),
            AssistantIntent::ActionHandoff => $this->actionHandoff($actor, $parsed),
            AssistantIntent::IntelligenceChanges,
            AssistantIntent::Unsupported => $this->unsupported(),
        };
    }

    private function help(): AssistantResult
    {
        return new AssistantResult(
            AssistantIntent::Help,
            AssistantStatus::Answered,
            'assistant.answers.help',
            suggestedQuestions: $this->suggestedQuestions(),
        );
    }

    private function unsupported(): AssistantResult
    {
        return new AssistantResult(
            AssistantIntent::Unsupported,
            AssistantStatus::Unsupported,
            'assistant.answers.unsupported',
            suggestedQuestions: $this->suggestedQuestions(),
        );
    }

    private function gameFactAnswer(ParsedQuestion $parsed): AssistantResult
    {
        if ($parsed->gameFact === null) {
            return $this->notFound(AssistantIntent::GameFact, 'assistant.answers.gameFactUnknown');
        }

        $fact = $this->progressionFacts->answer($parsed->gameFact);
        $hrefParameters = in_array($fact->family, ['heroes', 'hero_skills', 'formations'], true)
            ? ['q' => $fact->title]
            : (in_array($fact->family, ['max_levels'], true)
                ? []
                : ['family' => $fact->family, 'family_q' => $fact->title]);
        $evidence = new AssistantEvidence(
            'game-fact-'.hash('sha256', $fact->datasetId.'|'.$fact->path),
            EvidenceSourceType::GameFact,
            $fact->datasetId.':'.$fact->path,
            $fact->title,
            EvidenceClassification::GameFact,
            $fact->resolution->value,
            occurredAt: $fact->observedAt,
            href: route('progression.index', $hrefParameters, false),
            metadata: [
                'resolution' => $fact->resolution->value,
                'family' => $fact->family,
                'path' => $fact->path,
                'datasetReleaseId' => $fact->datasetId,
                'datasetVersion' => $fact->datasetVersion,
                'checksum' => $fact->checksum,
                'sourceIds' => $fact->sourceIds,
                'confidence' => $fact->confidence,
                'evidenceStatus' => $fact->evidenceStatus,
            ],
        );

        $messageKey = match ($fact->resolution) {
            ProgressionFactResolution::Known => 'assistant.answers.gameFactKnown',
            ProgressionFactResolution::Unknown => 'assistant.answers.gameFactUnknown',
            ProgressionFactResolution::Conflicting => 'assistant.answers.gameFactConflicting',
        };

        return new AssistantResult(
            AssistantIntent::GameFact,
            AssistantStatus::Answered,
            $messageKey,
            [
                'title' => $fact->title,
                'resolution' => $fact->resolution->value,
                'values' => $fact->values,
                'datasetVersion' => $fact->datasetVersion,
                'evidenceStatus' => $fact->evidenceStatus,
            ],
            [$evidence],
        );
    }

    private function eventAnswer(PlayerReference $actor, ParsedQuestion $parsed, bool $includeRoster): AssistantResult
    {
        if ($parsed->nextEvent) {
            $calendar = $this->calendar($actor);
            $occurrence = $calendar->first();
            if (! $occurrence instanceof EventOccurrence) {
                return $this->notFound($parsed->intent, 'assistant.answers.noUpcomingEvent');
            }

            return $this->resolvedEventAnswer($actor, $occurrence, $includeRoster, true);
        }

        $resolved = $this->resolveOccurrence($actor, $parsed);
        if ($resolved instanceof AssistantResult) {
            return $resolved;
        }

        return $this->resolvedEventAnswer($actor, $resolved, $includeRoster, $parsed->includeEventTime);
    }

    private function resolvedEventAnswer(
        PlayerReference $actor,
        EventOccurrence $occurrence,
        bool $includeRoster,
        bool $includeEventTime,
    ): AssistantResult {
        $title = $this->eventTitle($occurrence);
        $startsAt = $occurrence->starts_at->toIso8601String();
        $eventEvidence = $this->eventEvidence($occurrence);

        if (! $includeRoster) {
            return new AssistantResult(
                AssistantIntent::EventTime,
                AssistantStatus::Answered,
                'assistant.answers.eventTime',
                ['event' => $title, 'startsAt' => $startsAt],
                [$eventEvidence],
                suggestedQuestions: [AssistantPrompt::SwordlandRoster->value],
            );
        }

        $rows = $this->rosters->forPlayer($occurrence, $actor);
        if ($rows === []) {
            return new AssistantResult(
                AssistantIntent::EventRosterSelf,
                AssistantStatus::Answered,
                $includeEventTime ? 'assistant.answers.eventTimeNotRostered' : 'assistant.answers.notRostered',
                ['event' => $title, 'startsAt' => $startsAt],
                [$eventEvidence],
            );
        }

        $row = $rows[0];
        $rosterName = $this->rosterName($row);
        $role = $this->stringOrNull($row['role'] ?? null);
        $slot = is_int($row['slotNumber'] ?? null) ? (int) $row['slotNumber'] : null;
        $status = $this->stringOrNull($row['status'] ?? null) ?? 'assigned';
        $rosterEvidence = new AssistantEvidence(
            'roster-'.(string) ($row['id'] ?? $row['rosterId'] ?? $occurrence->id),
            EvidenceSourceType::Roster,
            (string) ($row['id'] ?? $row['rosterId'] ?? $occurrence->id),
            $rosterName,
            EvidenceClassification::OperationalFact,
            $status,
            updatedAt: $this->stringOrNull($row['respondedAt'] ?? null),
            href: route('events.show', ['occurrence' => (string) $occurrence->id], false),
            metadata: [
                'rosterId' => (string) ($row['rosterId'] ?? ''),
                'role' => $role,
                'slot' => $slot,
                'status' => $status,
            ],
        );

        return new AssistantResult(
            AssistantIntent::EventRosterSelf,
            AssistantStatus::Answered,
            $includeEventTime ? 'assistant.answers.eventTimeRostered' : 'assistant.answers.rostered',
            [
                'event' => $title,
                'startsAt' => $startsAt,
                'roster' => $rosterName,
                'role' => $role,
                'slot' => $slot,
                'status' => $status,
            ],
            [$eventEvidence, $rosterEvidence],
        );
    }

    private function participationAnswer(PlayerReference $actor, ParsedQuestion $parsed): AssistantResult
    {
        if (trim((string) $parsed->subject) !== '') {
            $resolved = $this->resolveOccurrence($actor, $parsed);
            if ($resolved instanceof AssistantResult) {
                return $resolved;
            }

            $row = $this->participation->forPlayer($resolved, $actor);
            $evidence = [$this->eventEvidence($resolved), ...$this->participationEvidence($resolved, $row)];
            $registration = is_array($row['registration'] ?? null) ? $row['registration'] : null;
            $response = is_array($row['response'] ?? null) ? $row['response'] : null;

            return new AssistantResult(
                AssistantIntent::EventParticipationSelf,
                AssistantStatus::Answered,
                $registration === null && $response === null
                    ? 'assistant.answers.participationNone'
                    : 'assistant.answers.participationFound',
                [
                    'event' => $this->eventTitle($resolved),
                    'startsAt' => $resolved->starts_at->toIso8601String(),
                    'response' => $response['response'] ?? null,
                    'registration' => $registration['status'] ?? null,
                    'waitlistPosition' => $registration['waitlistPosition'] ?? null,
                ],
                $evidence,
            );
        }

        $calendar = $parsed->thisWeek
            ? $this->events->calendar($actor, 7, 7)
            : $this->calendar($actor);
        if ($parsed->thisWeek) {
            $start = CarbonImmutable::now('UTC')->startOfWeek();
            $end = $start->endOfWeek();
            $calendar = $calendar->filter(static function (EventOccurrence $occurrence) use ($start, $end): bool {
                $startsAt = CarbonImmutable::instance($occurrence->starts_at);

                return $startsAt->greaterThanOrEqualTo($start) && $startsAt->lessThanOrEqualTo($end);
            })->values();
        }

        /** @var list<string> $occurrenceIds */
        $occurrenceIds = array_values($calendar
            ->map(static fn (EventOccurrence $occurrence): string => (string) $occurrence->id)
            ->all());
        $rows = $this->participation->forPlayerOccurrences($occurrenceIds, $actor);
        $items = [];
        /** @var list<AssistantEvidence> $evidence */
        $evidence = [];
        foreach ($calendar as $occurrence) {
            $row = $rows[(string) $occurrence->id] ?? null;
            if (! is_array($row) || ! $this->participationMatchesMode($row, $parsed->participationMode)) {
                continue;
            }
            $registration = is_array($row['registration'] ?? null) ? $row['registration'] : null;
            $response = is_array($row['response'] ?? null) ? $row['response'] : null;
            $items[] = [
                'event' => $this->eventTitle($occurrence),
                'startsAt' => $occurrence->starts_at->toIso8601String(),
                'response' => $response['response'] ?? null,
                'registration' => $registration['status'] ?? null,
                'waitlistPosition' => $registration['waitlistPosition'] ?? null,
            ];
            $evidence[] = $this->eventEvidence($occurrence);
            array_push($evidence, ...$this->participationEvidence($occurrence, $row));
            if (count($items) >= 10) {
                break;
            }
        }

        if ($items === []) {
            return $this->notFound(
                AssistantIntent::EventParticipationSelf,
                $parsed->thisWeek ? 'assistant.answers.participationWeekNone' : 'assistant.answers.participationNotFound',
            );
        }

        return new AssistantResult(
            AssistantIntent::EventParticipationSelf,
            AssistantStatus::Answered,
            $parsed->thisWeek ? 'assistant.answers.participationWeek' : 'assistant.answers.participationList',
            ['items' => $items, 'count' => count($items)],
            $this->uniqueEvidence($evidence),
        );
    }

    private function battlePlanAnswer(PlayerReference $actor, ParsedQuestion $parsed): AssistantResult
    {
        $subject = trim((string) $parsed->subject);
        if ($subject !== '') {
            $resolved = $this->resolveOccurrence($actor, $parsed);
            if ($resolved instanceof AssistantResult) {
                return $resolved;
            }

            return $this->resolvedBattlePlanAnswer($actor, $resolved);
        }

        foreach ($this->calendar($actor)->take(50) as $occurrence) {
            if (! $occurrence instanceof EventOccurrence) {
                continue;
            }
            $rows = $this->battlePlans->forPlayer($occurrence, $actor);
            if ($rows !== []) {
                return $this->resolvedBattlePlanAnswer($actor, $occurrence, $rows);
            }
        }

        return $this->notFound(AssistantIntent::BattlePlanSelf, 'assistant.answers.battlePlanNotFound');
    }

    /** @param list<array<string,mixed>>|null $rows */
    private function resolvedBattlePlanAnswer(
        PlayerReference $actor,
        EventOccurrence $occurrence,
        ?array $rows = null,
    ): AssistantResult {
        $rows ??= $this->battlePlans->forPlayer($occurrence, $actor);
        if ($rows === []) {
            return new AssistantResult(
                AssistantIntent::BattlePlanSelf,
                AssistantStatus::Answered,
                'assistant.answers.battlePlanNone',
                ['event' => $this->eventTitle($occurrence)],
                [$this->eventEvidence($occurrence)],
            );
        }

        $items = [];
        /** @var list<AssistantEvidence> $evidence */
        $evidence = [$this->eventEvidence($occurrence)];
        foreach (array_slice($rows, 0, 10) as $row) {
            $assignmentId = (string) ($row['assignmentId'] ?? '');
            $objectiveName = (string) ($row['objectiveName'] ?? 'Objective');
            $items[] = [
                'objective' => $objectiveName,
                'type' => $row['objectiveType'] ?? null,
                'status' => $row['objectiveStatus'] ?? null,
                'team' => $row['rosterName'] ?? null,
                'scope' => $row['assignmentScope'] ?? null,
                'notes' => $row['notes'] ?? null,
            ];
            $evidence[] = new AssistantEvidence(
                'battle-assignment-'.$assignmentId,
                EvidenceSourceType::BattlePlanAssignment,
                $assignmentId,
                $objectiveName,
                EvidenceClassification::OperationalFact,
                (string) ($row['objectiveStatus'] ?? 'assigned'),
                occurredAt: $this->stringOrNull($row['assignedAt'] ?? null),
                href: route('events.show', ['occurrence' => (string) $occurrence->id], false),
                metadata: [
                    'objectiveId' => $row['objectiveId'] ?? null,
                    'objectiveType' => $row['objectiveType'] ?? null,
                    'objectiveStatus' => $row['objectiveStatus'] ?? null,
                    'rosterId' => $row['rosterId'] ?? null,
                    'rosterName' => $row['rosterName'] ?? null,
                    'assignmentScope' => $row['assignmentScope'] ?? null,
                ],
            );
        }

        return new AssistantResult(
            AssistantIntent::BattlePlanSelf,
            AssistantStatus::Answered,
            count($items) === 1 ? 'assistant.answers.battlePlanOne' : 'assistant.answers.battlePlanMany',
            ['event' => $this->eventTitle($occurrence), 'items' => $items, 'count' => count($items)],
            $evidence,
        );
    }

    private function transferAnswer(
        PlayerReference $actor,
        AllianceScopeReference $scope,
        ParsedQuestion $parsed,
    ): AssistantResult {
        $assessment = $this->transfers->forPlayer(
            $actor->playerId,
            $scope->allianceId,
            $parsed->kingdomNumber,
        );
        if (! is_array($assessment)) {
            return $this->notFound(AssistantIntent::TransferStatusSelf, 'assistant.answers.transferNotInScope');
        }

        $requirements = is_array($assessment['requirements'] ?? null) ? $assessment['requirements'] : [];
        $unmet = array_values(array_filter(
            $requirements,
            static fn (mixed $row): bool => is_array($row)
                && in_array((string) ($row['state'] ?? ''), ['unmet', 'unknown', 'conflicting'], true),
        ));
        $evidence = new AssistantEvidence(
            'transfer-assessment-'.(string) ($assessment['participantId'] ?? ''),
            EvidenceSourceType::TransferAssessment,
            (string) ($assessment['participantId'] ?? ''),
            $assessment['targetKingdomNumber'] === null
                ? 'Transfer readiness'
                : 'Kingdom '.(string) $assessment['targetKingdomNumber'],
            EvidenceClassification::OperationalFact,
            (string) ($assessment['outcome'] ?? 'needs_verification'),
            occurredAt: $this->stringOrNull($assessment['evaluatedAt'] ?? null),
            href: route('alliance.transfers.readiness', [], false),
            metadata: [
                'planId' => $assessment['planId'] ?? null,
                'windowId' => $assessment['windowId'] ?? null,
                'direction' => $assessment['direction'] ?? null,
                'readinessState' => $assessment['readinessState'] ?? null,
                'targetKingdomId' => $assessment['targetKingdomId'] ?? null,
                'targetKingdomNumber' => $assessment['targetKingdomNumber'] ?? null,
                'outcome' => $assessment['outcome'] ?? null,
                'requirements' => $requirements,
                'sourceReferences' => $assessment['sourceReferences'] ?? [],
            ],
        );

        return new AssistantResult(
            AssistantIntent::TransferStatusSelf,
            AssistantStatus::Answered,
            'assistant.answers.transferStatus',
            [
                'outcome' => $assessment['outcome'] ?? 'needs_verification',
                'targetKingdomNumber' => $assessment['targetKingdomNumber'] ?? null,
                'requirements' => $requirements,
                'unmet' => $unmet,
                'primaryAction' => $assessment['primaryAction'] ?? null,
            ],
            [$evidence],
        );
    }

    private function territoryPlanAnswer(PlayerReference $actor, ParsedQuestion $parsed): AssistantResult
    {
        $resolved = $this->resolveOccurrence($actor, $parsed);
        if ($resolved instanceof AssistantResult) {
            return $resolved;
        }

        $rows = $this->territoryPlans->forOccurrence($actor->playerId, $resolved);
        if ($rows === []) {
            return $this->notFound(
                AssistantIntent::TerritoryPlan,
                'assistant.answers.territoryPlanNotFound',
                ['event' => $this->eventTitle($resolved)],
            );
        }
        if (count($rows) > 1) {
            return new AssistantResult(
                AssistantIntent::TerritoryPlan,
                AssistantStatus::Ambiguous,
                'assistant.answers.territoryPlanAmbiguous',
                ['event' => $this->eventTitle($resolved)],
                ambiguity: array_map(static fn (array $row): array => [
                    'planName' => $row['planName'] ?? 'Territory plan',
                    'revisionNumber' => $row['revisionNumber'] ?? null,
                    'purpose' => $row['purpose'] ?? null,
                    'revisionId' => $row['revisionId'] ?? null,
                ], array_slice($rows, 0, 5)),
            );
        }

        $row = $rows[0];
        $planId = (string) ($row['planId'] ?? '');
        $revisionId = (string) ($row['revisionId'] ?? '');
        $evidence = new AssistantEvidence(
            'territory-revision-'.$revisionId,
            EvidenceSourceType::TerritoryPlanRevision,
            $revisionId,
            (string) ($row['planName'] ?? 'Territory plan'),
            EvidenceClassification::AllianceStrategy,
            'revision '.(string) ($row['revisionNumber'] ?? ''),
            occurredAt: $this->stringOrNull($row['publishedAt'] ?? null),
            href: route('territory.show', ['plan' => $planId], false),
            metadata: [
                'attachmentId' => $row['attachmentId'] ?? null,
                'purpose' => $row['purpose'] ?? null,
                'planId' => $planId,
                'revisionId' => $revisionId,
                'revisionNumber' => $row['revisionNumber'] ?? null,
                'mapDatasetId' => $row['mapDatasetId'] ?? null,
                'mapDatasetChecksum' => $row['mapDatasetChecksum'] ?? null,
            ],
        );

        return new AssistantResult(
            AssistantIntent::TerritoryPlan,
            AssistantStatus::Answered,
            'assistant.answers.territoryPlanFound',
            [
                'event' => $this->eventTitle($resolved),
                'planName' => $row['planName'] ?? 'Territory plan',
                'revisionNumber' => $row['revisionNumber'] ?? null,
                'purpose' => $row['purpose'] ?? null,
                'publishedAt' => $row['publishedAt'] ?? null,
            ],
            [$this->eventEvidence($resolved), $evidence],
        );
    }

    private function actionHandoff(PlayerReference $actor, ParsedQuestion $parsed): AssistantResult
    {
        if ($parsed->writeAction !== 'roster') {
            return $this->unsupported();
        }

        $resolved = $this->resolveOccurrence($actor, $parsed);
        if ($resolved instanceof AssistantResult) {
            return $resolved;
        }

        return new AssistantResult(
            AssistantIntent::ActionHandoff,
            AssistantStatus::Answered,
            'assistant.answers.rosterWriteHandoff',
            ['event' => $this->eventTitle($resolved)],
            [$this->eventEvidence($resolved)],
            handoff: new AssistantNavigationHandoff(
                'assistant.handoffs.openRoster',
                route('events.show', ['occurrence' => (string) $resolved->id], false),
            ),
        );
    }

    private function contentAnswer(PlayerReference $actor, AllianceScopeReference $scope, ParsedQuestion $parsed): AssistantResult
    {
        $subject = trim((string) $parsed->subject);
        if ($subject === '') {
            return $this->notFound($parsed->intent, 'assistant.answers.contentSubjectMissing');
        }

        $this->allianceAuthorization->authorize($actor->playerId, $scope->allianceId, AlliancePermission::View);
        $item = $this->content->memberList($scope->allianceId, $subject)->first();

        if (! $item instanceof ContentItem) {
            return $this->notFound($parsed->intent, 'assistant.answers.contentNotFound', ['subject' => $subject]);
        }

        $presented = $this->contentPresenter->item($item);
        $excerpt = $this->contentExcerpt($item);
        $provenance = is_array($presented['provenance'] ?? null) ? $presented['provenance'] : [];
        $freshness = is_array($presented['freshness'] ?? null) ? $presented['freshness'] : [];
        $evidence = new AssistantEvidence(
            'content-'.(string) $item->id,
            EvidenceSourceType::AllianceContent,
            (string) $item->id,
            (string) $item->title,
            EvidenceClassification::AllianceStrategy,
            $excerpt,
            occurredAt: $item->published_at?->toIso8601String(),
            updatedAt: $item->updated_at?->toIso8601String(),
            href: route('alliance.content.show', ['contentSlug' => (string) $item->slug], false),
            metadata: [
                'revisionNumber' => (int) $item->current_revision_number,
                'sourceLabel' => $this->stringOrNull($provenance['sourceLabel'] ?? null),
                'gameVersion' => $this->stringOrNull($provenance['gameVersion'] ?? null),
                'reviewedAt' => $this->stringOrNull($provenance['reviewedAt'] ?? null),
                'freshness' => $this->stringOrNull($freshness['status'] ?? null),
            ],
        );

        return new AssistantResult(
            AssistantIntent::AllianceContent,
            AssistantStatus::Answered,
            'assistant.answers.contentFound',
            ['title' => $evidence->title, 'excerpt' => $evidence->statement],
            [$evidence],
        );
    }

    private function observationAnswer(AllianceScopeReference $scope, ParsedQuestion $parsed): AssistantResult
    {
        $subject = trim((string) $parsed->subject);
        if ($subject === '') {
            return $this->notFound($parsed->intent, 'assistant.answers.observationSubjectMissing');
        }

        $rows = $this->observations->search(
            $scope->playerId,
            $scope->allianceId,
            $subject,
            max(1, min(10, (int) config('assistant.observation_result_limit', 5))),
        );

        $row = $rows[0] ?? null;
        if (! is_array($row)) {
            return $this->notFound($parsed->intent, 'assistant.answers.observationNotFound', ['subject' => $subject]);
        }

        $display = $row['observedTag'] === null || $row['observedTag'] === ''
            ? $row['observedName']
            : '['.$row['observedTag'].'] '.$row['observedName'];
        $evidence = new AssistantEvidence(
            'observation-'.$row['id'],
            EvidenceSourceType::Observation,
            $row['id'],
            $display,
            EvidenceClassification::Observation,
            $this->observationStatement($row),
            occurredAt: $row['capturedAt'],
            href: route('alliance.kingdom-alliances.history', ['tracking' => $row['trackingId']], false),
            metadata: [
                'power' => $row['power'],
                'memberCount' => $row['memberCount'],
                'source' => $row['source'],
            ],
        );

        return new AssistantResult(
            AssistantIntent::AllianceObservation,
            AssistantStatus::Answered,
            'assistant.answers.observationFound',
            ['title' => $evidence->title, 'observation' => $evidence->statement],
            [$evidence],
        );
    }

    private function resolveOccurrence(PlayerReference $actor, ParsedQuestion $parsed): EventOccurrence|AssistantResult
    {
        $subject = trim((string) $parsed->subject);
        if ($subject === '') {
            return $this->notFound($parsed->intent, 'assistant.answers.eventSubjectMissing');
        }

        $matches = $this->matchEvents($this->calendar($actor), $subject);
        if ($matches === []) {
            return $this->notFound($parsed->intent, 'assistant.answers.eventNotFound', ['subject' => $subject]);
        }
        if (count($matches) > 1) {
            return new AssistantResult(
                $parsed->intent,
                AssistantStatus::Ambiguous,
                'assistant.answers.eventAmbiguous',
                ['subject' => $subject],
                ambiguity: array_map(fn (EventOccurrence $occurrence): array => [
                    'title' => $this->eventTitle($occurrence),
                    'startsAt' => $occurrence->starts_at->toIso8601String(),
                    'occurrenceId' => (string) $occurrence->id,
                ], array_slice($matches, 0, 5)),
            );
        }

        return $matches[0];
    }

    /** @return Collection<int,EventOccurrence> */
    private function calendar(PlayerReference $actor): Collection
    {
        return $this->events->calendar(
            $actor,
            max(0, (int) config('assistant.event_past_days', 0)),
            max(1, (int) config('assistant.event_future_days', 90)),
        );
    }

    private function eventEvidence(EventOccurrence $occurrence): AssistantEvidence
    {
        $startsAt = $occurrence->starts_at->toIso8601String();

        return new AssistantEvidence(
            'event-'.(string) $occurrence->id,
            EvidenceSourceType::Event,
            (string) $occurrence->id,
            $this->eventTitle($occurrence),
            EvidenceClassification::OperationalFact,
            $startsAt,
            occurredAt: $startsAt,
            updatedAt: $occurrence->updated_at?->toIso8601String(),
            href: route('events.show', ['occurrence' => (string) $occurrence->id], false),
            metadata: ['eventId' => (string) $occurrence->event_id],
        );
    }

    /**
     * @param  array{response:?array<string,mixed>,registration:?array<string,mixed>,attendance:?array<string,mixed>}  $row
     * @return list<AssistantEvidence>
     */
    private function participationEvidence(EventOccurrence $occurrence, array $row): array
    {
        $evidence = [];
        $href = route('events.show', ['occurrence' => (string) $occurrence->id], false);
        $response = is_array($row['response'] ?? null) ? $row['response'] : null;
        if ($response !== null && is_string($response['id'] ?? null)) {
            $evidence[] = new AssistantEvidence(
                'participation-response-'.$response['id'],
                EvidenceSourceType::Participation,
                $response['id'],
                $this->eventTitle($occurrence).' RSVP',
                EvidenceClassification::OperationalFact,
                (string) ($response['response'] ?? 'response'),
                updatedAt: $this->stringOrNull($response['updatedAt'] ?? null),
                href: $href,
                metadata: [
                    'kind' => 'response',
                    'response' => $response['response'] ?? null,
                    'preferredRole' => $response['preferredRole'] ?? null,
                    'preferredTeam' => $response['preferredTeam'] ?? null,
                ],
            );
        }
        $registration = is_array($row['registration'] ?? null) ? $row['registration'] : null;
        if ($registration !== null && is_string($registration['id'] ?? null)) {
            $evidence[] = new AssistantEvidence(
                'participation-registration-'.$registration['id'],
                EvidenceSourceType::Participation,
                $registration['id'],
                $this->eventTitle($occurrence).' registration',
                EvidenceClassification::OperationalFact,
                (string) ($registration['status'] ?? 'registered'),
                occurredAt: $this->stringOrNull($registration['registeredAt'] ?? null),
                updatedAt: $this->stringOrNull($registration['updatedAt'] ?? null),
                href: $href,
                metadata: [
                    'kind' => 'registration',
                    'status' => $registration['status'] ?? null,
                    'waitlistPosition' => $registration['waitlistPosition'] ?? null,
                ],
            );
        }

        return $evidence;
    }

    /** @param array<string,mixed> $row */
    private function participationMatchesMode(array $row, ?string $mode): bool
    {
        $response = is_array($row['response'] ?? null) ? $row['response'] : null;
        $registration = is_array($row['registration'] ?? null) ? $row['registration'] : null;

        return match ($mode) {
            'waitlist' => $registration !== null
                && ($registration['waitlistPosition'] ?? null) !== null,
            'registration' => $registration !== null,
            'rsvp', null => $response !== null || $registration !== null,
            default => false,
        };
    }

    /**
     * @param  list<AssistantEvidence>  $evidence
     * @return list<AssistantEvidence>
     */
    private function uniqueEvidence(array $evidence): array
    {
        $unique = [];
        foreach ($evidence as $item) {
            $unique[$item->id] = $item;
        }

        return array_values($unique);
    }

    /**
     * @param  Collection<int,EventOccurrence>  $calendar
     * @return list<EventOccurrence>
     */
    private function matchEvents(Collection $calendar, string $subject): array
    {
        $needle = $this->normalize($subject);
        $byEvent = [];

        foreach ($calendar as $occurrence) {
            $event = $occurrence->event;
            if (! $event instanceof Event || ! $this->eventMatches($event, $needle)) {
                continue;
            }

            $eventId = (string) $occurrence->event_id;
            if (! isset($byEvent[$eventId])) {
                $byEvent[$eventId] = $occurrence;
            }
        }

        return array_values($byEvent);
    }

    private function eventMatches(Event $event, string $needle): bool
    {
        if ($needle === '') {
            return false;
        }

        foreach ($this->eventAliases($event) as $alias) {
            if ($alias === $needle || str_contains($alias, $needle) || str_contains($needle, $alias)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function eventAliases(Event $event): array
    {
        $aliases = [];
        $title = $this->normalize((string) $event->title);
        if ($title !== '') {
            $aliases[] = $title;
        }

        $slug = $this->normalize(str_replace('-', ' ', (string) $event->eventType->slug));
        if ($slug !== '') {
            $aliases[] = $slug;
        }

        $translated = $this->normalize((string) __((string) $event->eventType->name_key));
        if ($translated !== '' && $translated !== $this->normalize((string) $event->eventType->name_key)) {
            $aliases[] = $translated;
        }

        return array_values(array_unique($aliases));
    }

    private function eventTitle(EventOccurrence $occurrence): string
    {
        $event = $occurrence->event;
        $title = trim((string) $event->title);
        if ($title !== '') {
            return $title;
        }

        $translated = (string) __((string) $event->eventType->name_key);
        if ($translated !== (string) $event->eventType->name_key) {
            return $translated;
        }

        return Str::headline((string) $event->eventType->slug);
    }

    /** @param array<string,mixed> $row */
    private function rosterName(array $row): string
    {
        $name = $this->stringOrNull($row['rosterName'] ?? null);
        if ($name !== null) {
            return $name;
        }

        $nameKey = $this->stringOrNull($row['rosterNameKey'] ?? null);
        if ($nameKey !== null) {
            $translated = (string) __($nameKey);
            if ($translated !== $nameKey) {
                return $translated;
            }
        }

        return $this->stringOrNull($row['rosterKey'] ?? null) ?? 'Roster';
    }

    private function contentExcerpt(ContentItem $item): string
    {
        $value = trim((string) ($item->summary ?? ''));
        if ($value === '') {
            $value = trim(strip_tags((string) $item->body));
        }

        return Str::limit(preg_replace('/\s+/u', ' ', $value) ?? $value, 320, '…');
    }

    /** @param array{id:string,trackingId:string,observedName:string,observedTag:?string,power:?int,memberCount:?int,capturedAt:string,source:string} $row */
    private function observationStatement(array $row): string
    {
        $parts = [];
        if ($row['power'] !== null) {
            $parts[] = 'power='.$row['power'];
        }
        if ($row['memberCount'] !== null) {
            $parts[] = 'members='.$row['memberCount'];
        }
        $parts[] = 'captured='.$row['capturedAt'];
        $parts[] = 'source='.$row['source'];

        return implode('; ', $parts);
    }

    /** @param array<string,mixed> $parameters */
    private function notFound(AssistantIntent $intent, string $messageKey, array $parameters = []): AssistantResult
    {
        return new AssistantResult(
            $intent,
            AssistantStatus::NotFound,
            $messageKey,
            $parameters,
            suggestedQuestions: $this->suggestedQuestions(),
        );
    }

    /** @return list<string> */
    private function suggestedQuestions(): array
    {
        return array_map(
            static fn (AssistantPrompt $prompt): string => $prompt->value,
            AssistantPrompt::cases(),
        );
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = (string) preg_replace('/[^\pL\pN\s-]+/u', ' ', $value);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
