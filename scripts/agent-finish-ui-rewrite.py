from pathlib import Path
import re


def read(path: str) -> tuple[Path, str]:
    p = Path(path)
    return p, p.read_text()


def require(text: str, old: str, new: str, label: str, count: int | None = None) -> str:
    found = text.count(old)
    if found == 0:
        raise SystemExit(f'{label}: expected pattern not found: {old[:120]!r}')
    if count is not None and found != count:
        raise SystemExit(f'{label}: expected {count} matches, found {found}: {old[:120]!r}')
    return text.replace(old, new)


def replace_header(text: str, replacement: str, label: str) -> str:
    pattern = re.compile(r'    <header class="[^"]*">.*?    </header>\n', re.S)
    text, count = pattern.subn(replacement, text, count=1)
    if count != 1:
        raise SystemExit(f'{label}: first header not replaced exactly once')
    return text


def semantic_controls(text: str) -> str:
    # Common control recipes from the old UI generation.
    patterns = {
        'class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/20 px-3 py-2.5"': 'class="ks-input mt-2"',
        'class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3.5 py-2.5 transition outline-none hover:border-[var(--ks-border-strong)] focus:border-[var(--ks-blue)]"': 'class="ks-input mt-2"',
        'class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3.5 py-2.5 transition outline-none placeholder:text-[var(--ks-text-muted)] hover:border-[var(--ks-border-strong)] focus:border-[var(--ks-blue)]"': 'class="ks-input mt-2"',
        'class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3.5 py-2.5 transition outline-none hover:border-[var(--ks-border-strong)] focus:border-[var(--ks-blue)] disabled:cursor-not-allowed disabled:opacity-60"': 'class="ks-input mt-2 disabled:cursor-not-allowed disabled:opacity-60"',
        'class="mt-2 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2 font-mono text-sm text-[var(--ks-text)]"': 'class="ks-input mt-2 font-mono text-sm"',
        'class="mt-2 w-full rounded-lg border border-amber-400/30 bg-[var(--ks-bg)] px-3 py-2 font-mono text-sm text-amber-100"': 'class="ks-input mt-2 border-amber-400/30 font-mono text-sm text-amber-100"',
        'class="rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-4 py-2.5 text-sm font-bold text-[var(--ks-ink)]"': 'class="ks-command-button"',
        'class="rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2.5 text-sm font-semibold text-[var(--ks-ivory)] disabled:opacity-60"': 'class="ks-command-button disabled:opacity-60"',
        'class="rounded-lg bg-[var(--ks-gold)] px-4 py-2 text-sm font-semibold text-[var(--ks-ink)] disabled:opacity-60"': 'class="ks-command-button disabled:opacity-60"',
        'class="mt-5 rounded-lg bg-[var(--ks-gold)] px-4 py-2 text-sm font-semibold text-[var(--ks-ink)] disabled:cursor-not-allowed disabled:opacity-60"': 'class="ks-command-button mt-5 disabled:cursor-not-allowed disabled:opacity-60"',
        'class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold"': 'class="ks-command-link"',
        'class="rounded-lg border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold"': 'class="ks-command-link"',
        'class="rounded-lg border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold text-[var(--ks-text)]"': 'class="ks-command-link"',
        'class="rounded-lg border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold text-[var(--ks-blue-strong)]"': 'class="ks-command-link"',
    }
    for old, new in patterns.items():
        text = text.replace(old, new)
    return text


