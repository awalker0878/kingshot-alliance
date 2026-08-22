from pathlib import Path
import re


def replace_once(source: str, old: str, new: str, label: str) -> str:
    if old not in source:
        raise RuntimeError(f"Missing expected Territory finalization anchor: {label}")
    return source.replace(old, new, 1)


editor = Path("resources/js/pages/Kingdom/Territory/Editor.vue")
source = editor.read_text()

if "MarchAnalysisPanel.vue" not in source:
    source = replace_once(
        source,
        "import TerritoryCanvas from '@/features/territory-planner/components/TerritoryCanvas.vue';",
        "import MarchAnalysisPanel from '@/features/territory-planner/components/MarchAnalysisPanel.vue';\nimport TerritoryCanvas from '@/features/territory-planner/components/TerritoryCanvas.vue';",
        "MarchAnalysisPanel import",
    )

if "governor_options:" not in source:
    source = replace_once(
        source,
        "  revisions: Revision[];\n};",
        "  revisions: Revision[];\n  governor_options: Record<string, Array<{ id: string; name: string }>>;\n};",
        "Governor options prop",
    )

source = source.replace(
    "[...validation.value.violations, ...validation.value.warnings].forEach((issue) => {",
    "[\n    ...validation.value.violations,\n    ...validation.value.warnings,\n    ...validation.value.suggestions,\n  ].forEach((issue) => {",
    1,
)

if "const governorCities = computed" not in source:
    anchor = "const selectedObjects = computed(() =>\n  objects.value.filter((object) => selectedKeys.value.includes(object.key)),\n);"
    source = replace_once(
        source,
        anchor,
        anchor
        + "\nconst governorCities = computed(() =>\n  visibleObjects.value.filter((object) => object.type === 'governor_city'),\n);",
        "Governor city computed list",
    )

if "function governorOptionsFor(" not in source:
    anchor = "function editable(object: PlanObject): boolean {\n  return canEdit.value && !allianceFor(object)?.locked;\n}"
    helpers = """function editable(object: PlanObject): boolean {
  return canEdit.value && !allianceFor(object)?.locked;
}
function governorOptionsFor(object: PlanObject): Array<{ id: string; name: string }> {
  return props.territory.governor_options[object.alliance_key] ?? [];
}
function assignGovernor(object: PlanObject, playerId: string): void {
  if (!editable(object)) return;
  remember();
  object.player_id = playerId || null;
  if (playerId) object.external_player_name = null;
}
function assignExternalGovernor(object: PlanObject, name: string): void {
  if (!editable(object)) return;
  object.player_id = null;
  object.external_player_name = name.trim() || null;
}
function bearTrapsFor(allianceKey: string): PlanObject[] {
  return objects.value.filter(
    (object) => object.alliance_key === allianceKey && object.type === 'bear_trap',
  );
}
function selectedBearFor(allianceKey: string): string {
  return preferences.value.selected_bear_trap_by_alliance?.[allianceKey] ?? '';
}
function setSelectedBear(allianceKey: string, objectKey: string): void {
  if (!canEdit.value) return;
  remember();
  const selected = { ...(preferences.value.selected_bear_trap_by_alliance ?? {}) };
  if (objectKey) selected[allianceKey] = objectKey;
  else delete selected[allianceKey];
  preferences.value = { ...preferences.value, selected_bear_trap_by_alliance: selected };
}"""
    source = replace_once(source, anchor, helpers, "Territory editor helpers")

if 'id="territory-editor-heading"' not in source:
    anchor = "    </RoomBanner>\n\n    <ActionNotice"
    source = replace_once(
        source,
        anchor,
        """    </RoomBanner>

    <header class="mt-4">
      <p class="ks-kicker">{{ t('territory.eyebrow') }}</p>
      <h1 id="territory-editor-heading" class="ks-display mt-1 text-3xl font-semibold">
        {{ t('territory.editorTitle') }}
      </h1>
    </header>

    <ActionNotice""",
        "Territory editor heading",
    )

