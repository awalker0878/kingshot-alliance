<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class ArchitectureV2IntelligenceTest extends TestCase
{
    public function test_contributions_v1_runtime_is_deleted(): void
    {
        self::assertDirectoryDoesNotExist($this->root().'/app/Domain/Contributions');
        self::assertDirectoryExists($this->root().'/app/Contexts/Intelligence/Contributions');
    }

    public function test_observation_foundation_diplomacy_and_sharing_are_owned_by_intelligence(): void
    {
        self::assertDirectoryExists($this->root().'/app/Contexts/Intelligence/Observations');
        self::assertDirectoryExists($this->root().'/app/Contexts/Intelligence/Diplomacy');
        self::assertDirectoryExists($this->root().'/app/Contexts/Intelligence/Sharing');

        foreach ([
            'Models/TrackedKingdomAlliance.php',
            'Models/KingdomAllianceObservation.php',
            'Models/KingdomAllianceDiplomacy.php',
            'Models/KingdomAllianceDiplomacyContact.php',
            'Models/KingdomAllianceDiplomacyTransition.php',
            'Models/KingdomIntelligenceShare.php',
            'Models/KingdomIntelligenceShareTarget.php',
            'Actions/TransitionKingdomAllianceDiplomacy.php',
            'Actions/SaveKingdomAllianceDiplomacyContact.php',
            'Actions/DeactivateKingdomAllianceDiplomacyContact.php',
            'Actions/CreateKingdomIntelligenceShareInvitation.php',
            'Actions/AcceptKingdomIntelligenceShareInvitation.php',
            'Actions/AddKingdomIntelligenceShareTarget.php',
            'Actions/RemoveKingdomIntelligenceShareTarget.php',
            'Actions/RevokeKingdomIntelligenceShare.php',
            'Queries/KingdomAllianceDiplomacyQuery.php',
            'Queries/KingdomAllianceDiplomacyContactQuery.php',
            'Queries/KingdomIntelligenceSharingManageQuery.php',
            'Http/Controllers/KingdomAllianceDiplomacyController.php',
            'Http/Controllers/KingdomAllianceDiplomacyContactController.php',
            'Http/Controllers/KingdomAllianceController.php',
            'Http/Controllers/KingdomAllianceObservationController.php',
            'Http/Controllers/KingdomAllianceIntelligenceController.php',
            'Http/Controllers/KingdomIntelligenceSharingController.php',
            'Queries/KingdomAllianceIntelligenceQuery.php',
            'Queries/SharedKingdomIntelligenceCurrentQuery.php',
            'Queries/SharedKingdomIntelligenceHistoryQuery.php',
            'Services/KingdomAllianceIntelligence.php',
            'Services/SharedKingdomIntelligenceHistoryCursor.php',
        ] as $legacyPath) {
            self::assertFileDoesNotExist($this->root().'/app/Domain/Kingdoms/'.$legacyPath);
        }
    }

    public function test_production_runtime_has_no_v1_contributions_namespace_references(): void
    {
        foreach (['app', 'routes', 'bootstrap', 'config', 'database'] as $root) {
            foreach ($this->phpFiles($this->root().'/'.$root) as $file) {
                self::assertStringNotContainsString(
                    'App\\Domain\\Contributions',
                    (string) file_get_contents($file),
                    $file.' still references the deleted V1 Contributions namespace.',
                );
            }
        }
    }

    public function test_production_runtime_has_no_bogus_game_world_intelligence_model_references(): void
    {
        foreach (['app', 'routes', 'bootstrap', 'config', 'database'] as $root) {
            foreach ($this->phpFiles($this->root().'/'.$root) as $file) {
                $source = (string) file_get_contents($file);
                foreach ([
                    'KingdomAllianceDiplomacy',
                    'KingdomAllianceDiplomacyContact',
                    'KingdomAllianceDiplomacyTransition',
                    'KingdomIntelligenceShare',
                    'KingdomIntelligenceShareTarget',
                ] as $model) {
                    self::assertStringNotContainsString(
                        'App\\Contexts\\GameWorld\\Models\\'.$model,
                        $source,
                        $file.' still assigns Intelligence state to GameWorld.',
                    );
                }
            }
        }
    }

    public function test_contribution_mutations_are_player_scoped(): void
    {
        $source = (string) file_get_contents(
            $this->root().'/app/Contexts/Intelligence/Contributions/Actions/RecordContribution.php',
        );

        self::assertStringContainsString('Player $actor', $source);
        self::assertStringContainsString('Player $player', $source);
        self::assertStringContainsString("->where('player_id',", $source);
        self::assertStringContainsString("'recorded_by_player_id' => \$context->actor->id", $source);
        self::assertStringNotContainsString('User $user', $source);
    }

    public function test_contribution_history_is_exact_player_scoped(): void
    {
        $source = (string) file_get_contents(
            $this->root().'/app/Contexts/Intelligence/Contributions/Queries/PlayerContributionHistoryQuery.php',
        );

        self::assertStringContainsString('forPlayer(Player $player', $source);
        self::assertStringContainsString("->where('player_id', \$player->id)", $source);
        self::assertStringContainsString('summaryForPlayer(Player $player)', $source);
        self::assertStringNotContainsString('user_id', $source);
    }

    public function test_sharing_mutations_are_player_scoped_and_capture_kingdom(): void
    {
        $create = (string) file_get_contents(
            $this->root().'/app/Contexts/Intelligence/Sharing/Actions/CreateKingdomIntelligenceShareInvitation.php',
        );
        $accept = (string) file_get_contents(
            $this->root().'/app/Contexts/Intelligence/Sharing/Actions/AcceptKingdomIntelligenceShareInvitation.php',
        );

        self::assertStringContainsString('Player $actor', $create);
        self::assertStringContainsString("'invited_by_player_id' => \$actor->id", $create);
        self::assertStringContainsString("'kingdom_id' => \$source->kingdom_id", $create);
        self::assertStringContainsString('Player $actor', $accept);
        self::assertStringContainsString("'accepted_by_player_id' => \$actor->id", $accept);
        self::assertStringNotContainsString('User $user', $create);
        self::assertStringNotContainsString('User $user', $accept);
    }

    public function test_foundation_alliance_and_operations_contexts_do_not_import_intelligence_capabilities(): void
    {
        foreach (['Accounts', 'GameWorld', 'Alliance', 'Operations'] as $context) {
            foreach ($this->phpFiles($this->root().'/app/Contexts/'.$context) as $file) {
                $source = (string) file_get_contents($file);
                foreach (['Contributions', 'Observations', 'Diplomacy', 'Sharing'] as $capability) {
                    self::assertStringNotContainsString(
                        'App\\Contexts\\Intelligence\\'.$capability.'\\',
                        $source,
                        $file.' must not depend upward on Intelligence '.$capability.'.',
                    );
                }
            }
        }
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