# ---------------------------------------------------------------------------
# Royal Court / Roles
# ---------------------------------------------------------------------------
path, text = read('resources/js/pages/Kingdom/RoyalCourt/Roles.vue')
text = require(
    text,
    "import AppLayout from '@/layouts/AppLayout.vue';",
    "import RoomBanner from '@/components/game/RoomBanner.vue';\nimport AppLayout from '@/layouts/AppLayout.vue';",
    'RoyalCourt Roles imports',
    1,
)
text = replace_header(
    text,
    """    <RoomBanner
      :eyebrow="t('kingdomP7A.rolesEyebrow')"
      :title="t('kingdomP7A.rolesTitle')"
      :subtitle="t('kingdomP7A.rolesSubtitle', { kingdom: kingdom.number })"
      image="/images/kingshot/v4/kingdom-map.svg"
      compact
    >
      <template #actions>
        <Link href="/alliance/kingdom-alliances" class="ks-command-link" data-variant="secondary">
          ← {{ t('kingdomP7A.overviewTitle') }}
        </Link>
      </template>
    </RoomBanner>
""",
    'RoyalCourt Roles header',
)
text = semantic_controls(text)
path.write_text(text)


# ---------------------------------------------------------------------------
# Royal Court / Settings
# ---------------------------------------------------------------------------
path, text = read('resources/js/pages/Kingdom/RoyalCourt/Settings.vue')
text = require(
    text,
    "import AppLayout from '@/layouts/AppLayout.vue';",
    "import RoomBanner from '@/components/game/RoomBanner.vue';\nimport AppLayout from '@/layouts/AppLayout.vue';",
    'RoyalCourt Settings imports',
    1,
)
text = replace_header(
    text,
    """    <RoomBanner
      :eyebrow="t('kingdomP7A.settingsEyebrow')"
      :title="t('kingdomP7A.settingsTitle')"
      :subtitle="alliance.kingdom ? `#${alliance.kingdom} · ${alliance.name}` : alliance.name"
      image="/images/kingshot/v4/kingdom-map.svg"
      compact
    >
      <template #actions>
        <Link href="/alliance/kingdom-alliances" class="ks-command-link" data-variant="secondary">
          ← {{ t('kingdomP7A.overviewTitle') }}
        </Link>
        <Link href="/alliance/kingdom-ingestion/manage" class="ks-command-link">
          {{ t('kingdomP7A.ingestion') }}
        </Link>
        <Link v-if="canManageKingdomRoles" href="/alliance/settings/kingdom/roles" class="ks-command-link">
          {{ t('kingdomP7A.rolesManage') }}
        </Link>
      </template>
    </RoomBanner>
""",
    'RoyalCourt Settings header',
)
text = text.replace('class="ks-surface mt-6 p-5"', 'class="ks-surface-gold mt-5 p-5 sm:p-6"', 1)
text = semantic_controls(text)
path.write_text(text)


# ---------------------------------------------------------------------------
# Kingdom Transfer / Completion
# ---------------------------------------------------------------------------
path, text = read('resources/js/pages/Kingdom/Transfer/Completion.vue')
text = require(
    text,
    "import AppLayout from '@/layouts/AppLayout.vue';",
    "import RoomBanner from '@/components/game/RoomBanner.vue';\nimport StatSeal from '@/components/game/StatSeal.vue';\nimport AppLayout from '@/layouts/AppLayout.vue';",
    'Transfer Completion imports',
    1,
)
text = replace_header(
    text,
    """    <RoomBanner
      :eyebrow="t('kingdomP7D.completionEyebrow')"
      :title="t('kingdomP7D.completionTitle')"
      :subtitle="t('kingdomP7D.completionSubtitle', { alliance: alliance.name, kingdom: alliance.kingdom ?? t('kingdomP7D.notConfigured') })"
      image="/images/kingshot/v4/kingdom-transfer.svg"
      compact
    >
      <template #actions>
        <Link href="/alliance/transfers" class="ks-command-link" data-variant="secondary">
          ← {{ t('kingdomP7D.title') }}
        </Link>
        <Link href="/alliance/transfers/readiness" class="ks-command-link">
          {{ t('kingdomP7D.readinessBoard') }}
        </Link>
        <Link href="/alliance/transfers/manage" class="ks-command-link">
          {{ t('kingdomP7D.manageTransfers') }}
        </Link>
      </template>
    </RoomBanner>
""",
    'Transfer Completion header',
)
summary_pattern = re.compile(r'    <section\n      v-if="plan"\n      class="mt-6 grid gap-3 sm:grid-cols-4".*?    </section>\n', re.S)
summary = """    <section v-if="plan" class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" :aria-label="t('kingdomP7D.summary')">
      <StatSeal :label="t('kingdomP7D.cycle')" :value="plan.label" icon="◇" />
      <StatSeal :label="t('kingdomP7D.participants')" :value="participants.length" icon="♟" tone="stone" />
      <StatSeal :label="t('kingdomP7D.readinessConfirmed')" :value="completionCounts.confirmed" icon="✓" tone="teal" />
      <StatSeal :label="t('kingdomP7D.completed')" :value="completionCounts.completed" icon="✦" />
    </section>
"""
text, count = summary_pattern.subn(summary, text, count=1)
if count != 1:
    raise SystemExit('Transfer Completion summary not replaced exactly once')
