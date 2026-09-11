<?php

declare(strict_types=1);

namespace Tests\Contexts\GameWorld\KingdomTransfers\Fixtures;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\Actions\CreateAlliance;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CreateTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\ScenarioFactory;

/** Separate tenant per project: browser mutations never change another project's fixture. */
final class TransferWorkflowHistoryVisualFixture
{
    public static function seed(): void
    {
        foreach (['desktop', 'mobile'] as $index => $project) {
            $user = User::factory()->create(['name' => 'Workflow History '.$project,
                'email' => 'transfer-history-'.$project.'@example.test', 'password' => Hash::make('password'), 'timezone' => 'UTC']);
            $factory = new ScenarioFactory;
            $actor = $factory->player((int) $user->id, 59521 + $index, 'transfer-history-'.$project);
            Player::query()->whereKey($actor->playerId)->update(['current_name' => 'Workflow History '.$project]);
            $alliance = app(CreateAlliance::class)->handle((int) $user->id, $actor->playerId, 'History '.$project, 'transfer-history-'.$project);
            $window = app(SaveTransferWindow::class)->handle($alliance, $actor->playerId, [
                'label' => 'History pagination window', 'pre_transfer_starts_at' => now()->subDays(3)->toIso8601String(),
                'invitational_starts_at' => now()->subDays(2)->toIso8601String(), 'transfer_opens_at' => now()->subDay()->toIso8601String(),
                'ends_at' => now()->addYears(20)->toIso8601String(), 'source_type' => TransferSourceType::OfficialPublication,
                'source_reference' => 'History fixture', 'observed_at' => now()->subDays(4)->toIso8601String(),
            ]);
            app(CreateTransferPlan::class)->handle($alliance, $actor->playerId, ['label' => 'History '.$project, 'transfer_window_id' => $window]);
            $plan = (string) TransferPlan::query()->where('alliance_id', $alliance)->sole()->id;
            $roster = $factory->roster($actor, app(AllianceReferenceQuery::class)->require($alliance), app(PlayerReferenceQuery::class)->require($actor->playerId));
            app(SaveTransferParticipant::class)->handle($alliance, $actor->playerId, $plan,
                ['direction' => TransferDirection::Staying, 'roster_entry_id' => $roster->rosterEntryId]);
            $participant = (string) TransferParticipant::query()->where('transfer_plan_id', $plan)->sole()->id;
            $scope = ['alliance_id' => $alliance, 'transfer_plan_id' => $plan, 'transfer_participant_id' => $participant];
            DB::table('transfer_readiness_transitions')->where($scope)->delete();
            foreach (['active' => 31, 'resolved' => 53] as $state => $count) {
                for ($i = 0; $i < $count; $i++) {
                    DB::table('transfer_blockers')->insert($scope + ['id' => (string) Str::ulid(), 'state' => $state,
                        'summary' => $state.' blocker '.$i, 'details' => 'History detail '.$i,
                        'created_by_player_id' => $actor->playerId,
                        'resolved_by_player_id' => $state === 'resolved' ? $actor->playerId : null,
                        'resolved_at' => $state === 'resolved' ? now() : null,
                        'created_at' => now()->subDays($state === 'active' ? 10 : 1)->startOfSecond(), 'updated_at' => now()]);
                }
            }
            for ($i = 0; $i < 61; $i++) {
                DB::table('transfer_readiness_transitions')->insert($scope + ['id' => (string) Str::ulid(),
                    'from_state' => 'not_started', 'to_state' => 'preparing', 'actor_player_id' => $actor->playerId,
                    'created_at' => now()->subDay()->startOfSecond()]);
            }
        }
    }
}
