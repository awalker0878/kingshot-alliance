<?php

declare(strict_types=1);

namespace Tests\ReadModels\TransferManagement\Support;

use App\Contexts\GameWorld\KingdomTransfers\Models\TransferCohort;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomCapacityObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomConditionObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferWindow;
use App\ReadModels\TransferManagement\Enums\TransferCatalogueKind;

final class TransferCatalogueFixture
{
    /** @return list<string> */
    public static function seed(TransferWorkspaceFixture $f, TransferCatalogueKind $kind, int $count = 63): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $facts = ['alliance_id' => $f->alliance->allianceId, 'transfer_window_id' => $f->plan->transfer_window_id,
                'source_type' => 'in_game', 'source_reference' => 'Catalogue fixture', 'observed_at' => now(),
                'kingdom_id' => $f->actor->kingdomId, 'fingerprint' => hash('sha256', $kind->value.$f->plan->id.$i)];
            $label = sprintf('Catalogue %03d', $i);
            $row = match ($kind) {
                TransferCatalogueKind::Windows => self::window($f, $label),
                TransferCatalogueKind::Plans => TransferPlan::query()->create(['alliance_id' => $f->alliance->allianceId,
                    'transfer_window_id' => self::window($f, 'Plan '.$label)->id, 'home_kingdom_id' => $f->actor->kingdomId,
                    'label' => $label, 'state' => 'closed']),
                TransferCatalogueKind::Groups => TransferGroup::query()->create(array_diff_key($facts, array_flip(['kingdom_id', 'fingerprint'])) + ['official_label' => $label]),
                TransferCatalogueKind::Conditions => TransferKingdomConditionObservation::query()->create($facts),
                TransferCatalogueKind::Capacities => TransferKingdomCapacityObservation::query()->create($facts),
                TransferCatalogueKind::Cohorts => TransferCohort::query()->create(['alliance_id' => $f->alliance->allianceId,
                    'transfer_plan_id' => $f->plan->id, 'name' => $label, 'direction' => 'incoming', 'state' => 'active']),
            };
            $ids[] = (string) $row->id;
        }
        sort($ids);

        return $ids;
    }

    private static function window(TransferWorkspaceFixture $f, string $label): TransferWindow
    {
        $copy = $f->plan->window->replicate();
        $copy->label = $label;
        $copy->save();

        return $copy;
    }
}