text = semantic_controls(text)
path.write_text(text)


# ---------------------------------------------------------------------------
# Glory Ledger / Manage
# ---------------------------------------------------------------------------
path, text = read('resources/js/pages/Intelligence/GloryLedger/Manage.vue')
text = require(
    text,
    "import { computed } from 'vue';\nimport AppLayout from '@/layouts/AppLayout.vue';",
    "import { computed } from 'vue';\n\nimport RoomBanner from '@/components/game/RoomBanner.vue';\nimport AppLayout from '@/layouts/AppLayout.vue';",
    'Glory Manage imports',
    1,
)
text = replace_header(
    text,
    """    <RoomBanner
      :eyebrow="t('contributions.eyebrow')"
      :title="t('contributions.managerTitle')"
      :subtitle="t('contributions.managerSubtitle', { alliance: alliance.name })"
      image="/images/kingshot/v4/glory-ledger.svg"
      compact
    >
      <template #actions>
        <Link href="/alliance/contributions" class="ks-command-link" data-variant="secondary">
          ← {{ t('contributions.memberView') }}
        </Link>
        <a class="ks-command-link" href="/alliance/contributions/export.csv">{{ t('contributions.exportCsv') }}</a>
        <a class="ks-command-link" href="/alliance/contributions/export.xls">{{ t('contributions.exportSpreadsheet') }}</a>
      </template>
    </RoomBanner>
""",
    'Glory Manage header',
)
text = semantic_controls(text)
text = text.replace('class="btn"', 'class="ks-command-link"')
text = text.replace('class="btn border-[var(--ks-gold)]/50 text-[var(--ks-gold-strong)]"', 'class="ks-command-link"')
path.write_text(text)


# ---------------------------------------------------------------------------
# Intel Room / Ingestion Reports
# ---------------------------------------------------------------------------
path, text = read('resources/js/pages/Intelligence/KingdomWatch/Reports.vue')
text = require(
    text,
    "import AppLayout from '@/layouts/AppLayout.vue';",
    "import RoomBanner from '@/components/game/RoomBanner.vue';\nimport AppLayout from '@/layouts/AppLayout.vue';",
    'Intel Reports imports',
    1,
)
text = replace_header(
    text,
    """    <RoomBanner
      :eyebrow="t('kingdomP7A.eyebrow')"
      :title="t('kingdomP7A.ingestionTitle')"
      :subtitle="t('kingdomP7A.ingestionSubtitle', { alliance: alliance.name })"
      image="/images/kingshot/v4/intel-room.svg"
      compact
    >
      <template #actions>
        <Link href="/alliance/kingdom-alliances" class="ks-command-link" data-variant="secondary">
          ← {{ t('kingdomP7A.overviewTitle') }}
        </Link>
        <Link href="/alliance/settings/kingdom" class="ks-command-link">
          {{ t('kingdomP7A.settings') }}
        </Link>
      </template>
    </RoomBanner>
""",
    'Intel Reports header',
)
text = semantic_controls(text)
path.write_text(text)


