<?php

declare(strict_types=1);

namespace Tests\Contexts\GameWorld\KingdomTransfers\Feature;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\ValueObjects\RosterEntryReference;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CreateTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferEligibilityOutcome;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferObservationKind;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomConditionObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferEligibilityQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferSelfEligibilityQuery;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferRequirement;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class TransferSelfEligibilityQueryV3Test extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = CarbonImmutable::parse('2026-08-24T16:00:00Z');
        CarbonImmutable::setTestNow($this->now);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_projection_evaluates_only_active_governors_participant_and_preserves_needs_verification(): void
    {
        $scenario = $this->outgoingScenario(64501, 64502);
        $factory = app(ScenarioFactory::class);
        $otherAccount = $factory->account();
        $other = $factory->player($otherAccount->userId, 64501, 'TRANSFER-OTHER');
        $otherRoster = $factory->roster($scenario['actor'], $scenario['alliance'], $other);
        app(SaveTransferParticipant::class)->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            (string) $scenario['plan']->id,
            [
                'direction' => TransferDirection::Outgoing,
                'roster_entry_id' => $otherRoster->rosterEntryId,
                'destination_kingdom' => 64502,
            ],
        );
        $otherParticipant = TransferParticipant::query()
            ->where('transfer_plan_id', $scenario['plan']->id)
            ->where('player_id', $other->playerId)
            ->firstOrFail();

        $result = app(TransferSelfEligibilityQuery::class)->forPlayer(
            $scenario['actor']->playerId,
            $scenario['alliance']->allianceId,
        );

        self::assertNotNull($result);
        self::assertSame((string) $scenario['participant']->id, $result['participantId'] ?? null);
        self::assertSame(TransferEligibilityOutcome::NeedsVerification->value, $result['outcome'] ?? null);
        self::assertNotSame(TransferEligibilityOutcome::EligibleNow->value, $result['outcome'] ?? null);
        self::assertStringNotContainsString(
            (string) $otherParticipant->id,
            json_encode($result, JSON_THROW_ON_ERROR),
        );
    }

    public function test_requested_kingdom_number_only_constrains_existing_authorized_target(): void
    {
        $scenario = $this->outgoingScenario(64511, 64512);
        app(ScenarioFactory::class)->kingdom(64513);

        self::assertNotNull(app(TransferSelfEligibilityQuery::class)->forPlayer(
            $scenario['actor']->playerId,
            $scenario['alliance']->allianceId,
            64512,
        ));
        self::assertNull(app(TransferSelfEligibilityQuery::class)->forPlayer(
            $scenario['actor']->playerId,
            $scenario['alliance']->allianceId,
            64513,
        ));
    }

    public function test_foreign_governor_cannot_use_another_alliances_transfer_plan_as_scope(): void
    {
        $scenario = $this->outgoingScenario(64521, 64522);
        $foreign = $this->ownerScenario(64523);

        self::assertNull(app(TransferSelfEligibilityQuery::class)->forPlayer(
            $foreign['actor']->playerId,
            $scenario['alliance']->allianceId,
        ));
    }

    public function test_self_projection_uses_bounded_canonical_evidence_and_keeps_the_complete_observation_count(): void
    {
        $f = $this->outgoingScenario(64531, 64532);
        for ($i = 0; $i < 180; $i++) {
            $this->observation($f, ['numeric_value' => 100 + $i, 'valid_until' => $this->now->subDay()]);
            $this->condition($f, ['observed_at' => $this->now->subDays(2), 'power_cap' => 200 + $i]);
        }
        $this->observation($f, ['numeric_value' => 123, 'observed_at' => $this->now->subHour()]);
        $this->observation($f, ['numeric_value' => 456, 'observed_at' => $this->now->subMinutes(30)]);
        $this->observation($f, ['numeric_value' => 999, 'source_type' => 'community', 'observed_at' => $this->now]);
        $this->condition($f, ['power_cap' => 500]);
        $this->condition($f, ['power_cap' => 700]);
        $this->condition($f, ['power_cap' => 999, 'source_type' => 'community', 'observed_at' => $this->now]);
        $group = $this->group($f, 'Current group');
        $group->kingdoms()->attach([$f['actor']->kingdomId, $f['participant']->destination_kingdom_id]);
        for ($i = 0; $i < 20; $i++) {
            $unrelated = app(ScenarioFactory::class)->kingdom(64600 + $i);
            $this->group($f, 'Unrelated '.$i)->kingdoms()->attach($unrelated->kingdomId);
        }
        $expected = $this->canonical($f);
        self::assertContains('conflicting', array_column($expected['requirements'], 'state'));
        $counts = ['observations' => 0, 'conditions' => 0, 'groups' => 0];
        foreach ([TransferObservation::class => 'observations', TransferKingdomConditionObservation::class => 'conditions', TransferGroup::class => 'groups'] as $model => $name) {
            Event::listen('eloquent.retrieved: '.$model, static function () use (&$counts, $name): void {
                $counts[$name]++;
            });
        }

        $result = app(TransferSelfEligibilityQuery::class)->forPlayer($f['actor']->playerId, $f['alliance']->allianceId);

        self::assertNotNull($result);
        self::assertSame($expected, array_intersect_key($result, $expected));
        self::assertSame(183, $result['observationCount']);
        self::assertLessThanOrEqual(count(TransferObservationKind::cases()) * 4, $counts['observations']);
        self::assertLessThanOrEqual(6, $counts['conditions']);
        self::assertLessThanOrEqual(2, $counts['groups']);
        self::assertSame('Current group', $result['targetGroupLabel']);
        self::assertSame(64532, $result['targetKingdomNumber']);
    }

    /** @return iterable<string,array{string,?string,?string,?int}> */
    public static function participantStates(): iterable
    {
        yield 'staying' => ['staying', 'source', null, null];
        yield 'missing target' => ['outgoing', 'source', null, null];
        yield 'missing source' => ['outgoing', null, 'target', 64542];
        yield 'incoming targets home' => ['incoming', 'target', null, 64541];
    }

    #[DataProvider('participantStates')]
    public function test_self_projection_delegates_staying_incoming_and_incomplete_scope_without_optimism(string $direction, ?string $source, ?string $target, ?int $targetNumber): void
    {
        $f = $this->outgoingScenario(64541, 64542);
        $ids = ['source' => $f['actor']->kingdomId, 'target' => $f['participant']->destination_kingdom_id];
        $f['participant']->update(['direction' => $direction, 'source_kingdom_id' => $source === null ? null : $ids[$source], 'destination_kingdom_id' => $target === null ? null : $ids[$target]]);
        $this->observation($f);
        $expected = $this->canonical($f);

        $result = app(TransferSelfEligibilityQuery::class)->forPlayer($f['actor']->playerId, $f['alliance']->allianceId);

        self::assertNotNull($result);
        self::assertSame($expected, array_intersect_key($result, $expected));
        self::assertSame(1, $result['observationCount']);
        self::assertSame($targetNumber, $result['targetKingdomNumber']);
        self::assertNotSame('eligible_now', $result['outcome']);
    }

    public function test_historical_other_target_observations_are_counted_without_entering_current_evaluation(): void
    {
        $f = $this->outgoingScenario(64551, 64552);
        $former = app(ScenarioFactory::class)->kingdom(64553);
        for ($i = 0; $i < 80; $i++) {
            $this->observation($f, ['kind' => 'transfer_passes_required', 'target_kingdom_id' => $former->kingdomId, 'numeric_value' => 10]);
        }
        $expected = $this->canonical($f);
        $hydrated = 0;
        Event::listen('eloquent.retrieved: '.TransferObservation::class, static function () use (&$hydrated): void {
            $hydrated++;
        });

        $result = app(TransferSelfEligibilityQuery::class)->forPlayer($f['actor']->playerId, $f['alliance']->allianceId, 64552);

        self::assertNotNull($result);
        self::assertSame(80, $result['observationCount']);
        self::assertSame($expected, array_intersect_key($result, $expected));
        self::assertSame(0, $hydrated);
    }

    public function test_self_projection_rechecks_current_authority_before_reading_evidence(): void
    {
        $f = $this->outgoingScenario(64561, 64562);
        $query = app(TransferSelfEligibilityQuery::class);
        self::assertNotNull($query->forPlayer($f['actor']->playerId, $f['alliance']->allianceId));
        AllianceMembership::query()->where('alliance_id', $f['alliance']->allianceId)->where('player_id', $f['actor']->playerId)->update(['status' => 'suspended']);
        $reads = 0;
        foreach ([TransferParticipant::class, TransferObservation::class, TransferKingdomConditionObservation::class, TransferGroup::class] as $model) {
            Event::listen('eloquent.retrieved: '.$model, static function () use (&$reads): void {
                $reads++;
            });
        }

        self::assertNull($query->forPlayer($f['actor']->playerId, $f['alliance']->allianceId));
        self::assertSame(0, $reads);
    }

    public function test_archived_target_and_withdrawn_participation_are_not_exposed(): void
    {
        $f = $this->outgoingScenario(64571, 64572);
        $query = app(TransferSelfEligibilityQuery::class);
        $f['participant']->update(['withdrawn_at' => $this->now]);
        self::assertNull($query->forPlayer($f['actor']->playerId, $f['alliance']->allianceId));
        $f['participant']->update(['withdrawn_at' => null]);
        Kingdom::query()->whereKey($f['participant']->destination_kingdom_id)->update(['status' => 'archived']);
        self::assertNull($query->forPlayer($f['actor']->playerId, $f['alliance']->allianceId));
    }

    /** @param array{actor:PlayerReference,alliance:AllianceReference,plan:TransferPlan,participant:TransferParticipant} $f
     * @return array<string,mixed>
     */
    private function canonical(array $f): array
    {
        $row = app(TransferEligibilityQuery::class)->forPlan($f['alliance']->allianceId, $f['plan'], collect([$f['participant']]))[(string) $f['participant']->id];
        $assessment = $row['assessment'];
        $requirements = array_map(static fn (TransferRequirement $r): array => [
            'key' => $r->key->value, 'state' => $r->state->value, 'explanation' => $r->explanation,
            'actual' => $r->actual, 'required' => $r->required, 'nextAction' => $r->nextAction,
            'sourceType' => $r->sourceType?->value, 'sourceReference' => $r->sourceReference,
            'observedAt' => $r->observedAt?->toIso8601String(), 'validUntil' => $r->validUntil?->toIso8601String(),
        ], $assessment->requirements);

        return ['outcome' => $assessment->outcome->value, 'requirements' => $requirements, 'primaryAction' => $assessment->primaryAction,
            'evaluatedAt' => $assessment->evaluatedAt->toIso8601String(), 'targetGroupLabel' => $row['officialGroup']?->official_label,
            'targetConditionId' => $row['targetCondition']?->id,
        ];
    }

    /** @param array{alliance:AllianceReference,plan:TransferPlan,participant:TransferParticipant} $f
     * @param  array<string,mixed>  $overrides
     */
    private function observation(array $f, array $overrides = []): void
    {
        $id = strtolower((string) Str::ulid());
        DB::table('transfer_observations')->insert(array_replace([
            'id' => $id, 'alliance_id' => $f['alliance']->allianceId, 'transfer_window_id' => $f['plan']->transfer_window_id,
            'transfer_plan_id' => $f['plan']->id, 'transfer_participant_id' => $f['participant']->id,
            'kind' => 'governor_power', 'numeric_value' => 100, 'source_type' => 'in_game',
            'source_reference' => 'Observation '.$id, 'observed_at' => $this->now->subHour(), 'valid_until' => $this->now->addDay(),
            'fingerprint' => hash('sha256', $id), 'created_at' => $this->now, 'updated_at' => $this->now,
        ], $overrides));
    }

    /** @param array{alliance:AllianceReference,plan:TransferPlan,participant:TransferParticipant} $f
     * @param  array<string,mixed>  $overrides
     */
    private function condition(array $f, array $overrides = []): void
    {
        $id = strtolower((string) Str::ulid());
        DB::table('transfer_kingdom_condition_observations')->insert(array_replace([
            'id' => $id, 'alliance_id' => $f['alliance']->allianceId, 'transfer_window_id' => $f['plan']->transfer_window_id,
            'kingdom_id' => $f['participant']->destination_kingdom_id, 'power_cap' => 500, 'hero_generation' => 3,
            'truegold_level' => 3, 'character_age_threshold_days' => 90, 'classification' => 'ordinary',
            'source_type' => 'in_game', 'source_reference' => 'Condition '.$id, 'observed_at' => $this->now->subHour(),
            'fingerprint' => hash('sha256', $id), 'created_at' => $this->now, 'updated_at' => $this->now,
        ], $overrides));
    }

    /** @param array{alliance:AllianceReference,plan:TransferPlan} $f */
    private function group(array $f, string $label): TransferGroup
    {
        return TransferGroup::query()->create([
            'alliance_id' => $f['alliance']->allianceId, 'transfer_window_id' => $f['plan']->transfer_window_id,
            'official_label' => $label, 'revision' => 1, 'source_type' => 'in_game',
            'source_reference' => 'Fixture group', 'observed_at' => $this->now,
        ]);
    }

    /**
     * @return array{actor:PlayerReference,alliance:AllianceReference,roster:RosterEntryReference}
     */
    private function ownerScenario(int $kingdomNumber): array
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $actor = $factory->player($account->userId, $kingdomNumber, 'TRANSFER-'.$kingdomNumber);
        $alliance = $factory->alliance($actor);
        $roster = $factory->roster($actor, $alliance, $actor);

        return compact('actor', 'alliance', 'roster');
    }

    /**
     * @return array{
     *   actor:PlayerReference,
     *   alliance:AllianceReference,
     *   roster:RosterEntryReference,
     *   plan:TransferPlan,
     *   participant:TransferParticipant
     * }
     */
    private function outgoingScenario(int $homeNumber, int $targetNumber): array
    {
        $scenario = $this->ownerScenario($homeNumber);
        app(ScenarioFactory::class)->kingdom($targetNumber);
        $windowId = app(SaveTransferWindow::class)->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            [
                'label' => 'Assistant current transfer',
                'pre_transfer_starts_at' => $this->now->subDays(3)->toIso8601String(),
                'invitational_starts_at' => $this->now->subDays(2)->toIso8601String(),
                'transfer_opens_at' => $this->now->subDay()->toIso8601String(),
                'ends_at' => $this->now->addDay()->toIso8601String(),
                'source_type' => TransferSourceType::OfficialPublication,
                'source_reference' => 'Century Games Kingdom Transfer publication',
                'observed_at' => $this->now->subDays(4)->toIso8601String(),
            ],
        );
        app(CreateTransferPlan::class)->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            ['label' => 'Assistant transfer plan', 'transfer_window_id' => $windowId],
        );
        $plan = TransferPlan::query()
            ->where('alliance_id', $scenario['alliance']->allianceId)
            ->where('transfer_window_id', $windowId)
            ->firstOrFail();
        app(SaveTransferParticipant::class)->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            (string) $plan->id,
            [
                'direction' => TransferDirection::Outgoing,
                'roster_entry_id' => $scenario['roster']->rosterEntryId,
                'destination_kingdom' => $targetNumber,
            ],
        );
        $participant = TransferParticipant::query()
            ->where('transfer_plan_id', $plan->id)
            ->where('player_id', $scenario['actor']->playerId)
            ->firstOrFail();

        return [
            ...$scenario,
            'plan' => $plan,
            'participant' => $participant,
        ];
    }
}
