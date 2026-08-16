<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class ApplicationExperienceTest extends TestCase
{
    public function test_dashboard_uses_the_shared_application_experience_and_active_player_context_without_invented_metrics(): void
    {
        $dashboard = $this->read('resources/js/pages/Dashboard.vue');

        self::assertStringContainsString("import AppLayout from '../layouts/AppLayout.vue';", $dashboard);
        self::assertStringContainsString("import { useLocale } from '../localization';", $dashboard);
        self::assertStringContainsString('<AppLayout', $dashboard);
        self::assertStringNotContainsString('<main', $dashboard);

        self::assertStringContainsString("allianceForm.post('/alliances'", $dashboard);
        self::assertStringContainsString('activePlayer: { id: string; name: string; gamePlayerId: string | null; kingdomNumber: number | null } | null;', $dashboard);
        self::assertStringContainsString(':has-player-alliance="membership !== null"', $dashboard);
        self::assertStringContainsString('{{ activePlayer.name }}', $dashboard);
        self::assertStringNotContainsString('router.put(`/alliances/${allianceId}/active`)', $dashboard);
        self::assertStringNotContainsString('activeAlliance', $dashboard);
        self::assertStringContainsString('href="/alliance"', $dashboard);
        self::assertStringContainsString('href="/alliance/roster"', $dashboard);
        self::assertStringContainsString('href="/alliance/kingdom-alliances"', $dashboard);
        self::assertStringContainsString('href="/alliance/transfers"', $dashboard);
        self::assertStringContainsString('href="/alliance/settings/kingdom"', $dashboard);

        foreach ([
            'Alliance power',
            'Recent activity',
            'Activity feed',
            'Notifications',
            'Events this week',
            'Rallies active',
        ] as $unsupported) {
            self::assertStringNotContainsString($unsupported, $dashboard, $unsupported);
        }
    }

    public function test_dashboard_catalogue_is_complete_for_every_supported_locale(): void
    {
        $root = dirname(__DIR__, 2);
        $english = file_get_contents($root.'/resources/js/localization/messages/core/en.ts');
        self::assertIsString($english);

        foreach (['en', 'ar', 'de', 'es', 'fr', 'id', 'it', 'ja', 'ko', 'pl', 'pt-BR', 'ru', 'th', 'tr', 'vi', 'zh-CN', 'zh-TW'] as $locale) {
            self::assertFileExists($root."/resources/js/localization/messages/core/{$locale}.ts");
        }

        self::assertStringContainsString('satisfies MessageCatalogue', $english);
        foreach (['application:'] as $required) {
            self::assertStringContainsString($required, $english, $required);
        }

        $registry = file_get_contents($root.'/resources/js/localization/registry.ts');
        self::assertIsString($registry);
        self::assertStringContainsString("'core'", $registry);
    }

    public function test_shared_application_input_and_command_link_tokens_are_available(): void
    {
        $css = $this->read('resources/css/app.css');

        self::assertStringContainsString('.ks-input {', $css);
        self::assertStringContainsString('.ks-command-link {', $css);
        self::assertStringContainsString('.ks-command-link:hover {', $css);
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
