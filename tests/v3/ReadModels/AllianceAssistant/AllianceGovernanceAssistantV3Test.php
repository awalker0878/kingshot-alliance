<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\AllianceAssistant;

use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\ValueObjects\AllianceScopeReference;
use App\Contexts\Intelligence\Roster\Actions\RecordAllianceRosterObservationBatch;
use App\ReadModels\AllianceAssistant\Enums\AssistantIntent;
use App\ReadModels\AllianceAssistant\Enums\AssistantStatus;
use App\ReadModels\AllianceAssistant\Queries\AllianceAssistantQuery;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class AllianceGovernanceAssistantV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_assistant_answers_authorized_settings_history_and_roster_reconciliation_facts(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 59240);
        $alliance = $scenario->alliance($actor);
        $roster = $scenario->roster($actor, $alliance);
        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance->allianceId)
            ->where('player_id', $actor->playerId)
            ->firstOrFail();
        $scope = new AllianceScopeReference(
            $actor->playerId,
            $actor->kingdomId,
            $alliance->allianceId,
            (string) $membership->id,
        );
        $query = app(AllianceAssistantQuery::class);

        $settings = $query->ask($actor, $scope, 'What are our Alliance settings?');
        self::assertSame(AssistantIntent::AllianceSettings, $settings->intent);
        self::assertSame(AssistantStatus::Answered, $settings->status);
        self::assertSame('alliance_settings', $settings->evidence[0]->sourceType->value);
        self::assertSame($alliance->name, $settings->messageParameters['name']);

        AuditEvent::query()->create([
            'alliance_id' => $alliance->allianceId,
            'actor_player_id' => $actor->playerId,
            'event' => 'alliance.settings_changed',
            'subject_type' => Alliance::class,
            'subject_id' => $alliance->allianceId,
            'metadata' => ['changed_fields' => ['timezone']],
            'created_at' => now(),
        ]);
        $history = $query->ask($actor, $scope, 'What changed in Alliance governance history?');
        self::assertSame(AssistantIntent::AllianceGovernanceHistory, $history->intent);
        self::assertSame(AssistantStatus::Answered, $history->status);
        self::assertNotEmpty($history->evidence);
        self::assertSame('alliance_governance_history', $history->evidence[0]->sourceType->value);

        app(RecordAllianceRosterObservationBatch::class)->handle(
            actorPlayerId: $actor->playerId,
            allianceId: $alliance->allianceId,
            sourceEvidenceId: 'assistant-evidence',
            sourceReviewId: 'assistant-review',
            schemaVersion: 'alliance-roster-v1',
            capturedAt: now()->subMinute()->toIso8601String(),
            rows: [[
                'observed_name' => $actor->currentName,
                'game_player_id' => $actor->gamePlayerId,
                'observed_rank' => 'r5',
                'power' => 1000,
                'roster_entry_id' => $roster->rosterEntryId,
            ]],
            idempotencyKey: hash('sha256', 'assistant-roster-reconciliation'),
        );
        $reconciliation = $query->ask($actor, $scope, 'What needs review in roster reconciliation?');
        self::assertSame(AssistantIntent::AllianceRosterReconciliation, $reconciliation->intent);
        self::assertSame(AssistantStatus::Answered, $reconciliation->status);
        self::assertSame('alliance_roster_reconciliation', $reconciliation->evidence[0]->sourceType->value);
        self::assertSame(0, $reconciliation->messageParameters['needsReview']);
        self::assertSame(1, $reconciliation->messageParameters['matched']);

        $handoff = $query->ask($actor, $scope, 'Edit our Alliance settings');
        self::assertSame(AssistantIntent::ActionHandoff, $handoff->intent);
        self::assertSame(AssistantStatus::Answered, $handoff->status);
        self::assertNotNull($handoff->handoff);
        self::assertSame('/alliance/settings', $handoff->handoff->href);
        self::assertSame('alliance_settings', $handoff->evidence[0]->sourceType->value);
    }
}
