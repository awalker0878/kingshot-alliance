from pathlib import Path

root=Path('.')

source_path = root / 'resources/js/pages/Operations/Events/Chronicle.vue'
dest_path = root / 'resources/js/pages/Operations/Events/History.vue'
if source_path.exists():
    text = source_path.read_text(encoding='utf-8')
else:
    text = dest_path.read_text(encoding='utf-8')

def req(old, new, label):
    global text
    if old in text:
        text = text.replace(old, new)
    elif new not in text:
        raise SystemExit(f'Missing replacement target: {label}')

req('const { formatDate, formatNumber } = useLocale();', 'const { t, formatDate, formatNumber } = useLocale();', 'useLocale t')
req("return details.length > 0 ? details.join(' · ') : 'No recorded evidence';", "return details.length > 0 ? details.join(' · ') : t('events.history.noEvidence');", 'no evidence')
req("  return `${series.eventTypeSlug} · ${series.metricKey}${dimension}`;", "  const eventType = t(`events.types.${series.eventTypeSlug}.name`);\n  return `${eventType} · ${series.metricKey}${dimension}`;", 'metric event type')
req('<Head :title="`Event history · ${organization.name}`" />', '<Head :title="`${t(\'events.history.title\')} · ${organization.name}`" />', 'head title')
req('{{ organization.scope }} Event history', "{{ t(`events.scope.${organization.scope}`) }} · {{ t('events.history.title') }}", 'eyebrow')
req("        <p class=\"max-w-3xl text-sm text-[var(--ks-muted)]\">\n          History is owned by the original Event target. Current authority controls access, while\n          Governor names, represented Alliances, and Kingdom remain frozen at the occurrence where\n          they were recorded.\n        </p>", "        <p class=\"max-w-3xl text-sm text-[var(--ks-muted)]\">\n          {{ t('events.history.subtitle', { organization: organization.name }) }}\n        </p>", 'subtitle')
req('<span>Event type</span>', "<span>{{ t('events.create.eventType') }}</span>", 'event type')
req('placeholder="All types"', ':placeholder="t(\'events.calendar.all\')"', 'all types')
req('<span>From</span>', "<span>{{ t('events.history.from') }}</span>", 'from')
req('<span>Until</span>', "<span>{{ t('events.history.until') }}</span>", 'until')
req('<span>Rows</span>', "<span>{{ t('events.history.rows') }}</span>", 'rows')
req('            Apply filters', "            {{ t('events.history.applyFilters') }}", 'apply filters')
req('            Clear', "            {{ t('events.history.clearFilters') }}", 'clear filters')
req('<h2 class="text-lg font-semibold text-[var(--ks-ivory)]">Compatible metric trends</h2>', '<h2 class="text-lg font-semibold text-[var(--ks-ivory)]">{{ t(\'events.history.metricTrends\') }}</h2>', 'metric trends')
req("          <p class=\"mt-1 text-xs text-[var(--ks-muted)]\">\n            Metrics are compared only within the same Event Type/scope and dimension. There is no\n            universal cross-Event score.\n          </p>", "          <p class=\"mt-1 text-xs text-[var(--ks-muted)]\">\n            {{ t('events.history.metricTrendsHelp') }}\n          </p>", 'metric help')
req('<span class="text-[var(--ks-muted)]">Average</span>', '<span class="text-[var(--ks-muted)]">{{ t(\'events.history.average\') }}</span>', 'average')
req('<span class="text-[var(--ks-muted)]">Best</span>', '<span class="text-[var(--ks-muted)]">{{ t(\'events.history.best\') }}</span>', 'best')
req('<span class="text-[var(--ks-muted)]">Samples</span>', '<span class="text-[var(--ks-muted)]">{{ t(\'events.history.samples\') }}</span>', 'samples')
req('              Latest {{ dateTime(series.latest.startsAt) }}', "              {{ t('events.history.latest') }} {{ dateTime(series.latest.startsAt) }}", 'latest')
req('<h2 class="text-lg font-semibold text-[var(--ks-ivory)]">Event-specific leaderboards</h2>', '<h2 class="text-lg font-semibold text-[var(--ks-ivory)]">{{ t(\'events.history.leaderboards\') }}</h2>', 'leaderboards')
req("          <p class=\"mt-1 text-xs text-[var(--ks-muted)]\">\n            Each board uses the metric's own aggregation and never mixes incompatible Event\n            families.\n          </p>", "          <p class=\"mt-1 text-xs text-[var(--ks-muted)]\">\n            {{ t('events.history.leaderboardsHelp') }}\n          </p>", 'leaderboards help')
req("              <span class=\"text-xs tracking-wide text-[var(--ks-muted)] uppercase\">{{\n                board.aggregation\n              }}</span>", '', 'aggregation label')
req('        No historical Events match these filters.', "        {{ t('events.history.noEvents') }}", 'empty history')
req('<span>{{ event.eventType.slug }}</span>', '<span>{{ t(event.eventType.nameKey) }}</span>', 'event type name')
req('<span>{{ humanize(event.occurrenceStatus) }}</span>', '<span>{{ t(`events.occurrenceStatuses.${event.occurrenceStatus}`) }}</span>', 'occurrence status')
req('<p class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">Result</p>', '<p class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">{{ t(\'events.results.title\') }}</p>', 'result')
req('aria-label="Historical operational evidence"', ':aria-label="t(\'events.history.evidenceAria\')"', 'evidence aria')
req('<p class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">Attendance</p>', '<p class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">{{ t(\'events.manage.attendance\') }}</p>', 'attendance')
req('<p class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">Roster</p>', '<p class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">{{ t(\'events.rosters.roster\') }}</p>', 'roster')
req("              <p class=\"text-xs tracking-wide text-[var(--ks-muted)] uppercase\">\n                Rally assignments\n              </p>", "              <p class=\"text-xs tracking-wide text-[var(--ks-muted)] uppercase\">\n                {{ t('events.history.rallyAssignments') }}\n              </p>", 'rally assignments')
req('<p class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">Objectives</p>', '<p class="text-xs tracking-wide text-[var(--ks-muted)] uppercase">{{ t(\'events.objectives.title\') }}</p>', 'objectives')
req('{{ formatNumber(event.evidence.objectives.assignments) }} assignments', "{{ t('events.history.assignments', { count: formatNumber(event.evidence.objectives.assignments) }) }}", 'assignments')
req('<h3 class="text-sm font-semibold text-[var(--ks-ivory)]">Alliance results</h3>', '<h3 class="text-sm font-semibold text-[var(--ks-ivory)]">{{ t(\'events.history.allianceResults\') }}</h3>', 'alliance results')
req('<h3 class="text-sm font-semibold text-[var(--ks-ivory)]">Historical participants</h3>', '<h3 class="text-sm font-semibold text-[var(--ks-ivory)]">{{ t(\'events.history.participants\') }}</h3>', 'participants')
req('>{{ event.participants.length }} Governors</span', ">{{ t('events.history.governorCount', { count: formatNumber(event.participants.length) }) }}</span", 'governor count')
req('<th class="px-4 py-3">Governor</th>', '<th class="px-4 py-3">{{ t(\'events.manage.player\') }}</th>', 'governor header')
req('<th class="px-4 py-3">Represented Alliance</th>', '<th class="px-4 py-3">{{ t(\'events.history.representedAlliance\') }}</th>', 'represented alliance')
req('<th class="px-4 py-3">Score</th>', '<th class="px-4 py-3">{{ t(\'events.results.score\') }}</th>', 'score')
req('<th class="px-4 py-3">Outcome</th>', '<th class="px-4 py-3">{{ t(\'events.results.outcome\') }}</th>', 'outcome')
req("              No Governor was recorded for this occurrence.", "              {{ t('events.history.noParticipants') }}", 'no participants')

