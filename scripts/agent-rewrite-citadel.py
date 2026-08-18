from pathlib import Path
import re


def load(path: str) -> tuple[Path, str]:
    p = Path(path)
    return p, p.read_text()


def require_replace(text: str, old: str, new: str, label: str, count: int | None = None) -> str:
    actual = text.count(old)
    if actual == 0:
        raise SystemExit(f'{label}: expected pattern not found: {old[:100]!r}')
    if count is not None and actual != count:
        raise SystemExit(f'{label}: expected {count} matches, found {actual}: {old[:100]!r}')
    return text.replace(old, new)


# ---------------------------------------------------------------------------
# Citadel / Event Codex
# ---------------------------------------------------------------------------
path, text = load('resources/js/pages/Citadel/EventCodex/Index.vue')
text = require_replace(
    text,
    "import { Head, Link, useForm } from '@inertiajs/vue3';\n\nimport AppLayout from '@/layouts/AppLayout.vue';",
    "import { Head, Link, useForm } from '@inertiajs/vue3';\nimport { computed } from 'vue';\n\nimport RoomBanner from '@/components/game/RoomBanner.vue';\nimport StatSeal from '@/components/game/StatSeal.vue';\nimport AppLayout from '@/layouts/AppLayout.vue';",
    'EventCodex imports',
    1,
)

computed_block = """
const activeScopeCount = computed(() =>
  props.eventTypes.reduce(
    (total, type) => total + type.scopes.filter((scope) => scope.active).length,
    0,
  ),
);
const configuredScopeCount = computed(() =>
  props.eventTypes.reduce((total, type) => total + type.scopes.length, 0),
);
"""
text = require_replace(
    text,
    "const { t } = useLocale();\n",
    "const { t } = useLocale();\n" + computed_block,
    'EventCodex computed metrics',
    1,
)

header_pattern = re.compile(r'    <header class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">.*?    </header>\n', re.S)
header = """    <RoomBanner
      :eyebrow="t('events.catalogue.eyebrow')"
      :title="t('events.catalogue.title')"
      :subtitle="t('events.catalogue.description')"
      image="/images/kingshot/v4/event-command.svg"
      compact
    >
      <template #actions>
        <Link href="/platform" class="ks-command-link" data-variant="secondary">
          ← {{ t('events.catalogue.back') }}
        </Link>
      </template>
    </RoomBanner>

    <section class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Event catalogue summary">
      <StatSeal :label="t('events.catalogue.title')" :value="props.eventTypes.length" icon="✦" />
      <StatSeal :label="t('events.catalogue.active')" :value="activeScopeCount" icon="✓" tone="teal" />
      <StatSeal :label="t('events.catalogue.capabilities')" :value="props.capabilityOptions.length" icon="⚙" tone="stone" />
      <StatSeal :label="t('events.catalogue.scheduleSource')" :value="configuredScopeCount" icon="⌛" />
    </section>
"""
text, count = header_pattern.subn(header, text, count=1)
if count != 1:
    raise SystemExit('EventCodex header replacement did not match exactly once')

text = text.replace(
    'class="mb-6 rounded-[var(--ks-radius-md)] border border-emerald-500/25 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200"',
    'class="ks-surface mt-5 border-emerald-400/25 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100"',
)
text = text.replace('class="space-y-5"', 'class="mt-5 space-y-5"', 1)
text = text.replace(
    'class="rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] bg-[var(--ks-surface-1)] p-5"',
    'class="ks-surface-gold p-5 sm:p-6"',
)
text = text.replace(
    'class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-surface-2)] p-4"',
    'class="ks-surface p-4"',
)
text = text.replace(
    'class="rounded-full border border-[var(--ks-border)] px-2 py-0.5 text-xs text-[var(--ks-text-muted)]"',
    'class="ks-chip"',
)
for old in [
    'class="mt-1 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-1)] px-3 py-2 text-sm"',
    'class="mt-1 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-1)] px-3 py-2 text-sm disabled:opacity-50"',
]:
    text = text.replace(old, 'class="ks-input mt-1 text-sm disabled:opacity-50"')
