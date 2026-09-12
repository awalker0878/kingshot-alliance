<?php

declare(strict_types=1);

namespace Tests\ReadModels\CommandOverview\Feature;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\ValueObjects\AllianceScopeReference;
use App\Contexts\Communications\Delivery\Models\NotificationMessage;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CreateTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferVerificationPreviewQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\ReadModels\AllianceAssistant\Enums\AssistantIntent;
use App\ReadModels\AllianceAssistant\Queries\AllianceOperationalAssistantQuery;
use App\ReadModels\AllianceAssistant\ValueObjects\ParsedQuestion;
use App\ReadModels\CommandOverview\Queries\AllianceCommandQuery;
use App\ReadModels\CommandOverview\Queries\OfficerBriefQuery;
use App\Workflows\NotificationDelivery\Services\OfficerBriefNotificationPublisher;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class TransferVerificationPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_has_bounded_assessment_and_reports_complete_plan_size_and_unknown_coverage(): void
    {
        $f = $this->fixture();
        $f['participant']->update(['direction' => 'staying']);
        $this->seedParticipants($f, 66);
        $last = TransferParticipant::query()->where('transfer_plan_id', $f['plan']->id)->orderByDesc('id')->firstOrFail();
        $last->update(['readiness_state' => 'blocked']);
        $hydrated = 0;
        TransferParticipant::retrieved(static function () use (&$hydrated): void {
            $hydrated++;
        });
        $projection = app(AllianceCommandQuery::class)->for($f['user']->id, $f['actor'], $f['alliance']->allianceId);
        self::assertLessThanOrEqual(25, $hydrated, 'An overview must not assess every participant on every dashboard request.');
        $item = array_values(array_filter($projection['items'], static fn (array $item): bool => $item['code'] === 'transfer_verification'))[0];
        self::assertSame('assessment_incomplete', $item['state']);
        self::assertTrue($item['actionable']);
        self::assertSame(1, $item['count'], 'Manual blockers outside the assessed page remain counted.');
        self::assertSame([], $item['affectedIds']);
        self::assertSame(['total' => 67, 'assessed' => 25, 'unassessed' => 42, 'manualBlocked' => 1,
            'complete' => false, 'affectedIdsComplete' => false], $item['metadata']['coverage']);
    }

    public function test_complete_small_plan_uses_actual_eligibility_and_never_counts_manual_blockers_twice(): void
    {
        $f = $this->fixture();
        $this->seedParticipants($f, 2);
        $f['participant']->update(['readiness_state' => 'blocked']);
        $preview = app(TransferVerificationPreviewQuery::class)->current($f['actor']->playerId, $f['alliance']->allianceId);
        self::assertNotNull($preview);
        self::assertSame(3, $preview->total);
        self::assertSame(3, $preview->assessed);
        self::assertSame(1, $preview->knownAffected);
        self::assertSame(1, $preview->manualBlocked);
        self::assertSame([(string) $f['participant']->id], $preview->affectedIds);
        self::assertTrue($preview->coverage()['complete']);
        self::assertTrue($preview->coverage()['affectedIdsComplete']);
        // Actual missing source facts still require verification without a manual blocker.
        $f['participant']->update(['readiness_state' => 'confirmed']);
        $preview = app(TransferVerificationPreviewQuery::class)->current($f['actor']->playerId, $f['alliance']->allianceId);
        self::assertSame(1, $preview->knownAffected);
        self::assertSame(0, $preview->manualBlocked);
    }

    public function test_unassessed_clean_prefix_cannot_claim_verified_and_withdrawn_rows_are_excluded(): void
    {
        $f = $this->fixture();
        $f['participant']->update(['direction' => 'staying']);
        $this->seedParticipants($f, 30);
        TransferParticipant::query()->where('transfer_plan_id', $f['plan']->id)->orderByDesc('id')->firstOrFail()->update(['withdrawn_at' => now(), 'readiness_state' => 'blocked']);
        $preview = app(TransferVerificationPreviewQuery::class)->current($f['actor']->playerId, $f['alliance']->allianceId);
        self::assertSame(30, $preview->total);
        self::assertSame(0, $preview->knownAffected);
        self::assertSame(5, $preview->coverage()['unassessed']);
        self::assertFalse($preview->coverage()['complete']);
        self::assertFalse($preview->coverage()['affectedIdsComplete']);
        $command = app(AllianceCommandQuery::class)->for($f['user']->id, $f['actor'], $f['alliance']->allianceId);
        $transfer = array_values(array_filter($command['items'], static fn (array $item): bool => $item['code'] === 'transfer_verification'))[0];
        self::assertSame('assessment_incomplete', $transfer['state']);
        self::assertTrue($transfer['actionable']);
        self::assertSame('application.dashboard.commandReasons.transferAssessmentIncomplete', $transfer['reasonKey']);
    }

    public function test_empty_and_fully_assessed_clear_plans_are_distinct_from_missing_plans(): void
    {
        $f = $this->fixture();
        $f['participant']->update(['direction' => 'staying']);
        $q = app(TransferVerificationPreviewQuery::class);
        self::assertSame(0, $q->current($f['actor']->playerId, $f['alliance']->allianceId)->knownAffected);
        $f['participant']->delete();
        $preview = $q->current($f['actor']->playerId, $f['alliance']->allianceId);
        self::assertSame(0, $preview->total);
        self::assertSame(0, $preview->assessed);
        self::assertTrue($preview->coverage()['complete']);
        $f['plan']->update(['state' => 'closed']);
        self::assertNull($q->current($f['actor']->playerId, $f['alliance']->allianceId));
    }

    public function test_source_owner_rejects_revoked_access_before_participant_materialization(): void
    {
        $f = $this->fixture();
        AllianceMembership::query()->where('alliance_id', $f['alliance']->allianceId)->where('player_id', $f['actor']->playerId)->update(['status' => 'suspended']);
        $hydrated = 0;
        TransferParticipant::retrieved(static function () use (&$hydrated): void {
            $hydrated++;
        });
        try {
            app(TransferVerificationPreviewQuery::class)->current($f['actor']->playerId, $f['alliance']->allianceId);
            self::fail('The owner must reauthorize, not trust a previously authorized projection.');
        } catch (AuthorizationException) {
            self::assertSame(0, $hydrated);
        }
        self::assertNull(app(AllianceCommandQuery::class)->for($f['user']->id, $f['actor'], $f['alliance']->allianceId));
    }

    public function test_foreign_alliance_is_not_observed_or_counted(): void
    {
        $f = $this->fixture();
        $other = $this->fixture();
        $this->seedParticipants($other, 35);
        $preview = app(TransferVerificationPreviewQuery::class)->current($f['actor']->playerId, $f['alliance']->allianceId);
        self::assertSame(1, $preview->total);
        self::assertSame((string) $f['plan']->id, $preview->planId);
        $this->expectException(AuthorizationException::class);
        app(TransferVerificationPreviewQuery::class)->current($f['actor']->playerId, $other['alliance']->allianceId);
    }

    public function test_preview_query_is_one_bounded_model_read_and_complete_database_aggregate(): void
    {
        $f = $this->fixture();
        $this->seedParticipants($f, 66);
        $queries = [];
        DB::listen(static function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        app(TransferVerificationPreviewQuery::class)->current($f['actor']->playerId, $f['alliance']->allianceId);
        $participantReads = array_values(array_filter($queries, static fn (string $sql): bool => str_contains($sql, '"transfer_participants".*')));
        self::assertCount(1, $participantReads);
        self::assertStringContainsString('limit 25', $participantReads[0]);
        self::assertStringContainsString('count(*) over ()', $participantReads[0]);
        self::assertStringContainsString('"alliance_id" = ?', $participantReads[0]);
        self::assertStringContainsString('"transfer_plan_id" = ?', $participantReads[0]);
        self::assertStringContainsString('"withdrawn_at" is null', $participantReads[0]);
    }

    public function test_assistant_brief_and_notification_keep_unknown_assessment_coverage_explicit(): void
    {
        $f = $this->fixture();
        $f['participant']->update(['direction' => 'staying']);
        $this->seedParticipants($f, 66);
        $membership = AllianceMembership::query()->where('alliance_id', $f['alliance']->allianceId)->where('player_id', $f['actor']->playerId)->sole();
        $scope = new AllianceScopeReference($f['actor']->playerId, $f['actor']->kingdomId, $f['alliance']->allianceId, (string) $membership->id);
        $answer = app(AllianceOperationalAssistantQuery::class)->ask($f['actor'], $scope, new ParsedQuestion(AssistantIntent::TransferVerification));
        self::assertSame('assistant.answers.transferVerificationIncomplete', $answer->messageKey);
        self::assertSame(['state' => 'assessment_incomplete', 'count' => 0, 'assessed' => 25, 'total' => 67, 'unassessed' => 42], $answer->messageParameters);
        self::assertSame(42, $answer->evidence[0]->metadata['coverage']['unassessed']);
        $command = app(AllianceCommandQuery::class)->for($f['user']->id, $f['actor'], $f['alliance']->allianceId);
        $brief = app(OfficerBriefQuery::class)->for($f['actor'], $f['alliance']->allianceId, $command)[0];
        self::assertSame('needs_attention', $brief['state']);
        self::assertSame(42, $brief['assessmentCoverage']['unassessed']);
        $receipt = app(OfficerBriefNotificationPublisher::class)->publish(
            $f['user']->id, $f['actor']->playerId, $f['alliance']->allianceId, $brief);
        $message = NotificationMessage::query()->findOrFail($receipt->messageId);
        self::assertStringContainsString('42 participants are not assessed', $message->body);
        self::assertFalse($message->metadata['assessmentCoverage']['complete']);
        self::assertSame(25, $message->metadata['assessmentCoverage']['assessed']);
    }

    /** @param array{actor:PlayerReference,alliance:AllianceReference,plan:TransferPlan,participant:TransferParticipant,user:User} $f */
    private function seedParticipants(array $f, int $count): void
    {
        $factory = new ScenarioFactory;
        for ($i = 0; $i < $count; $i++) {
            $player = $factory->unclaimedPlayer(59301);
            TransferParticipant::query()->create(['alliance_id' => $f['alliance']->allianceId,
                'transfer_plan_id' => $f['plan']->id, 'player_id' => $player->playerId,
                'direction' => 'staying', 'readiness_state' => 'preparing', 'source_kingdom_id' => $player->kingdomId,
                'observed_name' => 'Participant '.str_pad((string) $i, 3, '0', STR_PAD_LEFT)]);
        }
    }

    /** @return array{actor:PlayerReference,alliance:AllianceReference,plan:TransferPlan,participant:TransferParticipant,user:User} */
    private function fixture(): array
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $user = User::query()->findOrFail($account->userId);
        $user->forceFill(['email_verified_at' => now()])->save();
        $actor = $factory->player($account->userId, 59301);
        $alliance = $factory->alliance($actor);
        $roster = $factory->roster($actor, $alliance, $actor);
        $factory->kingdom(59302);
        $window = app(SaveTransferWindow::class)->handle($alliance->allianceId, $actor->playerId, [
            'label' => 'Bounded evidence window', 'pre_transfer_starts_at' => now()->subDays(3)->toIso8601String(),
            'invitational_starts_at' => now()->subDays(2)->toIso8601String(), 'transfer_opens_at' => now()->subDay()->toIso8601String(),
            'ends_at' => now()->addDay()->toIso8601String(), 'source_type' => TransferSourceType::OfficialPublication,
            'source_reference' => 'Official window', 'observed_at' => now()->subDays(4)->toIso8601String(),
        ]);
        app(CreateTransferPlan::class)->handle($alliance->allianceId, $actor->playerId, ['label' => 'Bounded evidence plan', 'transfer_window_id' => $window]);
        $plan = TransferPlan::query()->where('alliance_id', $alliance->allianceId)->sole();
        app(SaveTransferParticipant::class)->handle($alliance->allianceId, $actor->playerId, (string) $plan->id, ['direction' => TransferDirection::Outgoing, 'roster_entry_id' => $roster->rosterEntryId, 'destination_kingdom' => 59302]);
        $participant = TransferParticipant::query()->where('transfer_plan_id', $plan->id)->sole();

        return compact('actor', 'alliance', 'plan', 'participant', 'user');
    }
}
