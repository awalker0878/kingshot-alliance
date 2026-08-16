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

    public function test_contribution_mutations_are_player_scoped(): void
    {
        $source = (string) file_get_contents(
            $this->root().'/app/Contexts/Intelligence/Contributions/Actions/RecordContribution.php',
        );

        self::assertStringContainsString('Player $actor', $source);
        self::assertStringContainsString('Player $player', $source);
        self::assertStringContainsString("->where('player_id',", $source);
        self::assertStringContainsString("'recorded_by_player_id' => $context->actor->id", $source);
        self::assertStringNotContainsString('User $user', $source);
    }

    public function test_contribution_history_is_exact_player_scoped(): void
    {
        $source = (string) file_get_contents(
            $this->root().'/app/Contexts/Intelligence/Contributions/Queries/PlayerContributionHistoryQuery.php',
        );

        self::assertStringContainsString('forPlayer(Player $player', $source);
        self::assertStringContainsString("->where('player_id', $player->id)", $source);
        self::assertStringContainsString('summaryForPlayer(Player $player)', $source);
        self::assertStringNotContainsString('user_id', $source);
    }

    public function test_foundation_and_operations_contexts_do_not_import_intelligence_contributions(): void
    {
        foreach (['Accounts', 'GameWorld', 'Alliance', 'Operations'] as $context) {
            foreach ($this->phpFiles($this->root().'/app/Contexts/'.$context) as $file) {
                self::assertStringNotContainsString(
                    'App\\Contexts\\Intelligence\\Contributions\\',
                    (string) file_get_contents($file),
                    $file.' must not depend upward on Intelligence Contributions.',
                );
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