text = text.replace(
    'class="mt-1 w-full rounded border border-[var(--ks-border)] bg-[var(--ks-surface-1)] px-3 py-2 font-mono text-xs"',
    'class="ks-input mt-1 font-mono text-xs"',
)
text = text.replace(
    'class="mt-4 rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-4 py-2 text-sm font-bold text-[var(--ks-ink)] disabled:opacity-50"',
    'class="ks-command-button mt-4 disabled:opacity-50"',
)
path.write_text(text)


# ---------------------------------------------------------------------------
# Citadel / Realm Control
# ---------------------------------------------------------------------------
path, text = load('resources/js/pages/Citadel/RealmControl/Index.vue')
text = require_replace(
    text,
    "import AppLayout from '@/layouts/AppLayout.vue';",
    "import RoomBanner from '@/components/game/RoomBanner.vue';\nimport StatSeal from '@/components/game/StatSeal.vue';\nimport AppLayout from '@/layouts/AppLayout.vue';",
    'RealmControl imports',
    1,
)

header_pattern = re.compile(r'    <header class="mb-8 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">.*?    </header>\n', re.S)
header = """    <RoomBanner
      :eyebrow="t('platformAdmin.eyebrow')"
      :title="t('platformAdmin.title')"
      :subtitle="t('platformAdmin.subtitle')"
      image="/images/kingshot/v4/account-vault.svg"
      compact
    >
      <template #actions>
        <Link href="/dashboard" class="ks-command-link" data-variant="secondary">
          ← {{ t('platformAdmin.backDashboard') }}
        </Link>
        <Link href="/platform/event-types" class="ks-command-link">
          {{ t('events.catalogue.title') }}
        </Link>
      </template>
    </RoomBanner>

    <section class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Platform command summary">
      <StatSeal :label="metricLabel('alliances')" :value="platform.metrics.alliances ?? 0" icon="♜" />
      <StatSeal :label="metricLabel('activeAlliances')" :value="platform.metrics.activeAlliances ?? 0" icon="✓" tone="teal" />
      <StatSeal :label="metricLabel('pendingOutbox')" :value="platform.metrics.pendingOutbox ?? 0" icon="✉" tone="stone" />
      <StatSeal :label="t('platformAdmin.administrators')" :value="platform.administrators.filter((admin) => !admin.revokedAt).length" icon="♛" />
    </section>
"""
text, count = header_pattern.subn(header, text, count=1)
if count != 1:
    raise SystemExit('RealmControl header replacement did not match exactly once')

text = text.replace(
    'class="mb-6 rounded-[var(--ks-radius-md)] border border-[var(--ks-blue)]/25 bg-[var(--ks-blue-soft)] px-4 py-3 text-sm leading-6 text-[var(--ks-text-secondary)]"',
    'class="ks-surface-gold mt-5 px-4 py-3 text-sm leading-6 text-[var(--ks-text-secondary)]"',
)
text = text.replace(
    'class="mb-6 rounded-[var(--ks-radius-md)] border border-emerald-500/25 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200"',
    'class="ks-surface mt-4 border-emerald-400/25 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100"',
)
text = text.replace('class="mb-8 space-y-4"', 'class="mt-6 space-y-4"', 1)

# Promote repeated field/control recipes to the shared V4 semantic classes.
patterns = [
    ('class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 focus:border-[var(--ks-blue)]"', 'class="ks-input"'),
    ('class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2 text-sm"', 'class="ks-input text-sm"'),
    ('class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-3 py-2 text-sm"', 'class="ks-input text-sm"'),
    ('class="w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5"', 'class="ks-input"'),
    ('class="rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-4 py-2.5 font-bold text-[var(--ks-ink)] transition hover:bg-[var(--ks-gold-strong)] disabled:opacity-60"', 'class="ks-command-button disabled:opacity-60"'),
    ('class="rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-4 py-2 text-sm font-bold text-[var(--ks-ink)] disabled:opacity-60"', 'class="ks-command-button disabled:opacity-60"'),
    ('class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold"', 'class="ks-command-link"'),
]
for old, new in patterns:
    text = text.replace(old, new)

# Reduce legacy nested-card noise while retaining operational grouping.
text = text.replace(
    'class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-surface-2)] p-4"',
    'class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border-quiet)] bg-black/15 p-4"',
)
text = text.replace(
    'class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-surface-2)] p-3"',
    'class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border-quiet)] bg-black/15 p-3"',
)
path.write_text(text)
