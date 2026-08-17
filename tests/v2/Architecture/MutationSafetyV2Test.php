<?php

declare(strict_types=1);

namespace Tests\v2\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\v2\TestCase;

final class MutationSafetyV2Test extends TestCase
{
    public function test_high_risk_write_owners_keep_transaction_boundaries(): void
    {
        $owners = [
            'app/Contexts/Alliance/Core/Actions/CreateAlliance.php',
            'app/Contexts/Alliance/Membership/Actions/TransferAllianceLeadership.php',
            'app/Contexts/GameWorld/Governance/Actions/AssignKingdomRole.php',
            'app/Contexts/Operations/EventCore/Actions/CreateEvent.php',
            'app/Contexts/Operations/KingPerks/Services/KingPerkScheduler.php',
            'app/Contexts/GameWorld/KingdomTransfers/Actions/TransitionTransferPlan.php',
        ];

        foreach ($owners as $file) {
            $source = (string) file_get_contents(base_path($file));
            self::assertStringContainsString('DB::transaction', $source, $file.' must own its transaction boundary.');
            self::assertStringNotContainsString('MutationAuthority', $source, $file);
        }
    }

    public function test_policy_free_write_state_services_stabilize_mutable_state_inside_transactions(): void
    {
        $contracts = [
            'app/Contexts/Alliance/Access/Services/AllianceWriteState.php' => ['transactionLevel', 'lockForUpdate', 'sharedLock'],
            'app/Contexts/GameWorld/Governance/Services/KingdomWriteState.php' => ['transactionLevel', 'lockForUpdate'],
            'app/Contexts/GameWorld/Governance/Services/PlayerWriteState.php' => ['transactionLevel', 'lockForUpdate'],
            'app/Contexts/Operations/EventCore/Services/EventWriteState.php' => ['transactionLevel', 'lockForUpdate', 'sharedLock'],
            'app/Contexts/Platform/Access/Services/PlatformWriteState.php' => ['transactionLevel', 'lockForUpdate'],
        ];

        foreach ($contracts as $file => $needles) {
            $source = (string) file_get_contents(base_path($file));
            foreach ($needles as $needle) {
                self::assertStringContainsString($needle, $source, $file.' must retain '.$needle.'.');
            }
            self::assertStringNotContainsString('Permission::', $source, $file.' must not interpret permission vocabulary.');
            self::assertStringNotContainsString('MutationAuthority', $source, $file);
        }
    }

    public function test_authorization_services_are_policy_only_and_never_acquire_write_locks(): void
    {
        $files = $this->authorizationFiles();
        self::assertNotEmpty($files);

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);
            self::assertStringNotContainsString('lockForUpdate', $source, $file);
            self::assertStringNotContainsString('sharedLock', $source, $file);
            self::assertStringNotContainsString('transactionLevel', $source, $file);
            self::assertStringNotContainsString('DB::transaction', $source, $file);
            self::assertStringNotContainsString('WriteState', $source, $file);
            self::assertStringNotContainsString('MutationAuthority', $source, $file);
        }
    }

    public function test_no_mutation_authority_abstraction_remains_in_production_code(): void
    {
        $root = base_path('app');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        /** @var SplFileInfo $entry */
        foreach ($iterator as $entry) {
            if (! $entry->isFile() || $entry->getExtension() !== 'php') {
                continue;
            }

            self::assertStringNotContainsString('MutationAuthority', (string) file_get_contents($entry->getPathname()), $entry->getPathname());
            self::assertStringNotContainsString('MutationAuthority.php', $entry->getFilename(), $entry->getPathname());
        }
    }

    /** @return list<string> */
    private function authorizationFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('app/Contexts')));

        /** @var SplFileInfo $entry */
        foreach ($iterator as $entry) {
            if ($entry->isFile() && str_ends_with($entry->getFilename(), 'Authorization.php')) {
                $files[] = $entry->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