if 'id="planning-suggestions-heading"' not in source:
    marker = """        </section>

        <details class="ks-surface mt-4 p-4" open>"""
    addition = """        </section>

        <section
          v-if="validation.suggestions.length"
          class="ks-surface mt-4 p-4"
          aria-labelledby="planning-suggestions-heading"
        >
          <h2 id="planning-suggestions-heading" class="ks-display text-xl font-semibold">
            {{ t('territory.suggestions') }}
          </h2>
          <ul class="mt-3 space-y-2 text-sm text-sky-100">
            <li
              v-for="item in validation.suggestions"
              :key="`s-${item.code}-${item.object_key}`"
            >
              {{ item.message }}
            </li>
          </ul>
        </section>

        <section class="ks-surface mt-4 p-4" aria-labelledby="governor-assignment-heading">
          <h2 id="governor-assignment-heading" class="ks-display text-xl font-semibold">
            {{ t('territory.governorAssignment') }}
          </h2>
          <div v-if="governorCities.length" class="mt-3 grid gap-3 md:grid-cols-2">
            <label
              v-for="object in governorCities"
              :key="object.key"
              class="rounded border border-[var(--ks-border)] p-3 text-sm"
            >
              <span class="font-semibold">
                {{ object.label || object.external_player_name || t('territory.types.governor_city') }}
              </span>
              <select
                v-if="governorOptionsFor(object).length"
                :value="object.player_id ?? ''"
                class="ks-input mt-2 w-full"
                :disabled="!editable(object)"
                :aria-label="t('territory.governorAssignment')"
                @change="assignGovernor(object, ($event.target as HTMLSelectElement).value)"
              >
                <option value="">{{ t('territory.unassignedGovernor') }}</option>
                <option
                  v-for="governor in governorOptionsFor(object)"
                  :key="governor.id"
                  :value="governor.id"
                >
                  {{ governor.name }}
                </option>
              </select>
              <input
                v-else
                :value="object.external_player_name ?? ''"
                type="text"
                maxlength="160"
                class="ks-input mt-2 w-full"
                :disabled="!editable(object)"
                :placeholder="t('territory.externalGovernorName')"
                :aria-label="t('territory.externalGovernorName')"
                @focus="beginExactEdit"
                @change="assignExternalGovernor(object, ($event.target as HTMLInputElement).value)"
              />
            </label>
          </div>
        </section>

        <details class="ks-surface mt-4 p-4" open>"""
    source = replace_once(source, marker, addition, "Suggestions and Governor assignment surfaces")

source = source.replace(
    '<p v-else class="mt-2 text-sm text-[var(--ks-muted)]">\n            {{ t(\'territory.noValidationIssues\') }}\n          </p>',
    '<p\n            v-if="\n              !validation.violations.length &&\n              !validation.warnings.length &&\n              !validation.suggestions.length\n            "\n            class="mt-2 text-sm text-[var(--ks-muted)]"\n          >\n            {{ t(\'territory.noValidationIssues\') }}\n          </p>',
    1,
)

if '<MarchAnalysisPanel' not in source:
    anchor = """        <section class="ks-surface p-4">
          <p class="ks-kicker">{{ t('territory.preferences') }}</p>"""
    addition = """        <MarchAnalysisPanel
          :alliances="alliances"
          :objects="objects"
          :analysis="analysis"
        />
        <section class="ks-surface p-4">
          <p class="ks-kicker">{{ t('territory.preferences') }}</p>
          <div
            v-for="alliance in alliances"
            :key="`bear-${alliance.key}`"
            class="mt-3"
          >
            <label class="block text-sm">
              {{ t('territory.selectedBearTrap', { alliance: alliance.display_name }) }}
              <select
                :value="selectedBearFor(alliance.key)"
                class="ks-input mt-1 w-full"
                :disabled="!canEdit || alliance.locked"
                @change="setSelectedBear(alliance.key, ($event.target as HTMLSelectElement).value)"
              >
                <option value="">{{ t('territory.nearestBearTrap') }}</option>
                <option v-for="trap in bearTrapsFor(alliance.key)" :key="trap.key" :value="trap.key">
                  {{ trap.label || t('territory.bearTrap') }}
                </option>
              </select>
            </label>
          </div>"""
    source = replace_once(source, anchor, addition, "March analysis and selected Bear controls")

if "t('territory.violations')" not in source:
    anchor = """                <div>
                  <dt class="text-[var(--ks-muted)]">{{ t('territory.bannerEfficiency') }}</dt>
                  <dd>{{ analysis[alliance.key]?.banner_efficiency ?? '—' }}</dd>
                </div>"""
    metrics = anchor + """
                <div>
                  <dt class="text-[var(--ks-muted)]">{{ t('territory.violations') }}</dt>
                  <dd>{{ formatNumber(analysis[alliance.key]?.violation_count ?? 0) }}</dd>
                </div>
                <div>
                  <dt class="text-[var(--ks-muted)]">{{ t('territory.warnings') }}</dt>
                  <dd>{{ formatNumber(analysis[alliance.key]?.warning_count ?? 0) }}</dd>
                </div>
                <div>
                  <dt class="text-[var(--ks-muted)]">{{ t('territory.suggestions') }}</dt>
                  <dd>{{ formatNumber(analysis[alliance.key]?.suggestion_count ?? 0) }}</dd>
                </div>"""
    source = replace_once(source, anchor, metrics, "Analysis quality metrics")

