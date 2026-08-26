<?php

declare(strict_types=1);

namespace Tests\v3\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\v3\TestCase;

final class EventCommandArchitectureV3Test extends TestCase
{
    public function test_event_command_is_a_read_only_composition_without_new_bounded_context(): void
    {
        self::assertDirectoryDoesNotExist(base_path('app/Contexts/EventReadiness'));
        self::assertDirectoryDoesNotExist(base_path('app/Contexts/EventCloseout'));

        $sources = $this->eventCommandSources();
        self::assertNotEmpty($sources);

        foreach ([
            ' extends Model',
            '\\Actions\\',
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
        ] as $needle) {
            foreach ($sources as $path => $source) {
                self::assertStringNotContainsString(
                    $needle,
                    $source,
                    $path.' must remain a navigation-only read composition; forbidden token: '.$needle,
                );
            }
        }
    }

    public function test_event_command_derived_truth_is_not_persisted_in_application_or_schema(): void
    {
        $sources = array_merge(
            $this->phpSources(base_path('app')),
            $this->phpSources(base_path('database/migrations')),
        );

        foreach (['event_ready', 'event_complete', 'readiness_lifecycle', 'closeout_lifecycle'] as $needle) {
            foreach ($sources as $path => $source) {
                self::assertStringNotContainsString(
                    $needle,
                    $source,
                    $path.' must not persist derived Event Command truth: '.$needle,
                );
            }
        }
    }

    public function test_owner_contexts_do_not_depend_on_event_management_read_model(): void
    {
        foreach ($this->phpSources(base_path('app/Contexts')) as $path => $source) {
            self::assertStringNotContainsString(
                'App\\ReadModels\\EventManagement',
                $source,
                $path.' must not import the EventManagement read model.',
            );
        }
    }

    public function test_event_command_composes_owner_queries_and_not_owner_actions(): void
    {
        $sources = $this->eventCommandSources();
        $combined = implode("\n", $sources);

        foreach ([
            'EventParticipationQuery',
            'EventPhasePollQuery',
            'EventRosterQuery',
            'EventBattlePlanCommandQuery',
            'EventRallyCommandQuery',
            'EventResultCommandQuery',
            'EventTerritoryCommandQuery',
            'EventStrategyCommandQuery',
            'EventReminderCommandQuery',
            'EventDeliveryHealthQuery',
            'EventEvidenceCommandQuery',
            'EventDebriefAvailabilityQuery',
        ] as $ownerQuery) {
            self::assertStringContainsString(
                $ownerQuery,
                $combined,
                'Event Command must compose the bounded owner query: '.$ownerQuery,
            );
        }

        self::assertStringNotContainsString('\\Actions\\', $combined);
    }

    public function test_event_command_frontend_has_no_domain_write_request(): void
    {
        $source = file_get_contents(base_path('resources/js/components/events/EventCommandCard.vue'));
        self::assertIsString($source);

        foreach (['router.post(', 'router.put(', 'router.patch(', 'router.delete(', 'fetch('] as $needle) {
            self::assertStringNotContainsString(
                $needle,
                $source,
                'Event Command UI may navigate only; forbidden write request: '.$needle,
            );
        }

        self::assertStringContainsString('router.get(', $source);
        self::assertStringContainsString('item.handoff.href', $source);
        self::assertStringContainsString('events.command.owner', $source);
        self::assertStringContainsString('aria-live="polite"', $source);
    }

    /** @return array<string, string> */
    private function eventCommandSources(): array
    {
        return array_filter(
            $this->phpSources(base_path('app/ReadModels/EventManagement')),
            static fn (string $path): bool => str_contains(basename($path), 'EventCommand'),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /** @return array<string, string> */
    private function phpSources(string $directory): array
    {
        $sources = [];
        if (! is_dir($directory)) {
            return $sources;
        }

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
