<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\AllianceAssistant;

use App\ReadModels\AllianceAssistant\Enums\AssistantIntent;
use App\ReadModels\AllianceAssistant\Enums\AssistantStatus;
use App\ReadModels\AllianceAssistant\Enums\EvidenceClassification;
use App\ReadModels\AllianceAssistant\Enums\EvidenceSourceType;
use App\ReadModels\AllianceAssistant\ValueObjects\AssistantEvidence;
use App\ReadModels\AllianceAssistant\ValueObjects\AssistantResult;
use Tests\v3\TestCase;

final class AllianceAssistantEvidenceV3Test extends TestCase
{
    public function test_answered_result_builds_citations_only_from_response_local_evidence(): void
    {
        $evidence = new AssistantEvidence(
            id: 'event-01',
            sourceType: EvidenceSourceType::Event,
            sourceId: '01',
            title: 'Swordland',
            classification: EvidenceClassification::OperationalFact,
            statement: '2026-08-29T20:00:00+00:00',
            occurredAt: '2026-08-29T20:00:00+00:00',
            updatedAt: '2026-08-24T12:00:00+00:00',
            href: '/events/01',
        );

        $payload = (new AssistantResult(
            AssistantIntent::EventTime,
            AssistantStatus::Answered,
            'assistant.answers.eventTime',
            ['event' => 'Swordland', 'startsAt' => '2026-08-29T20:00:00+00:00'],
            [$evidence],
        ))->toArray();

        self::assertSame(['operational_fact'], $payload['classifications']);
        self::assertCount(1, $payload['evidence']);
        self::assertCount(1, $payload['citations']);
        self::assertSame('event-01', $payload['citations'][0]['evidenceId']);
        self::assertSame($payload['evidence'][0]['sourceId'], $payload['citations'][0]['sourceId']);
        self::assertSame($payload['evidence'][0]['classification'], $payload['citations'][0]['classification']);
        self::assertArrayNotHasKey('statement', $payload['citations'][0]);
        self::assertArrayNotHasKey('metadata', $payload['citations'][0]);
    }

    public function test_non_answer_states_never_emit_citations_even_if_evidence_is_accidentally_supplied(): void
    {
        $evidence = new AssistantEvidence(
            'event-01',
            EvidenceSourceType::Event,
            '01',
            'Swordland',
            EvidenceClassification::OperationalFact,
            'private source text',
        );

        $payload = (new AssistantResult(
            AssistantIntent::EventTime,
            AssistantStatus::NotFound,
            'assistant.answers.eventNotFound',
            evidence: [$evidence],
        ))->toArray();

        self::assertSame([], $payload['citations']);
    }

    public function test_strategy_and_observation_classifications_remain_distinct_from_game_fact(): void
    {
        $strategy = new AssistantEvidence(
            'content-1',
            EvidenceSourceType::AllianceContent,
            '1',
            'Bear Hunt Guide',
            EvidenceClassification::AllianceStrategy,
            'Use the Alliance rally plan.',
        );
        $observation = new AssistantEvidence(
            'observation-1',
            EvidenceSourceType::Observation,
            '1',
            '[ABC] Opponent',
            EvidenceClassification::Observation,
            'power=1000',
        );

        $payload = (new AssistantResult(
            AssistantIntent::AllianceObservation,
            AssistantStatus::Answered,
            'assistant.answers.observationFound',
            evidence: [$strategy, $observation],
        ))->toArray();

        self::assertSame(['alliance_strategy', 'observation'], $payload['classifications']);
        self::assertNotContains('game_fact', $payload['classifications']);
    }
}
