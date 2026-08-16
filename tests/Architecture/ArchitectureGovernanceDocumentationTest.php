<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class ArchitectureGovernanceDocumentationTest extends TestCase
{
    public function test_documentation_uses_the_canonical_reader_intent_structure(): void
    {
        $directories = $this->directories($this->root().'/docs');

        self::assertSame([
            'architecture',
            'codebase',
            'governance',
            'operations',
            'product',
            'reference',
        ], $directories);

        foreach (['domains', 'adr', 'security', 'contexts', 'legacy', 'wiki'] as $legacy) {
            self::assertDirectoryDoesNotExist($this->root().'/docs/'.$legacy);
        }
    }

    public function test_architecture_documents_the_v2_contexts_and_composition_layers(): void
    {
        $index = $this->read('docs/architecture/contexts/README.md');

        foreach (['Accounts', 'GameWorld', 'Alliance', 'Operations', 'Intelligence', 'Communications', 'Platform'] as $context) {
            self::assertStringContainsString($context, $index);
        }

        $overview = $this->read('docs/architecture/system-overview.md');
        foreach (['app/ReadModels', 'app/Workflows', 'app/Shared'] as $layer) {
            self::assertStringContainsString($layer, $overview);
        }
    }

    public function test_living_architecture_is_separate_from_decision_history(): void
    {
        $index = $this->read('docs/architecture/decisions/README.md');
        $decisions = glob($this->root().'/docs/architecture/decisions/[0-9][0-9][0-9][0-9]-*.md') ?: [];

        self::assertNotSame([], $decisions);

        foreach ($decisions as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source);
            self::assertMatchesRegularExpression('/^Status: (Proposed|Accepted|Superseded|Rejected)$/m', $source, $file);
            self::assertStringContainsString(basename($file), $index, basename($file).' is missing from the decision index.');
        }

        self::assertStringContainsString('living architecture', strtolower($index));
        self::assertStringContainsString('git history', strtolower($index));
    }

    public function test_authority_model_is_player_scoped_and_platform_admin_is_not_a_game_bypass(): void
    {
        $authority = $this->read('docs/architecture/authority-model.md');

        self::assertStringContainsString('active `Player` is the security principal for game-domain behavior', $authority);
        self::assertStringContainsString('never aggregated across every Player owned by one User', $authority);
        self::assertStringContainsString('does not bypass Alliance, Kingdom, Operations or Intelligence game authorization', $authority);
    }

    /** @return list<string> */
    private function directories(string $path): array
    {
        $entries = scandir($path);
        self::assertIsArray($entries);

        $directories = array_values(array_filter(
            $entries,
            static fn (string $entry): bool => $entry !== '.' && $entry !== '..' && is_dir($path.'/'.$entry),
        ));
        sort($directories);

        return $directories;
    }

    private function read(string $path): string
    {
        $source = file_get_contents($this->root().'/'.$path);
        self::assertIsString($source, $path);

        return $source;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
