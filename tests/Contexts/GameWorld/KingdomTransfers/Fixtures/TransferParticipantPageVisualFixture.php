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
use Illuminate\Support\Facades\Hash;
use Tests\Support\ScenarioFactory;

/** Independent scope per browser project; no other visual fixture or plan is modified. */
final class TransferParticipantPageVisualFixture
{
    public static function seed(): void
    {
        foreach (['desktop', 'mobile'] as $index => $project) {
            $user = User::factory()->create(['name' => 'Page Captain '.$project, 'email' => 'transfer-pages-'.$project.'@example.test',
                'password' => Hash::make('password'), 'timezone' => 'UTC']);
            $factory = new ScenarioFactory;
            $actor = $factory->player((int) $user->id, 59531 + $index, 'transfer-pages-'.$project);
            Player::query()->whereKey($actor->playerId)->update(['current_name' => 'Page Captain '.$project]);
            $actor = app(PlayerReferenceQuery::class)->require($actor->playerId);
            $alliance = app(CreateAlliance::class)->handle((int) $user->id, $actor->playerId, 'Participant Pages '.$project, 'transfer-pages-'.$project);
            $window = app(SaveTransferWindow::class)->handle($alliance, $actor->playerId, [
                'label' => 'Participant pagination window', 'pre_transfer_starts_at' => now()->subDays(3)->toIso8601String(),
                'invitational_starts_at' => now()->subDays(2)->toIso8601String(), 'transfer_opens_at' => now()->subDay()->toIso8601String(),
                'ends_at' => now()->addYears(20)->toIso8601String(), 'source_type' => TransferSourceType::OfficialPublication,
                'source_reference' => 'Participant page fixture', 'observed_at' => now()->subDays(4)->toIso8601String(),
            ]);
            app(CreateTransferPlan::class)->handle($alliance, $actor->playerId, ['label' => 'Participant Pages '.$project, 'transfer_window_id' => $window]);
            $plan = (string) TransferPlan::query()->where('alliance_id', $alliance)->sole()->id;
            $roster = $factory->roster($actor, app(AllianceReferenceQuery::class)->require($alliance), $actor);
            app(SaveTransferParticipant::class)->handle($alliance, $actor->playerId, $plan, ['direction' => TransferDirection::Staying, 'roster_entry_id' => $roster->rosterEntryId]);
            for ($i = 0; $i < 60; $i++) {
                $player = $factory->unclaimedPlayer(59531 + $index);
                TransferParticipant::query()->create(['alliance_id' => $alliance, 'transfer_plan_id' => $plan,
                    'player_id' => $player->playerId, 'observed_name' => 'Page participant '.$project.' '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    'direction' => 'staying', 'readiness_state' => 'preparing', 'source_kingdom_id' => $player->kingdomId]);
            }
        }
    }
}
