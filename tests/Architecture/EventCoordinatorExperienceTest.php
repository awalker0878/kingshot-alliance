<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class EventCoordinatorExperienceTest extends TestCase
{
    public function test_event_coordinator_uses_shared_shell_and_preserves_existing_management_contracts(): void
    {
        $source = $this->read('resources/js/pages/Alliance/Events/Manage.vue');

        self::assertStringContainsString("import AppLayout from '../../../layouts/AppLayout.vue';", $source);
        self::assertStringContainsString("import { useLocale } from '../../../localization';", $source);
        self::assertStringContainsString('<AppLayout', $source);
        self::assertStringNotContainsString('<main', $source);

        foreach ([
            ".post('/alliance/events'",
            ".post('/alliance/event-templates'",
            ".post('/alliance/event-templates/events'",
            '.post(`/alliance/events/${reminderForm.event_id}/reminders`',
            ".post('/alliance/rally-guidance'",
            '.post(`/alliance/events/${formationForm.occurrence_id}/formations`',
            '.post(`/alliance/events/${groupForm.occurrence_id}/rally-groups`',
            '.put(`/alliance/rally-groups/${assignmentForm.group_id}/assignments`',
            '`/alliance/events/${occurrenceId}/registrations/${registrationId}/attendance`',
            '`/alliance/rally-assignments/${assignmentId}/participation`',
        ] as $contract) {
            self::assertStringContainsString($contract, $source, $contract);
        }

        foreach ([
            'Reports & Export',
            'Audit Log',
            'Duplicate Event',
            'Archive Event',
            'Delete Event',
            'Bulk Actions',
            'Export Report',
            'Real-time overview',
            'Discord sync',
            'Google Calendar sync',
        ] as $unsupported) {
            self::assertStringNotContainsString($unsupported, $source, $unsupported);
        }
    }

    public function test_event_coordinator_catalogue_covers_every_supported_locale(): void
    {
        $messages = $this->read('resources/js/localization/messages/event-coordinator.ts');
        $index = $this->read('resources/js/localization/messages/index.ts');

        foreach ($this->locales() as $locale) {
            self::assertMatchesRegularExpression(
                '/(?:^|\\s)[\'\"]?'.preg_quote($locale, '/').'[\'\"]?\\s*:/m',
                $messages,
                $locale,
            );
        }

        self::assertStringContainsString('Record<LocaleCode, EventCoordinatorTree>', $messages);
        self::assertStringContainsString("import { eventCoordinatorMessages } from './event-coordinator';", $index);
        self::assertStringContainsString('...eventCoordinatorMessages[locale]', $index);
    }

    public function test_event_coordinator_controller_only_adds_authenticated_shell_identity_to_presentation_payload(): void
    {
        $source = $this->read('app/Domain/Events/Http/Controllers/EventManagementController.php');

        self::assertStringContainsString("Inertia::render('Alliance/Events/Manage'", $source);
        self::assertStringContainsString('\'name\' => (string) $user->name', $source);
        self::assertStringContainsString('\'email\' => (string) $user->email', $source);
        self::assertStringContainsString('PermissionKey::EventManage', $source);
    }

    /** @return list<string> */
    private function locales(): array
    {
        return [
            'en',
            'ar',
            'de',
            'es',
            'fr',
            'id',
            'it',
            'ja',
            'ko',
            'pl',
            'pt-BR',
            'ru',
            'th',
            'tr',
            'vi',
            'zh-CN',
            'zh-TW',
        ];
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
