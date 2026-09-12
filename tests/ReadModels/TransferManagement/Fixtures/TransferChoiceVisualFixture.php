<?php

declare(strict_types=1);

namespace Tests\ReadModels\TransferManagement\Fixtures;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CreateTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferWindow;
use App\Contexts\GameWorld\Players\Models\Player;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ScenarioFactory;

/** Independent project scopes; no existing screenshot or participant fixture is changed. */
final class TransferChoiceVisualFixture
{
    public static function seed(): void
    {
        foreach (['desktop', 'mobile'] as $offset => $project) {
            $factory = new ScenarioFactory;
            $user = User::factory()->create(['name' => 'Choice captain '.$project, 'email' => 'transfer-choices-'.$project.'@example.test',
                'password' => Hash::make('password'), 'email_verified_at' => now(), 'timezone' => 'UTC']);
            $actor = $factory->player((int) $user->id, 59541 + $offset);
            $alliance = $factory->alliance($actor);
            $windowId = app(SaveTransferWindow::class)->handle($alliance->allianceId, $actor->playerId, [
                'label' => 'Current choice window', 'pre_transfer_starts_at' => now()->subDays(3)->toIso8601String(),
                'invitational_starts_at' => now()->subDays(2)->toIso8601String(), 'transfer_opens_at' => now()->subDay()->toIso8601String(),
                'ends_at' => now()->addYears(20)->toIso8601String(), 'source_type' => TransferSourceType::OfficialPublication,
                'source_reference' => 'Choice fixture', 'observed_at' => now()->subDays(4)->toIso8601String(),
            ]);
            app(CreateTransferPlan::class)->handle($alliance->allianceId, $actor->playerId, ['label' => 'Current choice plan', 'transfer_window_id' => $windowId]);
            $window = TransferWindow::query()->findOrFail($windowId);
            for ($i = 0; $i < 55; $i++) {
                $label = sprintf('Fixture choice %03d', $i);
                $player = Player::query()->create(['current_kingdom_id' => $actor->kingdomId, 'game_player_id' => 'choices-'.$project.'-'.$i, 'current_name' => $label]);
                AllianceMembership::query()->create(['alliance_id' => $alliance->allianceId, 'player_id' => $player->id, 'rank' => 'r3', 'status' => 'active', 'joined_at' => now()]);
                AllianceRosterEntry::query()->create(['alliance_id' => $alliance->allianceId, 'player_id' => $player->id, 'observed_name' => $label, 'state' => 'active', 'source' => 'manual']);
                $another = $window->replicate();
                $another->label = $label;
                $another->save();
            }
        }
    }
}
