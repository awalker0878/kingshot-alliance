<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class AllianceOperationsExperienceTest extends TestCase
{
    public function test_alliance_overview_uses_shared_shell_and_preserves_existing_actions(): void
    {
        $source = $this->read('resources/js/pages/Alliance/Overview.vue');

        self::assertStringContainsString("import AppLayout from '../../layouts/AppLayout.vue';", $source);
        self::assertStringContainsString("import { useLocale } from '../../localization';", $source);
        self::assertStringContainsString('<AppLayout', $source);
        self::assertStringNotContainsString('<main', $source);

        foreach ([
            "inviteForm.post('/alliance/invitations'",
            'router.post(`/alliance/invitations/${id}/resend`',
            'router.delete(`/alliance/invitations/${id}`',
            'router.patch(',
            '`/alliance/memberships/${id}/status`',
            '`/alliance/memberships/${membershipId}/roles/${roleId}`',
            "router.delete('/alliance/membership')",
            'href="/alliance/events"',
            'href="/alliance/content"',
            'href="/alliance/contributions"',
        ] as $contract) {
            self::assertStringContainsString($contract, $source, $contract);
        }

        foreach ([
            'Alliance power',
            'Activity feed',
            'Direct messages',
            'Global notifications',
            'Win rate',
            'Online members',
        ] as $unsupported) {
            self::assertStringNotContainsString($unsupported, $source, $unsupported);
        }
    }

    public function test_event_calendar_uses_shared_shell_and_preserves_registration_contract(): void
    {
        $source = $this->read('resources/js/pages/Alliance/Events/Index.vue');

        self::assertStringContainsString("import AppLayout from '../../../layouts/AppLayout.vue';", $source);
        self::assertStringContainsString("import { useLocale } from '../../../localization';", $source);
        self::assertStringContainsString('<AppLayout', $source);
        self::assertStringNotContainsString('<main', $source);
        self::assertStringContainsString('router.post(`/alliance/events/${id}/registration`', $source);
        self::assertStringContainsString('router.delete(`/alliance/events/${id}/registration`', $source);
        self::assertStringContainsString('exports.csvUrl', $source);
        self::assertStringContainsString('exports.icalUrl', $source);
        self::assertStringContainsString('href="/alliance/events/manage"', $source);
        self::assertStringContainsString(':href="`/alliance/events/${event.id}`"', $source);
        self::assertStringContainsString('eventReminders', $source);

        foreach ([
            'calendar/create',
            'calendar/drag',
            'Google Calendar sync',
            'Discord event sync',
        ] as $unsupported) {
            self::assertStringNotContainsString($unsupported, $source, $unsupported);
        }
    }

    public function test_event_detail_uses_shared_shell_and_only_existing_event_capabilities(): void
    {
        $source = $this->read('resources/js/pages/Alliance/Events/Show.vue');

        self::assertStringContainsString("import AppLayout from '../../../layouts/AppLayout.vue';", $source);
        self::assertStringContainsString("import { useLocale } from '../../../localization';", $source);
        self::assertStringContainsString('<AppLayout', $source);
        self::assertStringNotContainsString('<main', $source);

        foreach ([
            'router.post(`/alliance/events/${props.event.id}/registration`',
            'router.delete(`/alliance/events/${props.event.id}/registration`',
            ".post('/alliance/formations'",
            'recommendedFormations',
            'rallyGroups',
            'savedFormations',
            'event.registeredCount',
            'event.waitlistedCount',
            'event.instructions',
            'href="/alliance/events/manage"',
        ] as $contract) {
            self::assertStringContainsString($contract, $source, $contract);
        }

        foreach ([
            'Invite Others',
            'Manage Reminders',
            'Share Event Link',
            'Contact Organizer',
            'Attendance tab',
            'Activity tab',
            'Google Calendar',
            'Discord event',
        ] as $unsupported) {
            self::assertStringNotContainsString($unsupported, $source, $unsupported);
        }
    }

    public function test_alliance_operations_catalogue_covers_every_supported_locale(): void
    {
        $messages = $this->read('resources/js/localization/messages/alliance-operations.ts');
        $index = $this->read('resources/js/localization/messages/index.ts');

        foreach ($this->locales() as $locale) {
            self::assertMatchesRegularExpression(
                '/(?:^|\s)[\'\"]?'.preg_quote($locale, '/').'[\'\"]?\s*:/m',
                $messages,
                $locale,
            );
        }

        self::assertStringContainsString('Record<LocaleCode, AllianceOperationsTree>', $messages);
        self::assertStringContainsString("import { allianceOperationsMessages } from './alliance-operations';", $index);
        self::assertStringContainsString('...allianceOperationsMessages[locale]', $index);
    }

    public function test_event_detail_catalogue_covers_every_supported_locale(): void
    {
        $messages = $this->read('resources/js/localization/messages/event-detail.ts');
        $index = $this->read('resources/js/localization/messages/index.ts');

        foreach ($this->locales() as $locale) {
            self::assertMatchesRegularExpression(
                '/(?:^|\s)[\'\"]?'.preg_quote($locale, '/').'[\'\"]?\s*:/m',
                $messages,
                $locale,
            );
        }

        self::assertStringContainsString('Record<LocaleCode, EventDetailTree>', $messages);
        self::assertStringContainsString("import { eventDetailMessages } from './event-detail';", $index);
        self::assertStringContainsString('...eventDetailMessages[locale]', $index);
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
