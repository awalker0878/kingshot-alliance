<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceAssistant\Queries;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Content\Queries\ContentQuery;
use App\Contexts\Alliance\Content\Services\ContentPresenter;
use App\Contexts\Alliance\Membership\ValueObjects\AllianceScopeReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Observations\Queries\AssistantObservationQuery;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Queries\EventCalendarQuery;
use App\Contexts\Operations\Rosters\Queries\EventRosterQuery;
use App\ReadModels\AllianceAssistant\Enums\AssistantIntent;
use App\ReadModels\AllianceAssistant\Enums\AssistantPrompt;
use App\ReadModels\AllianceAssistant\Enums\AssistantStatus;
use App\ReadModels\AllianceAssistant\Enums\EvidenceClassification;
use App\ReadModels\AllianceAssistant\Enums\EvidenceSourceType;
use App\ReadModels\AllianceAssistant\Services\AssistantQuestionInterpreter;
use App\ReadModels\AllianceAssistant\ValueObjects\AssistantEvidence;
use App\ReadModels\AllianceAssistant\ValueObjects\AssistantResult;
use App\ReadModels\AllianceAssistant\ValueObjects\ParsedQuestion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

final readonly class AllianceAssistantQuery
{
    public function __construct(
        private AssistantQuestionInterpreter $interpreter,
        private EventCalendarQuery $events,
        private EventRosterQuery $rosters,
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
            AssistantIntent::AllianceContent => $this->contentAnswer($actor, $scope, $parsed),
            AssistantIntent::AllianceObservation => $this->observationAnswer($scope, $parsed),
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

    private function eventAnswer(PlayerReference $actor, ParsedQuestion $parsed, bool $includeRoster): AssistantResult
    {
        $calendar = $this->events->calendar(
            $actor,
            max(0, (int) config('assistant.event_past_days', 0)),
            max(1, (int) config('assistant.event_future_days', 90)),
        );

        if ($parsed->nextEvent) {
            $occurrence = $calendar->first();
            if (! $occurrence instanceof EventOccurrence) {
                return $this->notFound($parsed->intent, 'assistant.answers.noUpcomingEvent');
            }

            return $this->resolvedEventAnswer($actor, $occurrence, $includeRoster, true);
        }

        $subject = trim((string) $parsed->subject);
        if ($subject === '') {
            return $this->notFound($parsed->intent, 'assistant.answers.eventSubjectMissing');
        }

        $matches = $this->matchEvents($calendar, $subject);
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

        return $this->resolvedEventAnswer($actor, $matches[0], $includeRoster, $parsed->includeEventTime);
    }

    private function resolvedEventAnswer(
        PlayerReference $actor,
        EventOccurrence $occurrence,
        bool $includeRoster,
        bool $includeEventTime,
    ): AssistantResult {
        $title = $this->eventTitle($occurrence);
        $startsAt = $occurrence->starts_at->toIso8601String();
        $eventEvidence = new AssistantEvidence(
            'event-'.(string) $occurrence->id,
            EvidenceSourceType::Event,
            (string) $occurrence->id,
            $title,
            EvidenceClassification::OperationalFact,
            $startsAt,
            occurredAt: $startsAt,
            updatedAt: $occurrence->updated_at?->toIso8601String(),
            href: route('events.show', ['occurrence' => (string) $occurrence->id], false),
            metadata: ['eventId' => (string) $occurrence->event_id],
        );

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

    private function contentAnswer(PlayerReference $actor, AllianceScopeReference $scope, ParsedQuestion $parsed): AssistantResult
    {
        $subject = trim((string) $parsed->subject);
        if ($subject === '') {
            return $this->notFound($parsed->intent, 'assistant.answers.contentSubjectMissing');
        }

        // Fresh Alliance read authorization is performed before member content is queried.
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

    /**
     * Reduce recurring occurrences of the same Event identity to its nearest authorized occurrence.
     * Multiple distinct matching Event identities remain ambiguous.
     *
     * @param  Collection<int, EventOccurrence>  $calendar
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

    /** @param array<string, mixed> $row */
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

    /**
     * @param  array{id:string,trackingId:string,observedName:string,observedTag:?string,power:?int,memberCount:?int,capturedAt:string,source:string}  $row
     */
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

    /** @param array<string, bool|float|int|string|null> $parameters */
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
