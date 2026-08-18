from pathlib import Path
import re

path = Path('resources/js/pages/Operations/Events/Manage.vue')
text = path.read_text()

imports = """import { ref } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import AppLayout from '@/layouts/AppLayout.vue';"""
text = text.replace(
    "import { ref } from 'vue';\n\nimport AppLayout from '@/layouts/AppLayout.vue';",
    imports,
    1,
)

header = """<RoomBanner
        :eyebrow="t('events.manage.eyebrow')"
        :title="event.title || t(event.nameKey)"
        :subtitle="`${t(`events.scope.${event.scope}`)} · ${event.timezone}`"
        image="/images/kingshot/v4/event-command.svg"
        compact
      >
        <template #actions>
          <Link href="/events" class="ks-command-link" data-variant="secondary">
            ← {{ t('events.manage.back') }}
          </Link>
          <Link
            v-if="event.occurrences[0]"
            :href="`/events/${event.occurrences[0].id}`"
            class="ks-command-link"
          >
            {{ t('events.calendar.agenda') }}
          </Link>
        </template>
      </RoomBanner>

      <section class="mt-4 grid gap-3 sm:grid-cols-2 2xl:grid-cols-4" aria-label="Event command summary">
        <StatSeal :label="t('events.manage.occurrences')" :value="event.occurrences.length" icon="▦" />
        <StatSeal :label="t('events.manage.participants')" :value="participants.length" icon="♟" tone="teal" />
        <StatSeal :label="t('events.manage.reminders')" :value="reminderRules.length" icon="⌛" tone="stone" />
        <StatSeal :label="t('events.calendar.viewOptions')" :value="event.capabilities.length" icon="✦" />
      </section>

      <nav class="ks-tab-strip mt-4" aria-label="Event organizer command sections">
        <a href="#schedule" class="ks-tab">{{ t('events.manage.title') }}</a>
        <a v-if="event.capabilities.includes('phases')" href="#phases" class="ks-tab">{{ t('events.phases.manageTitle') }}</a>
        <a v-if="event.capabilities.includes('polls')" href="#polls" class="ks-tab">{{ t('events.polls.manageTitle') }}</a>
        <a v-if="event.capabilities.includes('rosters')" href="#rosters" class="ks-tab">{{ t('events.rosters.manageTitle') }}</a>
        <a v-if="event.capabilities.includes('rally_guidance') || event.capabilities.includes('formations')" href="#rallies" class="ks-tab">{{ t('events.rallies.manageTitle') }}</a>
        <a v-if="event.capabilities.includes('objectives')" href="#battle-plan" class="ks-tab">{{ t('events.objectives.manageTitle') }}</a>
        <a v-if="event.capabilities.includes('results')" href="#results" class="ks-tab">{{ t('events.results.manageTitle') }}</a>
        <a v-if="event.capabilities.includes('responses') || event.capabilities.includes('registration') || event.capabilities.includes('attendance')" href="#participants" class="ks-tab">{{ t('events.manage.participants') }}</a>
        <a href="#reminders" class="ks-tab">{{ t('events.manage.reminders') }}</a>
      </nav>"""

text, count = re.subn(
    r'<header class="mb-7 flex flex-wrap items-end justify-between gap-4">.*?</header>',
    header,
    text,
    count=1,
    flags=re.S,
)
if count != 1:
    raise SystemExit('Event Manage header replacement did not match exactly once.')

text = text.replace('class="mx-auto max-w-6xl"', 'class="mx-auto max-w-[94rem]"', 1)
text = text.replace(
    'class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]"',
    'id="schedule" class="mt-5 grid scroll-mt-28 gap-5 2xl:grid-cols-[minmax(0,1.35fr)_minmax(20rem,.65fr)]"',
    1,
)