dest_path.parent.mkdir(parents=True, exist_ok=True)
dest_path.write_text(text, encoding='utf-8')
if source_path.exists():
    source_path.unlink()

controller = root / 'app/ReadModels/EventHistory/Http/Controllers/EventHistoryController.php'
text = controller.read_text(encoding='utf-8').replace('Operations/Events/Chronicle', 'Operations/Events/History')
controller.write_text(text, encoding='utf-8')

workflow = root / '.github/workflows/intelligence-verification.yml'
text = workflow.read_text(encoding='utf-8')
text = text.replace('resources/js/pages/Operations/Events/Chronicle.vue', 'resources/js/pages/Operations/Events/History.vue')
text = text.replace('resources/js/pages/Intelligence/GloryLedger/History.vue', 'resources/js/pages/Intelligence/Contributions/History.vue')
workflow.write_text(text, encoding='utf-8')

layout = root / 'resources/js/layouts/AppLayout.vue'
text = layout.read_text(encoding='utf-8')
text = text.replace('const rooms: NavigationItem[] = [', 'const navigationItems: NavigationItem[] = [')
text = text.replace('<!-- Desktop command rail -->', '<!-- Desktop navigation -->')
text = text.replace('v-for="room in rooms"', 'v-for="item in navigationItems"')
text = text.replace('room.href', 'item.href').replace('room.key', 'item.key').replace('room.icon', 'item.icon')
text = text.replace('isDisabled(room)', 'isDisabled(item)').replace('isActive(room)', 'isActive(item)')
layout.write_text(text, encoding='utf-8')

