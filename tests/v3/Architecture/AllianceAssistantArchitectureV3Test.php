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
