<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\KingdomTransfers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CreateTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferObservationKind;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferCapacityReservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferInvitationAllocation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomCapacityObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomConditionObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferCapacityPlanningQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferEligibilityEvidenceQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferEligibilityQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferObservationHistoryQuery;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferKingdomConditionSelector;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferObservationSelector;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\ReadModels\RecruitmentManagement\Queries\TransferCampaignWorkspaceQuery;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class TransferEligibilityEvidenceBoundsV3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());
    }

    /** @return iterable<string,array{TransferObservationKind}> */
    public static function kinds(): iterable
    {
        foreach (TransferObservationKind::cases() as $kind) {
            yield $kind->value => [$kind];
        }
    }

    #[DataProvider('kinds')]
    public function test_bounded_observation_witnesses_preserve_the_complete_selector_contract(TransferObservationKind $kind): void
    {
        $f = $this->fixture();
        $now = CarbonImmutable::now();
        $target = $kind->requiresTargetKingdom() ? (string) $f['participant']->destination_kingdom_id : null;
        $first = $kind->usesNumericValue() ? 10 : ($kind->usesBooleanValue() ? true : 'ordinary_received');
        $other = $kind->usesNumericValue() ? 20 : ($kind->usesBooleanValue() ? false : 'special_received');
        $valueKey = $kind->usesNumericValue() ? 'numeric_value' : ($kind->usesBooleanValue() ? 'boolean_value' : 'text_value');
        $selector = app(TransferObservationSelector::class);
        $query = app(TransferEligibilityEvidenceQuery::class);
        $raw = fn () => TransferObservation::query()->where('transfer_participant_id', $f['participant']->id)->orderByDesc('observed_at')->orderByDesc('id')->get();
        $bounded = fn () => $query->observations($f['alliance']->allianceId, (string) $f['plan']->id, [(string) $f['participant']->id => (string) $f['participant']->destination_kingdom_id], $now);
        self::assertEquals($selector->select($raw(), $kind, $target, $now), $selector->select($bounded(), $kind, $target, $now));
        foreach ([
            ['source_type' => 'community', 'valid_until' => $now->addDay()],
            ['source_type' => 'in_game', 'valid_until' => null],
            ['source_type' => 'official_publication', 'valid_until' => $now->subSecond()],
            ['source_type' => 'in_game', 'valid_until' => $now],
            ['source_type' => 'in_game', 'valid_until' => $now->addDay(), $valueKey => $other],
        ] as $step => $state) {
            for ($i = 0; $i < 55; $i++) {
                $this->observation($f, [...$state, 'kind' => $kind->value, 'target_kingdom_id' => $target, $valueKey => $state[$valueKey] ?? $first, 'observed_at' => $now->subMinutes(10 - $step)]);
            }
            $rows = $bounded();
            self::assertLessThanOrEqual(4, $rows->count());
            self::assertEquals($selector->select($raw(), $kind, $target, $now), $selector->select($rows, $kind, $target, $now));
        }
        // A newer untrusted value and an unrelated target must not hide a live conflict.
        $this->observation($f, ['kind' => $kind->value, 'target_kingdom_id' => $target, 'source_type' => 'community', $valueKey => $other, 'observed_at' => $now]);
        $rows = $bounded();
        self::assertLessThanOrEqual(4, $rows->count());
        self::assertEquals($selector->select($raw(), $kind, $target, $now), $selector->select($rows, $kind, $target, $now));
    }

    public function test_condition_witnesses_preserve_each_simultaneous_conflict_and_latest_provenance(): void
    {
        $f = $this->fixture();
        $target = (string) $f['participant']->destination_kingdom_id;
        $columns = ['power_cap', 'hero_generation', 'truegold_level', 'character_age_threshold_days'];
        for ($i = 0; $i < 200; $i++) {
            $this->condition($f, ['observed_at' => now()->subDay(), 'power_cap' => $i]);
        }
        $at = now()->subHour();
        foreach ($columns as $column) {
            $this->condition($f, ['observed_at' => $at, $column => 20]);
        }
        $this->condition($f, ['observed_at' => $at, 'power_cap' => null]);
        $this->condition($f, ['observed_at' => $at]);
        $this->condition($f, ['observed_at' => now(), 'source_type' => 'community']);
        $all = TransferKingdomConditionObservation::query()->where('kingdom_id', $target)->orderByDesc('observed_at')->orderByDesc('id')->get();
        $rows = app(TransferEligibilityEvidenceQuery::class)->conditions($f['alliance']->allianceId, (string) $f['plan']->transfer_window_id, [$target]);
        self::assertLessThanOrEqual(6, $rows->count());
        $selector = app(TransferKingdomConditionSelector::class);
        foreach ($columns as $column) {
            self::assertEquals($selector->value($all, $column), $selector->value($rows, $column));
        }
        self::assertEquals($selector->classification($all), $selector->classification($rows));
        self::assertSame((string) $all->first()->id, (string) $rows->first()->id);
    }

    public function test_single_participant_evaluation_excludes_unrelated_participants_and_targets_before_hydration(): void
    {
        $f = $this->fixture();
        $other = TransferParticipant::query()->create([
            'alliance_id' => $f['alliance']->allianceId, 'transfer_plan_id' => $f['plan']->id,
            'direction' => 'incoming', 'observed_name' => 'Other Governor',
        ]);
        $unrelatedKingdom = app(ScenarioFactory::class)->kingdom(59303);
        for ($i = 0; $i < 501; $i++) {
            $this->observation($f, ['transfer_participant_id' => $other->id]);
            $this->observation($f, ['target_kingdom_id' => $unrelatedKingdom->kingdomId, 'kind' => 'transfer_passes_required']);
            $this->condition($f, ['kingdom_id' => $unrelatedKingdom->kingdomId]);
        }
        $wanted = $this->observation($f, ['numeric_value' => 123]);
        $observations = 0;
        $conditions = 0;
        TransferObservation::retrieved(static function () use (&$observations): void {
            $observations++;
        });
        TransferKingdomConditionObservation::retrieved(static function () use (&$conditions): void {
            $conditions++;
        });
        DB::enableQueryLog();
        DB::flushQueryLog();
        $result = app(TransferEligibilityQuery::class)->forPlan($f['alliance']->allianceId, $f['plan'], collect([$f['participant']]));
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        self::assertSame([(string) $f['participant']->id], array_keys($result));
        self::assertSame([$wanted], $result[(string) $f['participant']->id]['observations']->pluck('id')->all());
        self::assertSame(1, $observations);
        self::assertSame(0, $conditions);
        self::assertLessThanOrEqual(12, $queries);
    }

    public function test_campaign_reports_true_totals_with_old_active_blockers_and_large_observation_history(): void
    {
        $f = $this->fixture();
        $candidate = RecruitmentCandidate::query()->create([
            'alliance_id' => $f['alliance']->allianceId, 'player_id' => $f['actor']->playerId,
            'full_name' => 'Transfer candidate', 'email' => 'transfer-count@example.test', 'stage' => 'accepted', 'submitted_at' => now(),
        ]);
        for ($i = 0; $i < 71; $i++) {
            $this->observation($f);
            DB::table('transfer_blockers')->insert([
                'id' => (string) Str::ulid(), 'alliance_id' => $f['alliance']->allianceId,
                'transfer_plan_id' => $f['plan']->id, 'transfer_participant_id' => $f['participant']->id,
                'state' => $i < 21 ? 'active' : 'resolved', 'summary' => 'Blocker '.$i,
                'created_at' => now()->subMinutes(100 - $i), 'updated_at' => now(),
            ]);
        }
        $row = app(TransferCampaignWorkspaceQuery::class)->forCandidate($f['actor']->playerId, $f['alliance']->allianceId, $candidate);
        self::assertSame(71, $row['transfer']['evidenceCount']);
        self::assertSame(21, $row['transfer']['activeBlockerCount']);
    }

    public function test_observation_history_continues_across_ties_and_deleted_boundaries_with_current_authority(): void
    {
        $f = $this->fixture();
        $ids = [];
        for ($i = 0; $i < 51; $i++) {
            $ids[] = $this->observation($f, ['observed_at' => now()->subDay()]);
        }
        rsort($ids);
        $query = app(TransferObservationHistoryQuery::class);
        $page = $query->forParticipant($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, (string) $f['participant']->id);
        self::assertSame(array_slice($ids, 0, 25), array_map(static fn (TransferObservation $row): string => (string) $row->id, $page->items));
        DB::table('transfer_observations')->where('id', $ids[24])->delete();
        $this->observation($f, ['observed_at' => now()]);
        $next = $query->forParticipant($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, (string) $f['participant']->id, $page->nextCursor);
        self::assertSame(array_slice($ids, 25, 25), array_map(static fn (TransferObservation $row): string => (string) $row->id, $next->items));
        $last = $query->forParticipant($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, (string) $f['participant']->id, $next->nextCursor);
        self::assertCount(1, $last->items);
        self::assertNull($last->nextCursor);
        $this->actingAs($f['user'])->withSession(['players.selected_id' => $f['actor']->playerId]);
        $url = '/alliance/transfers/'.$f['plan']->id.'/participants/'.$f['participant']->id.'/observations';
        $this->getJson($url.'?cursor='.urlencode((string) $page->nextCursor))->assertOk()->assertJsonCount(25, 'items')->assertJsonPath('isFirstPage', false);
        $this->getJson($url.'?cursor[]=bad')->assertUnprocessable();
        try {
            $query->forParticipant($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, (string) $f['participant']->id, 'tampered');
            self::fail('A forged cursor must be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('cursor', $exception->errors());
        }
        AllianceMembership::query()->where('alliance_id', $f['alliance']->allianceId)->where('player_id', $f['actor']->playerId)->update(['status' => 'suspended']);
        $this->expectException(AuthorizationException::class);
        $query->forParticipant($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, (string) $f['participant']->id, $page->nextCursor);
    }

    public function test_observation_cursor_cannot_cross_participants_and_foreign_targets_are_unavailable(): void
    {
        $f = $this->fixture();
        for ($i = 0; $i < 26; $i++) {
            $this->observation($f);
        }
        $other = $this->extraParticipant($f);
        $query = app(TransferObservationHistoryQuery::class);
        $page = $query->forParticipant($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, (string) $f['participant']->id);
        try {
            $query->forParticipant($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, (string) $other->id, $page->nextCursor);
            self::fail('Continuation cannot be reused for another participant.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('cursor', $exception->errors());
        }
        $this->actingAs($f['user'])->withSession(['players.selected_id' => $f['actor']->playerId]);
        $this->getJson('/alliance/transfers/'.$f['plan']->id.'/participants/'.Str::ulid().'/observations')->assertNotFound();
        $this->getJson('/alliance/transfers/'.Str::ulid().'/participants/'.$f['participant']->id.'/observations')->assertNotFound();
    }

    /** @return iterable<string,array{string,bool}> */
    public static function capacityCases(): iterable
    {
        foreach (['ordinary_invite', 'transfer_open'] as $bucket) {
            yield $bucket.' observed' => [$bucket, true];
            yield $bucket.' missing' => [$bucket, false];
        }
    }

    #[DataProvider('capacityCases')]
    public function test_capacity_counts_only_unreflected_consuming_commitments_without_hydrating_history(string $bucket, bool $observed): void
    {
        $f = $this->fixture();
        $at = now()->subHour();
        $target = (string) $f['participant']->destination_kingdom_id;
        for ($i = 0; $i < 101; $i++) {
            $this->condition($f, ['observed_at' => $at->copy()->subMinutes($i)]);
            if ($observed) {
                $id = (string) Str::ulid();
                DB::table('transfer_kingdom_capacity_observations')->insert([
                    'id' => $id, 'alliance_id' => $f['alliance']->allianceId, 'transfer_window_id' => $f['plan']->transfer_window_id,
                    'kingdom_id' => $target, 'ordinary_invites_used' => 3, 'transfer_opens_used' => 4, 'special_invites_available' => 9,
                    'source_type' => 'in_game', 'source_reference' => 'Capacity '.$i, 'observed_at' => $at->copy()->subMinutes($i),
                    'fingerprint' => hash('sha256', $id), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        foreach (['planned', 'reserved', 'confirmed', 'released', 'failed'] as $state) {
            foreach ([-1, 1] as $offset) {
                $participant = $this->extraParticipant($f);
                DB::table('transfer_capacity_reservations')->insert([
                    'id' => (string) Str::ulid(), 'alliance_id' => $f['alliance']->allianceId,
                    'transfer_window_id' => $f['plan']->transfer_window_id, 'transfer_plan_id' => $f['plan']->id,
                    'transfer_participant_id' => $participant->id, 'target_kingdom_id' => $target,
                    'bucket' => $bucket, 'state' => $state, 'created_by_player_id' => $f['actor']->playerId,
                    'created_at' => $at, 'updated_at' => $at->copy()->addSeconds($offset),
                ]);
            }
        }
        foreach (['ordinary', 'special'] as $kind) {
            foreach (['requested', 'reserved', 'issued', 'accepted', 'declined', 'cancelled'] as $state) {
                foreach ([-1, 1] as $offset) {
                    $participant = $this->extraParticipant($f);
                    DB::table('transfer_invitation_allocations')->insert([
                        'id' => (string) Str::ulid(), 'alliance_id' => $f['alliance']->allianceId,
                        'transfer_window_id' => $f['plan']->transfer_window_id, 'transfer_plan_id' => $f['plan']->id,
                        'transfer_participant_id' => $participant->id, 'target_kingdom_id' => $target,
                        'kind' => $kind, 'state' => $state, 'created_by_player_id' => $f['actor']->playerId,
                        'created_at' => $at, 'updated_at' => $at->copy()->addSeconds($offset),
                    ]);
                }
            }
        }
        $hydratedCommitments = 0;
        $hydratedCapacity = 0;
        $hydratedConditions = 0;
        TransferCapacityReservation::retrieved(static function () use (&$hydratedCommitments): void {
            $hydratedCommitments++;
        });
        TransferInvitationAllocation::retrieved(static function () use (&$hydratedCommitments): void {
            $hydratedCommitments++;
        });
        TransferKingdomCapacityObservation::retrieved(static function () use (&$hydratedCapacity): void {
            $hydratedCapacity++;
        });
        TransferKingdomConditionObservation::retrieved(static function () use (&$hydratedConditions): void {
            $hydratedConditions++;
        });
        $result = app(TransferCapacityPlanningQuery::class)->forTargets($f['alliance']->allianceId, (string) $f['plan']->transfer_window_id, [$target])[$target];
        self::assertSame($observed ? 5 : 6, $bucket === 'ordinary_invite' ? $result->plannedOrdinaryInviteReservations : $result->plannedTransferOpenReservations);
        self::assertSame(0, $bucket === 'ordinary_invite' ? $result->plannedTransferOpenReservations : $result->plannedOrdinaryInviteReservations);
        self::assertSame($observed ? 4 : 6, $result->plannedSpecialInviteAllocations);
        self::assertSame($observed ? 'Capacity 0' : null, $result->sourceReference);
        self::assertSame(0, $hydratedCommitments);
        self::assertSame($observed ? 1 : 0, $hydratedCapacity);
        self::assertSame(1, $hydratedConditions);
    }

    public function test_group_projection_loads_only_requested_kingdom_membership(): void
    {
        $f = $this->fixture();
        $kingdomIds = [(string) $f['participant']->source_kingdom_id, (string) $f['participant']->destination_kingdom_id];
        for ($i = 0; $i < 50; $i++) {
            $kingdomIds[] = app(ScenarioFactory::class)->kingdom(59400 + $i)->kingdomId;
        }
        $group = TransferGroup::query()->create([
            'alliance_id' => $f['alliance']->allianceId, 'transfer_window_id' => $f['plan']->transfer_window_id,
            'official_label' => 'One current group', 'revision' => 1, 'source_type' => 'official_publication',
            'source_reference' => 'Official group', 'observed_at' => now(),
        ]);
        $group->kingdoms()->sync($kingdomIds);
        $wanted = array_slice($kingdomIds, 0, 2);
        $rows = app(TransferEligibilityEvidenceQuery::class)->groups($f['alliance']->allianceId, (string) $f['plan']->transfer_window_id, $wanted);
        self::assertEqualsCanonicalizing($wanted, array_keys($rows));
        foreach ($rows as $row) {
            self::assertSame((string) $group->id, (string) $row->id);
            self::assertCount(2, $row->kingdoms);
        }
    }

    /** @param array{actor:PlayerReference,alliance:AllianceReference,plan:TransferPlan,participant:TransferParticipant,user:User} $f */
    private function extraParticipant(array $f): TransferParticipant
    {
        return TransferParticipant::query()->create([
            'alliance_id' => $f['alliance']->allianceId, 'transfer_plan_id' => $f['plan']->id,
            'direction' => 'incoming', 'observed_name' => 'Additional Governor',
        ]);
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

    /**
     * @param  array{actor:PlayerReference,alliance:AllianceReference,plan:TransferPlan,participant:TransferParticipant,user:User}  $f
     * @param  array<string,mixed>  $overrides
     */
    private function observation(array $f, array $overrides = []): string
    {
        $id = (string) Str::ulid();
        DB::table('transfer_observations')->insert(array_replace([
            'id' => $id, 'alliance_id' => $f['alliance']->allianceId, 'transfer_window_id' => $f['plan']->transfer_window_id,
            'transfer_plan_id' => $f['plan']->id, 'transfer_participant_id' => $f['participant']->id, 'target_kingdom_id' => null,
            'kind' => 'transfer_score', 'numeric_value' => 10, 'source_type' => 'in_game', 'source_reference' => 'Observation '.$id,
            'observed_at' => now()->subHour(), 'valid_until' => now()->addDay(), 'fingerprint' => hash('sha256', $id),
            'created_at' => now(), 'updated_at' => now(),
        ], $overrides));

        return $id;
    }

    /**
     * @param  array{actor:PlayerReference,alliance:AllianceReference,plan:TransferPlan,participant:TransferParticipant,user:User}  $f
     * @param  array<string,mixed>  $overrides
     */
    private function condition(array $f, array $overrides = []): void
    {
        $id = (string) Str::ulid();
        DB::table('transfer_kingdom_condition_observations')->insert(array_replace([
            'id' => $id, 'alliance_id' => $f['alliance']->allianceId, 'transfer_window_id' => $f['plan']->transfer_window_id,
            'kingdom_id' => $f['participant']->destination_kingdom_id, 'power_cap' => 10, 'hero_generation' => 10,
            'truegold_level' => 10, 'character_age_threshold_days' => 10, 'classification' => 'ordinary',
            'source_type' => 'in_game', 'source_reference' => 'Condition '.$id, 'observed_at' => now()->subHour(),
            'fingerprint' => hash('sha256', $id), 'created_at' => now(), 'updated_at' => now(),
        ], $overrides));
    }
}
