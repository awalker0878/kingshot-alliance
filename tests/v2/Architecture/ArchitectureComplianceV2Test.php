<?php

declare(strict_types=1);

namespace Tests\v2\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\v2\TestCase;

final class ArchitectureComplianceV2Test extends TestCase
{
    public function test_pass_1_canonical_source_shape_has_no_compatibility_scaffolding(): void
    {
        self::assertSame(
            ['Accounts', 'Alliance', 'Communications', 'GameWorld', 'Intelligence', 'Operations', 'Platform'],
            $this->directories('app/Contexts', ['README.md']),
        );

        foreach ($this->files(['app', 'bootstrap', 'config', 'database', 'routes']) as $file) {
            $normalized = str_replace('\\', '/', $file);
            self::assertDoesNotMatchRegularExpression(
                '#/(?:Legacy|Compatibility|Compat|Shim)(?:/|[A-Z_.-])#',
                $normalized,
                $normalized,
            );

            if (str_ends_with($normalized, '.php')) {
                $source = (string) file_get_contents($file);
                self::assertStringNotContainsString('class_alias(', $source, $normalized);
                self::assertStringNotContainsString('App\\Domain\\', $source, $normalized);
            }
        }
    }

    public function test_pass_2_accounts_and_game_world_keep_user_and_player_identity_separate(): void
    {
        self::assertFileExists(base_path('app/Contexts/Accounts/Models/User.php'));
        self::assertFileExists(base_path('app/Contexts/GameWorld/Models/Player.php'));
        self::assertFileExists(base_path('app/Contexts/GameWorld/Services/PlayerContext.php'));

        $player = (string) file_get_contents(base_path('app/Contexts/GameWorld/Models/Player.php'));
        $context = (string) file_get_contents(base_path('app/Contexts/GameWorld/Services/PlayerContext.php'));

        self::assertStringContainsString('user_id', $player);
        self::assertStringContainsString('Player $player, User $user', $context);
        self::assertStringContainsString('The active Player must belong to the authenticated User.', $context);
    }

    public function test_pass_3_alliance_authority_is_player_membership_scoped(): void
    {
        $membership = (string) file_get_contents(base_path('app/Contexts/Alliance/Membership/Models/AllianceMembership.php'));
        $authorization = (string) file_get_contents(base_path('app/Contexts/Alliance/Access/Services/AllianceAuthorization.php'));

        self::assertStringContainsString('player_id', $membership);
        self::assertStringNotContainsString('user_id', $membership);
        self::assertStringContainsString("where('player_id', \$player->id)", $authorization);
        self::assertStringNotContainsString('PlatformAdministrator', $authorization);
    }

    public function test_pass_4_authorization_vocabulary_stays_with_its_owner(): void
    {
        self::assertDirectoryExists(base_path('app/Contexts/Alliance/Access'));
        self::assertDirectoryExists(base_path('app/Contexts/Operations/Access'));
        self::assertDirectoryExists(base_path('app/Contexts/Intelligence/Access'));
        self::assertDirectoryExists(base_path('app/Contexts/Platform/Access'));

        foreach ($this->phpFiles(['app/Contexts/Accounts', 'app/Contexts/Alliance', 'app/Contexts/Communications', 'app/Contexts/GameWorld', 'app/Contexts/Intelligence', 'app/Contexts/Operations']) as $file) {
            self::assertStringNotContainsString(
                'App\\Contexts\\Platform\\Access\\',
                (string) file_get_contents($file),
                $file,
            );
        }
    }

    public function test_pass_5_operations_contains_its_complete_coordination_surface(): void
    {
        self::assertSame(
            ['Access', 'BattlePlans', 'EventCore', 'KingPerks', 'Participation', 'Polls', 'Rallies', 'Reminders', 'Results', 'Rosters'],
            $this->directories('app/Contexts/Operations', ['README.md']),
        );
    }

    public function test_pass_6_intelligence_contains_observation_analysis_and_access_ownership(): void
    {
        self::assertSame(
            ['Access', 'Contributions', 'Diplomacy', 'EventAnalysis', 'Http', 'Ingestion', 'Observations', 'Roster', 'Sharing'],
            $this->directories('app/Contexts/Intelligence', ['README.md']),
        );
    }

    public function test_pass_7_communications_and_platform_have_distinct_responsibilities(): void
    {
        self::assertDirectoryExists(base_path('app/Contexts/Communications/Reminders'));
        self::assertDirectoryExists(base_path('app/Contexts/Platform/Access'));
        self::assertDirectoryExists(base_path('app/Contexts/Platform/EventAdministration'));
        self::assertDirectoryExists(base_path('app/Contexts/Platform/Integrations'));
    }

    public function test_pass_8_composition_and_shared_infrastructure_do_not_own_business_policy(): void
    {
        foreach ($this->phpFiles(['app/Shared']) as $file) {
            $source = (string) file_get_contents($file);
            self::assertStringNotContainsString('App\\Contexts\\', $source, $file);
            self::assertStringNotContainsString('App\\Workflows\\', $source, $file);
            self::assertStringNotContainsString('App\\ReadModels\\', $source, $file);
        }

        foreach ($this->phpFiles(['app/Contexts']) as $file) {
            self::assertStringNotContainsString('App\\Workflows\\', (string) file_get_contents($file), $file);
        }
    }

    public function test_pass_9_clean_room_tests_and_current_documentation_are_the_only_compliance_sources(): void
    {
        self::assertSame(['v2'], $this->directories('tests'));
        self::assertSame(
            ['architecture', 'codebase', 'governance', 'operations', 'product', 'reference'],
            $this->directories('docs', ['README.md']),
        );
        self::assertFileExists(base_path('docs/governance/architecture-compliance.md'));

        foreach ([
            'app/Contexts/README.md',
            'app/Shared/README.md',
            'app/Workflows/README.md',
            'app/ReadModels/README.md',
            'docs/README.md',
            'docs/codebase/testing.md',
            'docs/governance/documentation-standard.md',
        ] as $path) {
            $source = strtolower((string) file_get_contents(base_path($path)));
            foreach (['superseded', 'legacy', 'compatibility', 'previous implementation', 'app\\domain\\'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $path);
            }
        }
    }

    /** @param list<string> $ignored @return list<string> */
    private function directories(string $root, array $ignored = []): array
    {
        $entries = array_values(array_filter(
            scandir(base_path($root)) ?: [],
            static fn (string $entry): bool => ! in_array($entry, ['.', '..', ...$ignored], true),
        ));
        sort($entries);

        return $entries;
    }

    /** @param list<string> $roots @return list<string> */
    private function files(array $roots): array
    {
        $files = [];
        foreach ($roots as $root) {
            $path = base_path($root);
            if (! is_dir($path)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
            /** @var SplFileInfo $entry */
            foreach ($iterator as $entry) {
                if ($entry->isFile()) {
                    $files[] = $entry->getPathname();
                }
            }
        }
        sort($files);

        return $files;
    }

    /** @param list<string> $roots @return list<string> */
    private function phpFiles(array $roots): array
    {
        return array_values(array_filter(
            $this->files($roots),
            static fn (string $file): bool => str_ends_with($file, '.php'),
        ));
    }
}