if "comparison.previous[alliance.key]?.violation_count" not in source:
    anchor = """              <p>
                {{ t('territory.avgDistance') }}:
                {{ comparison.previous[alliance.key]?.bear_distance_tiles.average ?? '—' }} →
                {{ comparison.current[alliance.key]?.bear_distance_tiles.average ?? '—' }}
              </p>"""
    comparison_metrics = anchor + """
              <p>
                {{ t('territory.violations') }}:
                {{ comparison.previous[alliance.key]?.violation_count ?? 0 }} →
                {{ comparison.current[alliance.key]?.violation_count ?? 0 }}
              </p>
              <p>
                {{ t('territory.warnings') }}:
                {{ comparison.previous[alliance.key]?.warning_count ?? 0 }} →
                {{ comparison.current[alliance.key]?.warning_count ?? 0 }}
              </p>
              <p>
                {{ t('territory.suggestions') }}:
                {{ comparison.previous[alliance.key]?.suggestion_count ?? 0 }} →
                {{ comparison.current[alliance.key]?.suggestion_count ?? 0 }}
              </p>"""
    source = replace_once(source, anchor, comparison_metrics, "Comparison quality metrics")

editor.write_text(source)

manage = Path("resources/js/pages/Operations/Events/Manage.vue")
source = manage.read_text()
if "EventTerritoryPositioning.vue" not in source:
    source = replace_once(
        source,
        "import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';",
        "import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';\nimport EventTerritoryPositioning from '@/features/territory-planner/components/EventTerritoryPositioning.vue';",
        "Event Territory component import",
    )

if "type TerritoryPlanningOperations =" not in source:
    marker = "\nconst props = defineProps<{"
    territory_type = """
type TerritoryPlanningOperations = {
  supported: boolean;
  availableRevisions: Array<{
    id: string;
    planId: string;
    planName: string;
    revisionNumber: number;
    mapDatasetId: string;
    mapDatasetChecksum: string;
    publishedAt: string | null;
  }>;
  attachments: Array<{
    id: string;
    occurrenceId: string;
    purpose: string;
    revisionId: string;
    planId: string;
    planName: string;
    revisionNumber: number;
    publishedAt: string | null;
  }>;
};

const props = defineProps<{"""
    source = replace_once(source, marker, "\n" + territory_type, "Territory Operations prop type")

if "territoryPlanning: TerritoryPlanningOperations;" not in source:
    match = re.search(r"\n  reminderAudiences:", source)
    if not match:
        raise RuntimeError("Missing reminderAudiences prop anchor")
    source = source[: match.start()] + "\n  territoryPlanning: TerritoryPlanningOperations;" + source[match.start() :]

if 'href="#territory-positioning"' not in source:
    anchor = "        <a href=\"#reminders\" class=\"ks-tab\">{{ t('events.manage.reminders') }}</a>"
    territory_tab = """        <a
          v-if="territoryPlanning.supported"
          href="#territory-positioning"
          class="ks-tab"
        >{{ t('territory.eventPositioningTitle') }}</a>
""" + anchor
    source = replace_once(source, anchor, territory_tab, "Territory Event tab")

if "<EventTerritoryPositioning" not in source:
    anchor = """      <div
        id="schedule""" 
    territory_surface = """      <EventTerritoryPositioning
        v-if="territoryPlanning.supported"
        class="mt-5"
        :occurrences="event.occurrences"
        :planning="territoryPlanning"
      />

      <div
        id="schedule"""
    source = replace_once(source, anchor, territory_surface, "Territory Event positioning surface")

manage.write_text(source)

visual_spec = Path("tests/v3/Visual/TerritoryPlanner.spec.ts")
source = visual_spec.read_text()
source = source.replace("ux-p9-visual@example.test", "territory-visual@example.test")
old_switch = """  const identitySwitcher = page.locator('button[aria-haspopup=\"listbox\"]:visible').first();
  await identitySwitcher.click();
  await page.getByRole('listbox', { name: 'Active Governor' }).getByRole('option').nth(0).click();
  await page.waitForURL('**/dashboard');"""
new_switch = """  const identitySwitcher = page.locator('button[aria-haspopup=\"listbox\"]:visible').first();
  if (await identitySwitcher.isVisible()) {
    const label = (await identitySwitcher.textContent()) ?? '';
    if (/select governor/i.test(label)) {
      await identitySwitcher.click();
      await page.getByRole('listbox', { name: 'Active Governor' }).getByRole('option').first().click();
      await page.waitForURL('**/dashboard');
    }
  }"""
if old_switch in source:
    source = source.replace(old_switch, new_switch, 1)
visual_spec.write_text(source)

english = Path("resources/js/localization/messages/territory/en.ts")
text = english.read_text()
if "violations:" not in text:
    anchor = "    bannerEfficiency: 'Covered cities / Banner',\n"
    if anchor not in text:
        raise RuntimeError("Missing English Territory analysis localization anchor")
    text = text.replace(
        anchor,
        anchor
        + "    violations: 'Blocking issues',\n"
        + "    warnings: 'Planning warnings',\n"
        + "    suggestions: 'Planning suggestions',\n",
        1,
    )
    english.write_text(text)

print("Territory UI finalization applied.")