visual = root / 'tests/v3/Visual/ApplicationShell.spec.ts'
text = visual.read_text(encoding='utf-8')
text = text.replace("{ path: '/', name: 'realm-gate' }", "{ path: '/', name: 'home' }")
text = text.replace('command-overview-select-governor.png', 'home-select-governor.png')
text = text.replace('command-overview-active-governor.png', 'home-active-governor.png')
visual.write_text(text, encoding='utf-8')

checker = root / 'scripts/check-product-language.mjs'
text = checker.read_text(encoding='utf-8')
if "  'Operations/Events/Chronicle'," not in text:
    text = text.replace("  'Kingdom/RoyalCourt',\n];", "  'Kingdom/RoyalCourt',\n  'Operations/Events/Chronicle',\n];")
checker.write_text(text, encoding='utf-8')

doc = root / 'docs/product/terminology.md'
text = doc.read_text(encoding='utf-8')
if '- `Operations/Events/Chronicle` → `Operations/Events/History`' not in text:
    text = text.replace('- `Kingdom/RoyalCourt/*` → `Kingdom/PositionPerks/*`', '- `Kingdom/RoyalCourt/*` → `Kingdom/PositionPerks/*`\n- `Operations/Events/Chronicle` → `Operations/Events/History`')
doc.write_text(text, encoding='utf-8')

coverage = root / 'scripts/check-event-localization-coverage.mjs'
text = coverage.read_text(encoding='utf-8')
if 'requiredHistoryKeys' not in text:
    anchor = 'const minimumLocalizedKeys = 89;\n'
    keys = ['title','subtitle','from','until','rows','applyFilters','clearFilters','metricTrends','metricTrendsHelp','average','best','samples','latest','leaderboards','leaderboardsHelp','noEvents','noEvidence','evidenceAria','rallyAssignments','assignments','allianceResults','participants','governorCount','representedAlliance','noParticipants']
    block = anchor + "const requiredHistoryKeys = [\n" + ''.join(f"  'events.history.{key}',\n" for key in keys) + "];\n"
    text = text.replace(anchor, block)
    target = "  if (localized.size < minimumLocalizedKeys) {\n    failed = true;\n    console.error(`  Coverage regression: expected at least ${minimumLocalizedKeys} localized Event keys.`);\n  }"
    replacement = target + "\n\n  const missingHistory = requiredHistoryKeys.filter((key) => !localized.has(key));\n  if (missingHistory.length > 0) {\n    failed = true;\n    console.error(`  Missing Event History translations: ${missingHistory.join(', ')}`);\n  }"
    if target not in text:
        raise SystemExit('Unable to extend Event localization coverage check')
    text = text.replace(target, replacement)
coverage.write_text(text, encoding='utf-8')

print('Final language alignment transformations applied.')
