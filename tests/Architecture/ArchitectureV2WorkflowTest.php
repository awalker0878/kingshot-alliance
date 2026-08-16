<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class ArchitectureV2WorkflowTest extends TestCase
{
    public function test_p8_removes_the_final_v1_runtime_root(): void
    {
        self::assertDirectoryDoesNotExist($this->root().'/app/Domain');
        self::assertDirectoryExists($this->root().'/app/Workflows/KingdomTransfer');
        self::assertDirectoryExists($this->root().'/app/Workflows/PlayerContext');
        self::assertDirectoryExists($this->root().'/app/ReadModels/KingdomSettings');
    }

    public function test_transfer_workflow_does_not_directly_persist_game_world_players(): void
    {
        foreach ($this->phpFiles($this->root().'/app/Workflows/KingdomTransfer') as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source);
            self::assertStringNotContainsString('Player::query()->create(', $source, $file);
            self::assertDoesNotMatchRegularExpression('/\$[A-Za-z_][A-Za-z0-9_]*->forceFill\([^;]*current_kingdom_id/s', $source, $file);
        }
    }

    public function test_roster_resolution_delegates_player_persistence_to_game_world(): void
    {
        $source = file_get_contents($this->root().'/app/Contexts/Intelligence/Roster/Actions/ResolvePlayer.php');
        self::assertIsString($source);
        self::assertStringContainsString('PersistPlayerIdentity', $source);
        self::assertStringNotContainsString('Player::query()->create(', $source);
        self::assertStringNotContainsString('->forceFill(', $source);
    }

    public function test_alliance_invitation_claims_player_through_game_world_api(): void
    {
        $source = file_get_contents($this->root().'/app/Contexts/Alliance/Membership/Actions/AcceptInvitation.php');
        self::assertIsString($source);
        self::assertStringContainsString('ClaimPlayerAccount', $source);
        self::assertStringNotContainsString('forceFill([\'user_id\'', $source);
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
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

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
