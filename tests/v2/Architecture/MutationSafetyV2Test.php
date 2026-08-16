<?php

declare(strict_types=1);

namespace Tests\v2\Architecture;

use Tests\v2\TestCase;

final class MutationSafetyV2Test extends TestCase
{
    public function test_high_risk_mutations_keep_transactions_and_aggregate_locks_in_the_write_path(): void
    {
        $contracts = [
            'app/Contexts/Alliance/Core/Actions/CreateAlliance.php' => ['DB::transaction', 'lockForUpdate'],
            'app/Contexts/Alliance/Membership/Actions/TransferAllianceLeadership.php' => ['DB::transaction', 'lockForUpdate'],
            'app/Contexts/GameWorld/Governance/Actions/AssignKingdomRole.php' => ['DB::transaction', 'lockForUpdate'],
            'app/Contexts/Operations/EventCore/Actions/CreateEvent.php' => ['DB::transaction', 'lockForUpdate'],
            'app/Contexts/Operations/KingPerks/Services/KingPerkScheduler.php' => ['DB::transaction', 'lockForUpdate'],
            'app/Contexts/GameWorld/KingdomTransfers/Actions/TransitionTransferPlan.php' => ['DB::transaction', 'lockForUpdate'],
        ];

        foreach ($contracts as $file => $needles) {
            $source = (string) file_get_contents(base_path($file));
            foreach ($needles as $needle) {
                self::assertStringContainsString($needle, $source, $file.' must retain '.$needle);
            }
            self::assertStringNotContainsString('MutationAuthority', $source, $file);
        }
    }
}
