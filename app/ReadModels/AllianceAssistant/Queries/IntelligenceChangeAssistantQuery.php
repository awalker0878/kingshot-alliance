<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceAssistant\Queries;

use App\Contexts\Alliance\Membership\ValueObjects\AllianceScopeReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\ReadModels\AllianceAssistant\Enums\AssistantIntent;
use App\ReadModels\AllianceAssistant\Enums\AssistantPrompt;
use App\ReadModels\AllianceAssistant\Enums\AssistantStatus;
use App\ReadModels\AllianceAssistant\Enums\EvidenceClassification;
use App\ReadModels\AllianceAssistant\Enums\EvidenceSourceType;
use App\ReadModels\AllianceAssistant\ValueObjects\AssistantEvidence;
use App\ReadModels\AllianceAssistant\ValueObjects\AssistantResult;
use App\ReadModels\IntelligenceSignals\Queries\IntelligenceSignalQuery;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class IntelligenceChangeAssistantQuery
{
    public function __construct(
        private AllianceIntelligenceAuthorization $authorization,
        private IntelligenceSignalQuery $signals,
    ) {}

    public function supports(string $question, ?AssistantPrompt $prompt): bool
    {
        if ($prompt instanceof AssistantPrompt) {
            return false;
        }

        $normalized = mb_strtolower(trim($question));
        if ($normalized === '') {
            return false;
        }

        $changeLanguage = preg_match(
            '/\b(changed?|changes|stale|trend|increased|decreased|expir(?:e|ed|ing|y)|reappear(?:ed)?|disappear(?:ed)?|different|difference)\b/u',
            $normalized,
        ) === 1;
        if (! $changeLanguage) {
            return false;
        }

        return preg_match(
            '/\b(alliance|intelligence|observation|observations|power|members?|progression|governor|bear hunt|transfer|recruitment|candidate|kingdom)\b/u',
            $normalized,
        ) === 1;
    }

    public function ask(
        PlayerReference $actor,
        AllianceScopeReference $scope,
        string $question,
    ): AssistantResult {
        if (! $this->authorization->allows(
            $actor->playerId,
            $scope->allianceId,
            IntelligencePermission::View,
        )) {
            throw new AuthorizationException;
        }

        $signals = $this->signals->recentForAlliance(
            $scope->allianceId,
            $actor->playerId,
            max(1, min(8, (int) config('assistant.intelligence_change_result_limit', 5))),
        );
        $signals = $this->filterForQuestion($signals, $question);

        if ($signals === []) {
            return new AssistantResult(
                AssistantIntent::IntelligenceChanges,
                AssistantStatus::NotFound,
                'assistant.answers.observationNotFound',
                ['subject' => 'recent intelligence changes'],
            );
        }

        $evidence = [];
        foreach ($signals as $signal) {
            $sourceRecordIds = is_array($signal['sourceRecordIds'] ?? null)
                ? array_values(array_filter($signal['sourceRecordIds'], 'is_string'))
                : [];
            $sourceId = $sourceRecordIds[array_key_last($sourceRecordIds)] ?? (string) $signal['fingerprint'];
            $title = $this->title($signal);
            $evidence[] = new AssistantEvidence(
                'intelligence-signal-'.(string) $signal['fingerprint'],
                EvidenceSourceType::Observation,
                $sourceId,
                $title,
                EvidenceClassification::Observation,
                (string) $signal['summary'],
                occurredAt: is_string($signal['observedAt'] ?? null) ? $signal['observedAt'] : null,
                updatedAt: is_string($signal['detectedAsOf'] ?? null) ? $signal['detectedAsOf'] : null,
                href: is_string($signal['canonicalUrl'] ?? null) ? $signal['canonicalUrl'] : null,
                metadata: [
                    'signalType' => $signal['type'] ?? null,
                    'subjectType' => $signal['subjectType'] ?? null,
                    'subjectId' => $signal['subjectId'] ?? null,
                    'metric' => $signal['metric'] ?? null,
                    'state' => $signal['state'] ?? null,
                    'materiality' => $signal['materiality'] ?? null,
                    'sourceClassification' => $signal['sourceClassification'] ?? null,
                    'sourceOwner' => $signal['sourceOwner'] ?? null,
                    'sourceRecordIds' => $sourceRecordIds,
                    'baselineObservedAt' => $signal['baselineObservedAt'] ?? null,
                    'currentValue' => $signal['currentValue'] ?? null,
                    'previousValue' => $signal['previousValue'] ?? null,
                    'delta' => $signal['delta'] ?? null,
                    'percentChange' => $signal['percentChange'] ?? null,
                    'evidenceIds' => $signal['evidenceIds'] ?? [],
                    'datasetId' => $signal['datasetId'] ?? null,
                    'datasetChecksum' => $signal['datasetChecksum'] ?? null,
                    'fingerprint' => $signal['fingerprint'] ?? null,
                    'ruleVersion' => $signal['ruleVersion'] ?? null,
                ],
            );
        }

        return new AssistantResult(
            AssistantIntent::IntelligenceChanges,
            AssistantStatus::Answered,
            'assistant.answers.observationFound',
            [
                'title' => 'Recent intelligence changes',
                'observation' => (string) $signals[0]['summary'],
                'count' => count($signals),
                'signals' => $signals,
            ],
            $evidence,
        );
    }

    /**
     * @param  list<array<string,mixed>>  $signals
     * @return list<array<string,mixed>>
     */
    private function filterForQuestion(array $signals, string $question): array
    {
        $normalized = mb_strtolower($question);
        $wantedTypes = [];
        if (str_contains($normalized, 'stale')) {
            $wantedTypes[] = 'stale_intelligence';
        }
        if (preg_match('/\b(expir(?:e|ed|ing|y)|transfer)\b/u', $normalized) === 1) {
            $wantedTypes[] = 'transfer_evidence_expiring';
        }
        if (preg_match('/\b(progression|governor)\b/u', $normalized) === 1) {
            $wantedTypes[] = 'progression_changed';
        }
        if (preg_match('/\bbear hunt|performance|trend\b/u', $normalized) === 1) {
            $wantedTypes[] = 'performance_trend';
        }
        if (preg_match('/\brecruit|candidate\b/u', $normalized) === 1) {
            $wantedTypes[] = 'recruitment_changed';
        }

        $wantedTypes = array_values(array_unique($wantedTypes));
        if ($wantedTypes === []) {
            return $signals;
        }

        return array_values(array_filter(
            $signals,
            static fn (array $signal): bool => in_array((string) ($signal['type'] ?? ''), $wantedTypes, true),
        ));
    }

    /** @param array<string,mixed> $signal */
    private function title(array $signal): string
    {
        $metric = trim(str_replace('_', ' ', (string) ($signal['metric'] ?? 'intelligence')));
        $state = trim(str_replace('_', ' ', (string) ($signal['state'] ?? 'changed')));

        return ucfirst($metric).' · '.$state;
    }
}
