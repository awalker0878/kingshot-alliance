<?php

declare(strict_types=1);

namespace Tests\v3\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\v3\TestCase;

final class AllianceAssistantArchitectureV3Test extends TestCase
{
    public function test_assistant_read_model_has_no_persistence_or_domain_write_path(): void
    {
        $sources = $this->phpSources(base_path('app/ReadModels/AllianceAssistant'));
        self::assertNotEmpty($sources);

        $forbidden = [
            ' extends Model',
            'Migration',
            '->save(',
            '->delete(',
            '->update(',
            '::create(',
            '::insert(',
            '::upsert(',
            'DB::statement(',
            'DB::insert(',
            'DB::update(',
            'DB::delete(',
            '\\Actions\\',
        ];

        foreach ($sources as $path => $source) {
            foreach ($forbidden as $needle) {
                self::assertStringNotContainsString(
                    $needle,
                    $source,
                    $path.' must remain a read-only composition surface; forbidden token: '.$needle,
                );
            }
        }
    }

    public function test_owner_contexts_do_not_depend_on_alliance_assistant_read_model(): void
    {
        foreach ($this->phpSources(base_path('app/Contexts')) as $path => $source) {
            self::assertStringNotContainsString(
                'App\\ReadModels\\AllianceAssistant',
                $source,
                $path.' must not import the Alliance Assistant read model.',
            );
        }
    }

    public function test_initial_release_contains_no_external_model_or_http_provider_client(): void
    {
        $forbidden = [
            'Illuminate\\Support\\Facades\\Http',
            'GuzzleHttp\\',
            'OpenAI',
            'Anthropic',
            'curl_',
        ];

        foreach ($this->phpSources(base_path('app/ReadModels/AllianceAssistant')) as $path => $source) {
            foreach ($forbidden as $needle) {
                self::assertStringNotContainsString(
                    $needle,
                    $source,
                    $path.' must not send Assistant questions or evidence to an external provider.',
                );
            }
        }
    }

    public function test_gameworld_extension_composes_only_narrow_owner_queries(): void
    {
        $path = base_path('app/ReadModels/AllianceAssistant/Queries/AllianceAssistantQuery.php');
        $source = file_get_contents($path);
        self::assertIsString($source);

        foreach ([
            'ProgressionFactQuery',
            'EventParticipationQuery',
            'PlayerBattlePlanQuery',
            'TransferSelfEligibilityQuery',
            'PublishedEventTerritoryRevisionQuery',
        ] as $requiredOwnerQuery) {
            self::assertStringContainsString(
                $requiredOwnerQuery,
                $source,
                'Alliance Assistant must compose the narrow owner projection: '.$requiredOwnerQuery,
            );
        }

        foreach ([
            'TransferParticipantQuery',
            'TransferPlanQuery',
            'TerritoryPlanQuery',
            'EventObjectiveAssignment',
            '->management(',
        ] as $forbiddenManagementSurface) {
            self::assertStringNotContainsString(
                $forbiddenManagementSurface,
                $source,
                'Alliance Assistant must not import/query a broad management surface: '.$forbiddenManagementSurface,
            );
        }
    }

    public function test_gameworld_extension_preserves_all_nine_bounded_discovery_prompts(): void
    {
        $source = file_get_contents(base_path('resources/js/pages/Assistant/Index.vue'));
        self::assertIsString($source);

        foreach ([
            'swordland_roster',
            'next_event',
            'bear_hunt_guide',
            'observation',
            'hero_fact',
            'rsvp_week',
            'battle_assignment',
            'transfer_status',
            'territory_plan',
        ] as $prompt) {
            self::assertSame(
                2,
                substr_count($source, "'{$prompt}'"),
                'The Assistant prompt contract must keep exactly one type member and one default discovery entry for '.$prompt.'.',
            );
        }

        self::assertStringContainsString('ids.slice(0, 9)', $source);
    }

    public function test_assistant_extension_localization_is_typed_for_every_locale_and_lazy_loaded(): void
    {
        $loader = file_get_contents(base_path('resources/js/localization/loader.ts'));
        $extension = file_get_contents(base_path('resources/js/localization/assistant-gameworld-extension.ts'));
        $transferLabels = file_get_contents(base_path('resources/js/localization/assistant-transfer-labels.ts'));
        self::assertIsString($loader);
        self::assertIsString($extension);
        self::assertIsString($transferLabels);

        self::assertStringContainsString("import('./assistant-gameworld-extension')", $loader);
        self::assertStringContainsString("import('./assistant-transfer-labels')", $loader);
        self::assertStringNotContainsString("from './assistant-gameworld-extension'", $loader);
        self::assertStringNotContainsString("from './assistant-transfer-labels'", $loader);
        self::assertStringContainsString(
            'satisfies Record<NonEnglishLocale, AssistantExtensionStrings>',
            $extension,
        );
        self::assertStringContainsString('satisfies Record<LocaleCode, TransferLabels>', $transferLabels);
    }

    /** @return array<string, string> */
    private function phpSources(string $directory): array
    {
        $sources = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents);
            $sources[$file->getPathname()] = $contents;
        }

        ksort($sources);

        return $sources;
    }
}
