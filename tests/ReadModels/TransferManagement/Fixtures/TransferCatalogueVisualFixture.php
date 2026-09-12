<?php

declare(strict_types=1);

namespace Tests\ReadModels\TransferManagement\Fixtures;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\Actions\CreateAlliance;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\GameWorld\Kingdoms\Actions\ResolveKingdom;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CreateTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\ReadModels\TransferManagement\Enums\TransferCatalogueKind;
use Illuminate\Support\Facades\Hash;
use Tests\ReadModels\TransferManagement\Support\TransferCatalogueFixture;
use Tests\ReadModels\TransferManagement\Support\TransferWorkspaceFixture;
use Tests\Support\ScenarioFactory;

/** Independent project scopes; no existing screenshot or participant fixture is changed. */
final class TransferCatalogueVisualFixture
{
    public static function seed(): void
    {
        foreach (['desktop', 'mobile'] as $offset => $project) {
            $factory = new ScenarioFactory;
            $user = User::factory()->create(['name' => 'Catalogue captain '.$project, 'email' => 'transfer-catalogues-'.$project.'@example.test',
                'password' => Hash::make('password'), 'email_verified_at' => now(), 'timezone' => 'UTC']);
            $actor = $factory->player((int) $user->id, 61641 + $offset, 'transfer-catalogues-'.$project);
            $allianceId = app(CreateAlliance::class)->handle((int) $user->id, $actor->playerId, 'Transfer Catalogues '.$project, 'transfer-catalogues-'.$project);
            $alliance = app(AllianceReferenceQuery::class)->require($allianceId);
            $windowId = app(SaveTransferWindow::class)->handle($alliance->allianceId, $actor->playerId, [
                'label' => 'Current choice window', 'pre_transfer_starts_at' => now()->subDays(3)->toIso8601String(),
                'invitational_starts_at' => now()->subDays(2)->toIso8601String(), 'transfer_opens_at' => now()->subDay()->toIso8601String(),
                'ends_at' => now()->addYears(20)->toIso8601String(), 'source_type' => TransferSourceType::OfficialPublication,
                'source_reference' => 'Choice fixture', 'observed_at' => now()->subDays(4)->toIso8601String(),
            ]);
            app(CreateTransferPlan::class)->handle($alliance->allianceId, $actor->playerId, ['label' => 'Current choice plan', 'transfer_window_id' => $windowId]);
            $plan = TransferPlan::query()->where('alliance_id', $allianceId)->sole();
            $f = new TransferWorkspaceFixture($user, $actor, $alliance, $plan);
            foreach (TransferCatalogueKind::cases() as $kind) {
                TransferCatalogueFixture::seed($f, $kind, 55);
            }
            TransferParticipant::query()->create([
                'alliance_id' => $allianceId, 'transfer_plan_id' => $plan->id, 'player_id' => $actor->playerId,
                'observed_name' => 'Catalogue Governor', 'direction' => 'incoming', 'readiness_state' => 'preparing',
            ]);
            $group = TransferGroup::query()->where('alliance_id', $allianceId)->orderBy('id')->firstOrFail();
            $kingdoms = [];
            for ($i = 0; $i < 55; $i++) {
                $kingdoms[$i] = app(ResolveKingdom::class)->handle(63000 + $offset * 100 + $i)->kingdomId;
            }
            $group->kingdoms()->sync($kingdoms);
        }
    }
}