# ---------------------------------------------------------------------------
# Roster Intelligence / History
# ---------------------------------------------------------------------------
path, text = read('resources/js/pages/Intelligence/Roster/History.vue')
text = require(
    text,
    "import AppLayout from '@/layouts/AppLayout.vue';",
    "import RoomBanner from '@/components/game/RoomBanner.vue';\nimport AppLayout from '@/layouts/AppLayout.vue';",
    'Roster History imports',
    1,
)
text = replace_header(
    text,
    """    <RoomBanner
      :eyebrow="t('roster.eyebrow', { kingdom: alliance.kingdom ?? t('roster.kingdomNotSet') })"
      :title="entry.name"
      :subtitle="`${t('roster.gameId')}: ${entry.gamePlayerId ?? t('rosterManage.unknown')} · ${entry.membership?.name ?? t('roster.unlinked')}`"
      image="/images/kingshot/v4/roster-hall.svg"
      compact
    >
      <template #actions>
        <Link href="/alliance/roster" class="ks-command-link" data-variant="secondary">
          ← {{ t('roster.title') }}
        </Link>
        <Link v-if="canManage" href="/alliance/roster/manage" class="ks-command-link">
          {{ t('roster.manage') }}
        </Link>
      </template>
    </RoomBanner>
""",
    'Roster History header',
)
text = semantic_controls(text)
path.write_text(text)


# ---------------------------------------------------------------------------
# Intelligence Sharing / Read
# ---------------------------------------------------------------------------
path, text = read('resources/js/pages/Intelligence/Sharing/Index.vue')
text = require(
    text,
    "import AppLayout from '@/layouts/AppLayout.vue';",
    "import RoomBanner from '@/components/game/RoomBanner.vue';\nimport AppLayout from '@/layouts/AppLayout.vue';",
    'Sharing Index imports',
    1,
)
text = replace_header(
    text,
    """    <RoomBanner
      :eyebrow="t('kingdomP7C.eyebrow')"
      :title="t('kingdomP7C.title')"
      :subtitle="t('kingdomP7C.subtitle', { alliance: alliance.name })"
      image="/images/kingshot/v4/connections.svg"
      compact
    >
      <template #actions>
        <Link v-if="canManage" href="/alliance/kingdom-sharing/manage" class="ks-command-link">
          {{ t('kingdomP7C.manageSharing') }}
        </Link>
      </template>
    </RoomBanner>
""",
    'Sharing Index header',
)
text = semantic_controls(text)
path.write_text(text)


# ---------------------------------------------------------------------------
# Intelligence Sharing / Manage
# ---------------------------------------------------------------------------
path, text = read('resources/js/pages/Intelligence/Sharing/Manage.vue')
text = require(
    text,
    "import AppLayout from '@/layouts/AppLayout.vue';",
    "import RoomBanner from '@/components/game/RoomBanner.vue';\nimport AppLayout from '@/layouts/AppLayout.vue';",
    'Sharing Manage imports',
    1,
)
text = replace_header(
    text,
    """    <RoomBanner
      :eyebrow="t('kingdomP7C.manageEyebrow')"
      :title="t('kingdomP7C.manageTitle')"
      :subtitle="t('kingdomP7C.manageSubtitle', { alliance: alliance.name, kingdom: alliance.kingdom ?? t('kingdomP7C.notConfigured') })"
      image="/images/kingshot/v4/connections.svg"
      compact
    >
      <template #actions>
        <Link href="/alliance/kingdom-sharing" class="ks-command-link" data-variant="secondary">
          ← {{ t('kingdomP7C.receivedFacts') }}
        </Link>
        <Link href="/dashboard" class="ks-command-link">
          {{ t('kingdomP7C.dashboard') }}
        </Link>
      </template>
    </RoomBanner>
""",
    'Sharing Manage header',
)
text = semantic_controls(text)
path.write_text(text)


