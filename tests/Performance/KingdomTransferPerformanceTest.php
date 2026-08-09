<?php

declare(strict_types=1);

namespace Tests\Performance;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Enums\TransferBlockerState;
use App\Domain\Kingdoms\Enums\TransferDirection;
use App\Domain\Kingdoms\Enums\TransferGroupState;
use App\Domain\Kingdoms\Enums\TransferPlanState;
use App\Domain\Kingdoms\Enums\TransferReadinessState;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\KingdomPlayer;
use App\Domain\Kingdoms\Models\TransferBlocker;
use App\Domain\Kingdoms\Models\TransferCompletion;
use App\Domain\Kingdoms\Models\TransferGroup;
use App\Domain\Kingdoms\Models\TransferParticipant;
use App\Domain\Kingdoms\Models\TransferPlan;
use App\Domain\Kingdoms\Models\TransferReadinessTransition;
use App\Domain\Kingdoms\Queries\TransferGroupQuery;
use App\Domain\Kingdoms\Queries\TransferParticipantQuery;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class KingdomTransferPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_queries_remain_batched_at_realistic_alliance_volume(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Transfer Performance', 'transfer-performance', 5800);
        self::assertNotNull($alliance->kingdom_id);

        $destination = Kingdom::query()->create(['number' => 5899, 'status' => 'active']);
        $source = Kingdom::query()->create(['number' => 5799, 'status' => 'active']);
        $ownerMembership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('user_id', $owner->id)
            ->sole();
        $plan = TransferPlan::query()->create([
            'alliance_id' => $alliance->id,
            'home_kingdom_id' => $alliance->kingdom_id,
            'label' => 'Realistic transfer volume',
            'state' => TransferPlanState::Open,
        ]);

        $outgoingGroups = [];
        $incomingGroups = [];
        for ($index = 1; $index <= 10; $index++) {
            $outgoingGroups[] = TransferGroup::query()->create([
                'alliance_id' => $alliance->id,
                'transfer_plan_id' => $plan->id,
                'name' => 'Outgoing '.$index,
                'direction' => TransferDirection::Outgoing,
                'destination_kingdom_id' => $destination->id,
                'state' => TransferGroupState::Active,
                'coordinator_membership_id' => $ownerMembership->id,
                'manager_notes' => 'Private outgoing group '.$index,
            ]);
            $incomingGroups[] = TransferGroup::query()->create([
                'alliance_id' => $alliance->id,
                'transfer_plan_id' => $plan->id,
                'name' => 'Incoming '.$index,
                'direction' => TransferDirection::Incoming,
                'destination_kingdom_id' => $alliance->kingdom_id,
                'state' => TransferGroupState::Active,
                'coordinator_membership_id' => $ownerMembership->id,
                'manager_notes' => 'Private incoming group '.$index,
            ]);
        }

        for ($index = 1; $index <= 150; $index++) {
            $kind = $index % 3;
            $direction = match ($kind) {
                0 => TransferDirection::Staying,
                1 => TransferDirection::Outgoing,
                default => TransferDirection::Incoming,
            };
            $roster = null;
            if ($direction !== TransferDirection::Incoming) {
                $player = KingdomPlayer::query()->create([
                    'kingdom_id' => $alliance->kingdom_id,
                    'game_player_id' => 'transfer-performance-'.$index,
                    'current_name' => 'Transfer Player '.$index,
                ]);
                $roster = AllianceRosterEntry::query()->create([
                    'alliance_id' => $alliance->id,
                    'kingdom_player_id' => $player->id,
                    'observed_name' => $player->current_name,
                    'state' => 'active',
                    'source' => 'manual',
                ]);
            }

            $group = match ($direction) {
                TransferDirection::Outgoing => $outgoingGroups[$index % 10],
                TransferDirection::Incoming => $incomingGroups[$index % 10],
                TransferDirection::Staying => null,
            };
            $readiness = $index % 7 === 0
                ? TransferReadinessState::Blocked
                : TransferReadinessState::Ready;
            $participant = TransferParticipant::query()->create([
                'alliance_id' => $alliance->id,
                'transfer_plan_id' => $plan->id,
                'transfer_group_id' => $group?->id,
                'direction' => $direction,
                'readiness_state' => $readiness,
                'roster_entry_id' => $roster?->id,
                'observed_name' => 'Transfer Player '.$index,
                'game_player_id' => $direction === TransferDirection::Incoming
                    ? 'incoming-performance-'.$index
                    : null,
                'source_kingdom_id' => $direction === TransferDirection::Incoming ? $source->id : $alliance->kingdom_id,
                'destination_kingdom_id' => match ($direction) {
                    TransferDirection::Incoming => $alliance->kingdom_id,
                    TransferDirection::Outgoing => $destination->id,
                    TransferDirection::Staying => null,
                },
                'manager_notes' => 'Private participant '.$index,
            ]);

            TransferReadinessTransition::query()->create([
                'alliance_id' => $alliance->id,
                'transfer_plan_id' => $plan->id,
                'transfer_participant_id' => $participant->id,
                'from_state' => TransferReadinessState::Preparing,
                'to_state' => $readiness,
                'actor_user_id' => $owner->id,
            ]);

            if ($readiness === TransferReadinessState::Blocked) {
                TransferBlocker::query()->create([
                    'alliance_id' => $alliance->id,
                    'transfer_plan_id' => $plan->id,
                    'transfer_participant_id' => $participant->id,
                    'state' => TransferBlockerState::Active,
                    'summary' => 'Performance blocker '.$index,
                    'details' => 'Private performance detail '.$index,
                    'created_by_user_id' => $owner->id,
                ]);
            }

            if ($index % 5 === 0) {
                TransferCompletion::query()->create([
                    'alliance_id' => $alliance->id,
                    'transfer_plan_id' => $plan->id,
                    'transfer_participant_id' => $participant->id,
                    'roster_entry_id' => $roster?->id,
                    'direction' => $direction,
                    'completed_by_user_id' => $owner->id,
                    'completed_at' => now()->subMinutes($index),
                ]);
            }
        }

        $selectQueries = 0;
        $collectQueries = false;
        DB::listen(static function (QueryExecuted $query) use (&$selectQueries, &$collectQueries): void {
            if ($collectQueries && str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $selectQueries++;
            }
        });

        $collectQueries = true;
        $participants = $this->app->make(TransferParticipantQuery::class)->forPlan($alliance, $plan, true);
        $groups = $this->app->make(TransferGroupQuery::class)->forPlan($alliance, $plan, true);
        $collectQueries = false;

        self::assertCount(150, $participants);
        self::assertCount(20, $groups);
        self::assertTrue($participants->every(fn (TransferParticipant $participant): bool => $participant->relationLoaded('completion')));
        self::assertTrue($participants->every(fn (TransferParticipant $participant): bool => $participant->relationLoaded('blockers')));
        self::assertTrue($participants->every(fn (TransferParticipant $participant): bool => $participant->relationLoaded('readinessTransitions')));
        self::assertTrue($groups->every(fn (TransferGroup $group): bool => $group->relationLoaded('coordinator')));
        self::assertLessThanOrEqual(
            30,
            $selectQueries,
            'Transfer planning queries must remain eager-loaded/batched instead of growing SELECT count with participant volume.',
        );
    }
}
