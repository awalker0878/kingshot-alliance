<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceAssistant\Queries;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Membership\ValueObjects\AllianceScopeReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\ReadModels\AllianceAssistant\Enums\AssistantIntent;
use App\ReadModels\AllianceAssistant\Enums\AssistantStatus;
use App\ReadModels\AllianceAssistant\Enums\EvidenceClassification;
use App\ReadModels\AllianceAssistant\Enums\EvidenceSourceType;
use App\ReadModels\AllianceAssistant\ValueObjects\AssistantEvidence;
use App\ReadModels\AllianceAssistant\ValueObjects\AssistantResult;
use App\ReadModels\AllianceAssistant\ValueObjects\ParsedQuestion;
use App\ReadModels\AllianceGovernance\Queries\AllianceGovernanceTimelineQuery;
use App\ReadModels\AllianceGovernance\Queries\AllianceRosterReconciliationQuery;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class AllianceGovernanceAssistantQuery
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AllianceIntelligenceAuthorization $intelligenceAuthorization,
        private AllianceReferenceQuery $alliances,
        private AllianceGovernanceTimelineQuery $timeline,
        private AllianceRosterReconciliationQuery $reconciliation,
    ) {}

    public function ask(
        PlayerReference $actor,
        AllianceScopeReference $scope,
        ParsedQuestion $parsed,
    ): AssistantResult {
        return match ($parsed->intent) {
            AssistantIntent::AllianceSettings => $this->settings($actor, $scope),
            AssistantIntent::AllianceGovernanceHistory => $this->history($actor, $scope),
            AssistantIntent::AllianceRosterReconciliation => $this->rosterReconciliation($actor, $scope),
            default => throw new \LogicException('Unsupported Alliance governance Assistant intent.'),
        };
    }

    private function settings(PlayerReference $actor, AllianceScopeReference $scope): AssistantResult
    {
        $this->authorization->authorize($actor->playerId, $scope->allianceId, AlliancePermission::Manage);
        $alliance = $this->alliances->require($scope->allianceId);
        $statement = sprintf(
            '%s · %s · %s · %s',
            $alliance->name,
            $alliance->slug,
            $alliance->language,
            $alliance->timezone,
        );
        $evidence = new AssistantEvidence(
            'alliance-settings-'.$alliance->allianceId,
            EvidenceSourceType::AllianceSettings,
            $alliance->allianceId,
            $alliance->name,
            EvidenceClassification::OperationalFact,
            $statement,
            href: '/alliance/settings',
            metadata: [
                'name' => $alliance->name,
                'slug' => $alliance->slug,
                'language' => $alliance->language,
                'timezone' => $alliance->timezone,
                'status' => $alliance->status,
            ],
        );

        return new AssistantResult(
            AssistantIntent::AllianceSettings,
            AssistantStatus::Answered,
            'assistant.answers.allianceSettings',
            [
                'name' => $alliance->name,
                'slug' => $alliance->slug,
                'language' => $alliance->language,
                'timezone' => $alliance->timezone,
            ],
            [$evidence],
        );
    }

    private function history(PlayerReference $actor, AllianceScopeReference $scope): AssistantResult
    {
        $this->authorizeOfficer($actor->playerId, $scope->allianceId);
        $result = $this->timeline->forAlliance($scope->allianceId, limit: 10);
        $evidence = [];
        foreach ($result['items'] as $item) {
            $actorName = is_array($item['actor'] ?? null) ? ($item['actor']['name'] ?? null) : null;
            $evidence[] = new AssistantEvidence(
                'alliance-governance-'.$item['id'],
                EvidenceSourceType::AllianceGovernanceHistory,
                (string) $item['id'],
                (string) $item['type'],
                EvidenceClassification::OperationalFact,
                (string) $item['type'],
                occurredAt: isset($item['occurredAt']) ? (string) $item['occurredAt'] : null,
                href: '/alliance/history',
                metadata: [
                    'actor' => $actorName,
                    'ownerSource' => $item['source'] ?? 'audit',
                    'ownerMetadata' => $item['metadata'] ?? [],
                    'handoff' => $item['handoff'] ?? null,
                ],
            );
        }

        return new AssistantResult(
            AssistantIntent::AllianceGovernanceHistory,
            AssistantStatus::Answered,
            'assistant.answers.allianceGovernanceHistory',
            ['count' => count($evidence)],
            $evidence,
        );
    }

    private function rosterReconciliation(PlayerReference $actor, AllianceScopeReference $scope): AssistantResult
    {
        if (! $this->intelligenceAuthorization->allows(
            $actor->playerId,
            $scope->allianceId,
            IntelligencePermission::KingdomManage,
        )) {
            throw new AuthorizationException;
        }
        $result = $this->reconciliation->forAlliance($scope->allianceId);
        $batch = $result['batch'];
        if (! is_array($batch)) {
            return new AssistantResult(
                AssistantIntent::AllianceRosterReconciliation,
                AssistantStatus::NotFound,
                'assistant.answers.allianceRosterReconciliationNotAvailable',
            );
        }

        $evidence = new AssistantEvidence(
            'alliance-roster-reconciliation-'.$batch['id'],
            EvidenceSourceType::AllianceRosterReconciliation,
            (string) $batch['id'],
            'Alliance roster reconciliation',
            EvidenceClassification::Observation,
            sprintf(
                '%d need review; %d match current membership',
                (int) $result['summary']['needsReview'],
                (int) $result['summary']['matched'],
            ),
            occurredAt: isset($batch['capturedAt']) ? (string) $batch['capturedAt'] : null,
            href: '/alliance/roster/reconciliation',
            metadata: [
                'needsReview' => (int) $result['summary']['needsReview'],
                'matched' => (int) $result['summary']['matched'],
                'completeRoster' => (bool) ($batch['completeRoster'] ?? false),
                'evidenceId' => $batch['evidenceId'] ?? null,
                'reviewId' => $batch['reviewId'] ?? null,
                'reasons' => array_values(array_unique(array_merge(...array_map(
                    static fn (array $item): array => array_values((array) ($item['reasons'] ?? [])),
                    $result['items'],
                )))),
            ],
        );

        return new AssistantResult(
            AssistantIntent::AllianceRosterReconciliation,
            AssistantStatus::Answered,
            'assistant.answers.allianceRosterReconciliation',
            [
                'needsReview' => (int) $result['summary']['needsReview'],
                'matched' => (int) $result['summary']['matched'],
            ],
            [$evidence],
        );
    }

    private function authorizeOfficer(string $playerId, string $allianceId): void
    {
        if (! $this->authorization->allows($playerId, $allianceId, AlliancePermission::MembershipManage)
            && ! $this->authorization->allows($playerId, $allianceId, AlliancePermission::RoleManage)
            && ! $this->authorization->allows($playerId, $allianceId, AlliancePermission::Manage)) {
            throw new AuthorizationException;
        }
    }
}