# Move the dense operational surface onto the V4 semantic component language.
replacements = {
    'space-y-5 rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] bg-[var(--ks-surface-1)] p-6': 'ks-surface-gold space-y-5 p-5 sm:p-6',
    'rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] bg-[var(--ks-surface-1)] p-5': 'ks-surface p-5',
    'rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-surface-1)] p-4': 'ks-surface p-4',
    'space-y-3 rounded border border-[var(--ks-border)] p-3': 'ks-surface space-y-3 p-4',
    'space-y-3 rounded border border-[var(--ks-border)] p-4': 'ks-surface space-y-3 p-4',
    'rounded border border-[var(--ks-border)] p-4': 'ks-surface p-4',
    'rounded border border-[var(--ks-border)] p-3': 'ks-surface p-3',
    'rounded border border-[var(--ks-border)] p-2': 'rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 p-2',
    'rounded bg-[var(--ks-surface-2)] p-3': 'rounded-[var(--ks-radius-sm)] border border-[var(--ks-border-quiet)] bg-black/15 p-3',
    'rounded bg-[var(--ks-surface-2)] p-2': 'rounded-[var(--ks-radius-sm)] border border-[var(--ks-border-quiet)] bg-black/15 p-2',
    'rounded border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-3 py-2 text-sm disabled:opacity-60': 'ks-input text-sm disabled:opacity-60',
    'rounded border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-3 py-2 disabled:opacity-60': 'ks-input disabled:opacity-60',
    'rounded border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-3 py-2 text-sm': 'ks-input text-sm',
    'rounded border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-3 py-2': 'ks-input',
    'rounded border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-2 py-2 text-sm': 'ks-input text-sm',
    'rounded border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-2 py-2 text-xs': 'ks-input text-xs',
    'rounded bg-[var(--ks-gold)] px-5 py-2.5 font-bold text-[var(--ks-ink)]': 'ks-command-button',
    'w-full rounded bg-[var(--ks-gold)] px-3 py-2 text-sm font-bold text-[var(--ks-ink)]': 'ks-command-button w-full',
    'w-full rounded bg-[var(--ks-blue-soft)] px-3 py-2 text-sm font-semibold text-[var(--ks-blue-strong)]': 'ks-command-button w-full',
    'rounded bg-[var(--ks-blue-soft)] px-4 py-2 text-sm font-semibold text-[var(--ks-blue-strong)]': 'ks-command-button',
    'mt-3 w-full rounded bg-[var(--ks-blue-soft)] px-4 py-2 text-sm font-semibold text-[var(--ks-blue-strong)] disabled:opacity-50': 'ks-command-button mt-3 w-full disabled:opacity-50',
    'rounded border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold': 'ks-command-link',
    'text-xs font-bold tracking-[0.15em] text-[var(--ks-gold)] uppercase': 'ks-kicker',
    'text-xs font-bold tracking-[0.15em] text-[var(--ks-blue-strong)] uppercase': 'ks-kicker',
}
for old, new in replacements.items():
    text = text.replace(old, new)

# Section anchors make the large console predictable and keyboard-friendly.
anchors = [
    ("<section\n        v-if=\"event.capabilities.includes('phases')\"", "<section\n        id=\"phases\"\n        v-if=\"event.capabilities.includes('phases')\""),
    ("<section\n        v-if=\"event.capabilities.includes('polls')\"", "<section\n        id=\"polls\"\n        v-if=\"event.capabilities.includes('polls')\""),
    ("<section\n        v-if=\"event.capabilities.includes('rosters')\"", "<section\n        id=\"rosters\"\n        v-if=\"event.capabilities.includes('rosters')\""),
    ("<section\n        v-if=\"\n          event.capabilities.includes('rally_guidance') || event.capabilities.includes('formations')\n        \"", "<section\n        id=\"rallies\"\n        v-if=\"\n          event.capabilities.includes('rally_guidance') || event.capabilities.includes('formations')\n        \""),
    ("<section\n        v-if=\"event.capabilities.includes('objectives')\"", "<section\n        id=\"battle-plan\"\n        v-if=\"event.capabilities.includes('objectives')\""),
    ("<section\n        v-if=\"event.capabilities.includes('results')\"", "<section\n        id=\"results\"\n        v-if=\"event.capabilities.includes('results')\""),
    ("<section\n        v-if=\"\n          event.capabilities.includes('responses') ||", "<section\n        id=\"participants\"\n        v-if=\"\n          event.capabilities.includes('responses') ||"),
]
for old, new in anchors:
    if old not in text:
        raise SystemExit(f'Missing anchor target: {old[:80]}')
    text = text.replace(old, new, 1)

# The final unconditional section is the reminder command surface.
reminder_marker = """<section
        class="mt-5 ks-surface p-5"
      >
        <p class="ks-kicker">
          {{ t('events.manage.reminderEyebrow') }}
        </p>"""
if reminder_marker in text:
    text = text.replace(
        reminder_marker,
        reminder_marker.replace('<section\n', '<section\n        id="reminders"\n'),
        1,
    )
else:
    # Prettier/class ordering can vary; anchor by the reminder eyebrow.
    idx = text.find("{{ t('events.manage.reminderEyebrow') }}")
    if idx == -1:
        raise SystemExit('Reminder section marker not found.')
    section_idx = text.rfind('<section', 0, idx)
    text = text[:section_idx] + text[section_idx:].replace('<section', '<section id="reminders"', 1)

# Make every major section land below the sticky app chrome.
text = text.replace('class="mt-5 ks-surface p-5"', 'class="ks-surface mt-5 scroll-mt-28 p-5"')
text = text.replace('class="ks-surface mt-5 scroll-mt-28 p-5"', 'class="ks-surface mt-5 scroll-mt-28 p-5"')

# Destructive actions remain visually distinct without leaking arbitrary page-local styling elsewhere.
text = text.replace(
    'class="w-full rounded border border-red-500/30 bg-red-500/10 px-4 py-2 text-sm font-semibold text-red-200"',
    'class="w-full rounded-[var(--ks-radius-sm)] border border-red-400/30 bg-red-500/10 px-4 py-3 text-sm font-semibold text-red-100 transition hover:border-red-300/50"',
)

path.write_text(text)
