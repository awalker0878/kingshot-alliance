<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceAssistant\Queries;

use App\Contexts\Alliance\Membership\ValueObjects\AllianceScopeReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Events\Enums\EventOccurrenceStatus;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\ReadModels\AllianceAssistant\Enums\AssistantIntent;
use App\ReadModels\AllianceAssistant\Enums\AssistantStatus;
use App\ReadModels\AllianceAssistant\Enums\EvidenceClassification;
use App\ReadModels\AllianceAssistant\Enums\EvidenceSourceType;
use App\ReadModels\AllianceAssistant\ValueObjects\AssistantEvidence;
use App\ReadModels\AllianceAssistant\ValueObjects\AssistantResult;
use App\ReadModels\AllianceAssistant\ValueObjects\ParsedQuestion;
use App\ReadModels\CommandOverview\Queries\AllianceCommandQuery;
use App\ReadModels\EventAnalysis\Queries\BearHuntDebriefQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

/** Closed, source-backed officer intents. No free-form or mutation path exists here. */
final readonly class AllianceOperationalAssistantQuery
{
    /** @var list<AssistantIntent> */
    private const COMMAND_INTENTS = [
        AssistantIntent::AllianceCommandAttention,
        AssistantIntent::EventReadiness,
        AssistantIntent::RallyGaps,
        AssistantIntent::ProgressionFreshness,
        AssistantIntent::TransferVerification,
        AssistantIntent::TerritoryComparison,
    ];

    public function __construct(
        private AllianceCommandQuery $command,
        private EventAuthorization $eventAuthorization,
        private BearHuntDebriefQuery $bearHuntDebrief,
    ) {}

    public function ask(
        PlayerReference $actor,
        AllianceScopeReference $scope,
        ParsedQuestion $parsed,
    ): AssistantResult {
        if ($parsed->intent === AssistantIntent::BearHuntHistory) {
            return $this->bearHuntHistory($actor, $scope);
        }

        if (! in_array($parsed->intent, self::COMMAND_INTENTS, true) || $actor->userId === null) {
            throw new AuthorizationException;
        }

        $projection = $this->command->for($actor->userId, $actor, $scope->allianceId);
        if ($projection === null) {
            throw new AuthorizationException;
        }

        return match ($parsed->intent) {
            AssistantIntent::AllianceCommandAttention => $this->attention($projection),
            AssistantIntent::EventReadiness => $this->eventReadiness($projection, $parsed),
            AssistantIntent::RallyGaps => $this->rallyGaps($projection, $parsed),
            AssistantIntent::ProgressionFreshness => $this->singleItem(
                $projection,
                $parsed->intent,
                'governor_observation_freshness',
                EvidenceSourceType::RosterFreshness,
                'Governor observation freshness',
                'assistant.answers.progressionFreshness',
                'assistant.answers.progressionFreshnessNotAvailable',
            ),
            AssistantIntent::TransferVerification => $this->singleItem(
                $projection,
                $parsed->intent,
                'transfer_verification',
                EvidenceSourceType::TransferVerification,
                'Transfer verification',
                'assistant.answers.transferVerification',
                'assistant.answers.transferVerificationNotAvailable',
            ),
            AssistantIntent::TerritoryComparison => $this->singleItem(
                $projection,
                $parsed->intent,
                'territory_reconciliation',
                EvidenceSourceType::TerritoryComparison,
                'Territory comparison',
                'assistant.answers.territoryComparison',
                'assistant.answers.territoryComparisonNotAvailable',
            ),
            default => throw new AuthorizationException,
        };
    }

    /** @param array{asOf:string,actionCount:int,items:list<array<string,mixed>>} $projection */
    private function attention(array $projection): AssistantResult
    {
        $items = array_values(array_filter(
            $projection['items'],
            static fn (array $item): bool => ($item['actionable'] ?? false) === true,
        ));
        $evidenceItems = $items === [] ? $projection['items'] : $items;
        if ($evidenceItems === []) {
            return $this->notFound(
                AssistantIntent::AllianceCommandAttention,
                'assistant.answers.allianceCommandAttentionNotAvailable',
            );
        }

        $evidence = array_map(
            fn (array $item): AssistantEvidence => $this->itemEvidence(
                $item,
                EvidenceSourceType::AllianceCommand,
                'Officer overview: '.str_replace('_', ' ', (string) ($item['code'] ?? 'fact')),
            ),
            array_slice($evidenceItems, 0, 8),
        );

        return new AssistantResult(
            AssistantIntent::AllianceCommandAttention,
            AssistantStatus::Answered,
            'assistant.answers.allianceCommandAttention',
            ['count' => $projection['actionCount']],
            $evidence,
        );
    }

    /** @param array{asOf:string,actionCount:int,items:list<array<string,mixed>>} $projection */
    private function eventReadiness(array $projection, ParsedQuestion $parsed): AssistantResult
    {
        $item = $this->findItem($projection, 'next_event');
        if ($item === null || ! $this->eventMatchesSubject($item, $parsed->subject)) {
            return $this->notFound($parsed->intent, 'assistant.answers.eventReadinessNotAvailable');
        }

        return new AssistantResult(
            $parsed->intent,
            AssistantStatus::Answered,
            'assistant.answers.eventReadiness',
            [
                'state' => (string) ($item['state'] ?? 'unknown'),
                'count' => (int) ($item['count'] ?? 0),
            ],
            [$this->itemEvidence($item, EvidenceSourceType::EventReadiness, 'Verified Event readiness')],
        );
    }

    /** @param array{asOf:string,actionCount:int,items:list<array<string,mixed>>} $projection */
    private function rallyGaps(array $projection, ParsedQuestion $parsed): AssistantResult
    {
        $event = $this->findItem($projection, 'next_event');
        if ($event === null || ! $this->eventMatchesSubject($event, $parsed->subject)) {
            return $this->notFound($parsed->intent, 'assistant.answers.rallyGapsNotAvailable');
        }

        $metadata = is_array($event['metadata'] ?? null) ? $event['metadata'] : [];
        $facts = array_values(array_filter(
            is_array($metadata['facts'] ?? null) ? $metadata['facts'] : [],
            static fn (mixed $fact): bool => is_array($fact)
                && ($fact['owner'] ?? null) === 'operations.rallies',
        ));
        if ($facts === []) {
            return $this->notFound($parsed->intent, 'assistant.answers.rallyGapsNotAvailable');
        }

        $href = is_string($event['handoff']['href'] ?? null) ? $event['handoff']['href'] : '/events';
        $occurrenceId = (string) ($metadata['occurrenceId'] ?? 'next');
        $evidence = [];
        $attention = 0;
        foreach ($facts as $fact) {
            $count = (int) ($fact['count'] ?? 0);
            $state = (string) ($fact['status'] ?? 'unknown');
            if (in_array($state, ['needs_attention', 'warning', 'unknown'], true)) {
                $attention += max(1, $count);
            }
            $code = (string) ($fact['code'] ?? 'rally_fact');
            $evidence[] = new AssistantEvidence(
                'rally-readiness-'.hash('sha256', $occurrenceId.'|'.$code),
                EvidenceSourceType::EventReadiness,
                $occurrenceId.':'.$code,
                'Rally readiness: '.str_replace(['.', '_'], ' ', $code),
                EvidenceClassification::OperationalFact,
                'state='.$state.'; count='.$count,
                occurredAt: is_string($metadata['startsAt'] ?? null) ? $metadata['startsAt'] : null,
                href: $href,
                metadata: $fact,
            );
        }

        return new AssistantResult(
            $parsed->intent,
            AssistantStatus::Answered,
            'assistant.answers.rallyGaps',
            ['count' => $attention],
            $evidence,
        );
    }

    /**
     * @param array{asOf:string,actionCount:int,items:list<array<string,mixed>>} $projection
     */
    private function singleItem(
        array $projection,
        AssistantIntent $intent,
        string $code,
        EvidenceSourceType $sourceType,
        string $title,
        string $messageKey,
        string $notFoundKey,
    ): AssistantResult {
        $item = $this->findItem($projection, $code);
        if ($item === null) {
            return $this->notFound($intent, $notFoundKey);
        }

        return new AssistantResult(
            $intent,
            AssistantStatus::Answered,
            $messageKey,
            [
                'state' => (string) ($item['state'] ?? 'unknown'),
                'count' => (int) ($item['count'] ?? 0),
            ],
            [$this->itemEvidence($item, $sourceType, $title)],
        );
    }

    private function bearHuntHistory(
        PlayerReference $actor,
        AllianceScopeReference $scope,
    ): AssistantResult {
        if (! $this->eventAuthorization->allows(
            $actor->playerId,
            EventScope::Alliance,
            $scope->allianceId,
            OperationsPermission::EventAllianceView,
        )) {
            throw new AuthorizationException;
        }

        $occurrence = EventOccurrence::query()
            ->where(static fn (Builder $query) => $query
                ->where('status', EventOccurrenceStatus::Completed->value)
                ->orWhere('ends_at', '<=', now()))
            ->whereHas('event', static fn (Builder $query) => $query
                ->where('scope', EventScope::Alliance->value)
                ->where('alliance_id', $scope->allianceId)
                ->whereHas('eventType', static fn (Builder $type) => $type->where('slug', 'bear-hunt')))
            ->with(['event.eventType.workflowDimensions'])
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();
        if (! $occurrence instanceof EventOccurrence || ! $occurrence->event instanceof Event) {
            return $this->notFound(AssistantIntent::BearHuntHistory, 'assistant.answers.bearHuntHistoryNotAvailable');
        }

        $canManage = $this->eventAuthorization->allows(
            $actor->playerId,
            EventScope::Alliance,
            $scope->allianceId,
            OperationsPermission::EventAllianceManage,
        );
        $debrief = $this->bearHuntDebrief->forOccurrence($occurrence, $actor, $canManage);
        $rawRuns = is_array($debrief['runs'] ?? null) ? $debrief['runs'] : [];
        $runs = array_values(array_filter($rawRuns, 'is_array'));
        if ($runs === []) {
            return $this->notFound(AssistantIntent::BearHuntHistory, 'assistant.answers.bearHuntHistoryNotAvailable');
        }

        $evidence = [];
        foreach (array_slice($runs, 0, 12) as $run) {
            $runId = (string) ($run['occurrenceId'] ?? '');
            if ($runId === '') {
                continue;
            }
            $total = is_int($run['totalDamage'] ?? null) ? (string) $run['totalDamage'] : 'not_recorded';
            $personal = is_int($run['personalDamage'] ?? null) ? (string) $run['personalDamage'] : 'not_recorded';
            $evidence[] = new AssistantEvidence(
                'bear-hunt-run-'.$runId,
                EvidenceSourceType::BearHuntRun,
                $runId,
                'Bear Hunt run',
                EvidenceClassification::OperationalFact,
                'alliance_damage='.$total.'; personal_damage='.$personal,
                occurredAt: is_string($run['startsAt'] ?? null) ? $run['startsAt'] : null,
                href: '/events/'.$runId.'/debrief',
                metadata: $run,
            );
        }

        return new AssistantResult(
            AssistantIntent::BearHuntHistory,
            AssistantStatus::Answered,
            'assistant.answers.bearHuntHistory',
            ['count' => count($evidence)],
            $evidence,
        );
    }

    /** @param array{asOf:string,actionCount:int,items:list<array<string,mixed>>} $projection
     *  @return array<string,mixed>|null
     */
    private function findItem(array $projection, string $code): ?array
    {
        foreach ($projection['items'] as $item) {
            if (($item['code'] ?? null) === $code) {
                return $item;
            }
        }

        return null;
    }

    /** @param array<string,mixed> $item */
    private function itemEvidence(
        array $item,
        EvidenceSourceType $sourceType,
        string $title,
    ): AssistantEvidence {
        $code = (string) ($item['code'] ?? 'fact');
        $owner = (string) ($item['owner'] ?? 'unknown');
        $state = (string) ($item['state'] ?? 'unknown');
        $count = (int) ($item['count'] ?? 0);
        $href = is_string($item['handoff']['href'] ?? null) ? $item['handoff']['href'] : null;
        $metadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];

        return new AssistantEvidence(
            'alliance-command-'.hash('sha256', $owner.'|'.$code.'|'.$state.'|'.$count),
            $sourceType,
            $code,
            $title,
            EvidenceClassification::OperationalFact,
            'state='.$state.'; count='.$count,
            occurredAt: is_string($item['observedAt'] ?? null) ? $item['observedAt'] : null,
            href: $href,
            metadata: $metadata + [
                'owner' => $owner,
                'state' => $state,
                'count' => $count,
                'affectedIds' => is_array($item['affectedIds'] ?? null) ? $item['affectedIds'] : [],
            ],
        );
    }

    /** @param array<string,mixed> $item */
    private function eventMatchesSubject(array $item, ?string $subject): bool
    {
        $needle = $this->normalize((string) $subject);
        if ($needle === '' || in_array($needle, ['next', 'upcoming'], true)) {
            return true;
        }

        $metadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];
        $haystacks = [
            $this->normalize((string) ($metadata['title'] ?? '')),
            $this->normalize(str_replace('-', ' ', (string) ($metadata['canonicalKey'] ?? ''))),
            $this->normalize((string) __((string) ($metadata['nameKey'] ?? ''))),
        ];

        foreach ($haystacks as $haystack) {
            if ($haystack !== '' && ($haystack === $needle || str_contains($haystack, $needle))) {
                return true;
            }
        }

        return false;
    }

    private function notFound(AssistantIntent $intent, string $messageKey): AssistantResult
    {
        return new AssistantResult($intent, AssistantStatus::NotFound, $messageKey);
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = (string) preg_replace('/[^\pL\pN\s-]+/u', ' ', $value);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