# ---------------------------------------------------------------------------
# Public Alliance profile
# ---------------------------------------------------------------------------
path, text = read('resources/js/pages/Public/Alliance/Show.vue')
text = require(
    text,
    '      <img\n        v-if="alliance.bannerUrl"',
    '      <img\n        v-if="alliance.bannerUrl"',
    'Public Alliance banner anchor',
    1,
)
# Add an original product-art fallback when an alliance has no custom banner.
anchor = """      <img
        v-if="alliance.bannerUrl"
        class="absolute inset-0 -z-20 h-full w-full object-cover opacity-45"
        :src="alliance.bannerUrl"
        :alt="`${alliance.name} banner`"
      />"""
fallback = anchor + """
      <img
        v-else
        class="absolute inset-0 -z-20 h-full w-full object-cover opacity-40"
        src="/images/kingshot/v4/alliance-hall.svg"
        alt=""
        aria-hidden="true"
      />"""
text = require(text, anchor, fallback, 'Public Alliance fallback art', 1)
text = text.replace(
    'class="rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-5 py-2.5 text-sm font-bold text-[var(--ks-ink)] transition hover:bg-[var(--ks-gold-strong)]"',
    'class="ks-command-button"',
)
text = text.replace(
    'class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[rgba(8,17,31,0.68)] px-5 py-2.5 text-sm font-semibold transition hover:border-[var(--ks-border-strong)] hover:bg-[var(--ks-surface-1)]"',
    'class="ks-command-link"',
)
text = semantic_controls(text)
path.write_text(text)


# ---------------------------------------------------------------------------
# Public Alliance notice/article
# ---------------------------------------------------------------------------
path, text = read('resources/js/pages/Public/Alliance/Notice.vue')
text = require(
    text,
    '    <section class="border-b border-[var(--ks-border)] bg-[rgba(8,17,31,0.58)]">',
    '    <section class="relative isolate overflow-hidden border-b border-[var(--ks-border)]">\n      <img class="absolute inset-0 -z-20 h-full w-full object-cover opacity-25" src="/images/kingshot/v4/noticeboard.svg" alt="" aria-hidden="true" />\n      <div class="absolute inset-0 -z-10 bg-[linear-gradient(90deg,rgba(4,8,9,.96),rgba(7,17,17,.82))]" />',
    'Public Notice hero',
    1,
)
path.write_text(text)


# ---------------------------------------------------------------------------
# Public Recruitment Apply
# ---------------------------------------------------------------------------
path, text = read('resources/js/pages/Public/Recruitment/Apply.vue')
text = require(
    text,
    '    <section class="relative isolate overflow-hidden border-b border-[var(--ks-border)]">\n      <div\n        class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_20%_10%,rgba(226,180,77,0.14),transparent_28rem),linear-gradient(180deg,rgba(11,22,38,0.72),rgba(5,11,20,0.94))]"\n      />',
    '    <section class="relative isolate overflow-hidden border-b border-[var(--ks-border)]">\n      <img class="absolute inset-0 -z-20 h-full w-full object-cover opacity-42" src="/images/kingshot/v4/recruitment-hall.svg" alt="" aria-hidden="true" />\n      <div class="absolute inset-0 -z-10 bg-[linear-gradient(90deg,rgba(4,8,9,.98),rgba(5,14,15,.88),rgba(5,11,20,.78))]" />',
    'Public Recruitment hero art',
    1,
)
text = semantic_controls(text)
# Remaining recruitment field recipes including textareas/selects.
text = text.replace(
    'class="w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3.5 py-2.5 transition outline-none hover:border-[var(--ks-border-strong)] focus:border-[var(--ks-blue)]"',
    'class="ks-input"',
)
text = text.replace(
    'class="w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3.5 py-2.5 transition outline-none placeholder:text-[var(--ks-text-muted)] hover:border-[var(--ks-border-strong)] focus:border-[var(--ks-blue)]"',
    'class="ks-input"',
)
text = text.replace(
    'class="rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-5 py-2.5 text-sm font-bold text-[var(--ks-ink)] transition hover:bg-[var(--ks-gold-strong)] disabled:opacity-60"',
    'class="ks-command-button disabled:opacity-60"',
)
path.write_text(text)
