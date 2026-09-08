from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    target = Path(path)
    text = target.read_text()
    if old not in text:
        raise SystemExit(f"expected source shape not found in {path}: {old[:140]!r}")
    if text.count(old) != 1:
        raise SystemExit(f"expected one source match in {path}, found {text.count(old)}")
    target.write_text(text.replace(old, new, 1))


types = 'resources/js/components/progression/governorProgressionTypes.ts'
replace_once(
    types,
    '''    governorGear: Record<string, Record<string, GovernorProgressionFact>>;\n    charms: Record<string, Record<string, GovernorProgressionFact>>;\n    completeRosterCapture: GovernorProgressionFact | null;''',
    '''    governorGear: Record<string, Record<string, GovernorProgressionFact>>;\n    charms: Record<string, Record<string, GovernorProgressionFact>>;\n    buildings: Record<string, Record<string, GovernorProgressionFact>>;\n    academyResearch: Record<string, Record<string, GovernorProgressionFact>>;\n    warAcademyResearch: Record<string, Record<string, GovernorProgressionFact>>;\n    completeRosterCapture: GovernorProgressionFact | null;''',
)

component = 'resources/js/components/progression/GovernorProgressionScreenshotIntake.vue'
replace_once(
    component,
    '''type ReviewCharmRow = {\n  slot_id: string;\n  level: string;\n};''',
    '''type ReviewCharmRow = {\n  slot_id: string;\n  level: string;\n};\n\ntype ReviewStructuredStateRow = {\n  subject_id: string;\n  level: string;\n};''',
)
replace_once(
    component,
    '''  gear: ReviewGearRow[];\n  charms: ReviewCharmRow[];\n};''',
    '''  gear: ReviewGearRow[];\n  charms: ReviewCharmRow[];\n  states: ReviewStructuredStateRow[];\n};''',
)
replace_once(
    component,
    '''  governor_gear: t('progression.governorGearScreenshot'),\n  governor_charms: t('progression.governorCharmsScreenshot'),''',
    '''  governor_gear: t('progression.governorGearScreenshot'),\n  governor_charms: t('progression.governorCharmsScreenshot'),\n  governor_buildings: t('progression.governorBuildingsScreenshot'),\n  governor_academy_research: t('progression.governorAcademyResearchScreenshot'),\n  governor_war_academy_research: t('progression.governorWarAcademyResearchScreenshot'),''',
)
replace_once(
    component,
    '''  charm_level: t('progression.level'),\n  complete_roster_capture: t('progression.completeRosterCapture'),''',
    '''  charm_level: t('progression.level'),\n  building_name: t('progression.buildingName'),\n  building_level: t('progression.level'),\n  technology_name: t('progression.technologyName'),\n  research_level: t('progression.level'),\n  state_id: t('progression.factualState'),\n  complete_roster_capture: t('progression.completeRosterCapture'),''',
)
replace_once(
    component,
    '''    gear: [],\n    charms: [],\n  };''',
    '''    gear: [],\n    charms: [],\n    states: [],\n  };''',
)
replace_once(
    component,
    '''  } else if (item.detectedKind === 'governor_charms') {\n    draft.charms = ordinals(item, 'charm_slot').map((ordinal) => ({\n      slot_id: value(item, 'charm_slot', ordinal),\n      level: value(item, 'charm_level', ordinal),\n    }));\n  }''',
    '''  } else if (item.detectedKind === 'governor_charms') {\n    draft.charms = ordinals(item, 'charm_slot').map((ordinal) => ({\n      slot_id: value(item, 'charm_slot', ordinal),\n      level: value(item, 'charm_level', ordinal),\n    }));\n  } else if (\n    item.detectedKind === 'governor_buildings' ||\n    item.detectedKind === 'governor_academy_research' ||\n    item.detectedKind === 'governor_war_academy_research'\n  ) {\n    const subjectField = item.detectedKind === 'governor_buildings' ? 'building_name' : 'technology_name';\n    const levelField = item.detectedKind === 'governor_buildings' ? 'building_level' : 'research_level';\n    draft.states = ordinals(item, subjectField).map((ordinal) => ({\n      subject_id: value(item, subjectField, ordinal),\n      level: value(item, levelField, ordinal),\n    }));\n    if (draft.states.length === 0) {\n      draft.states.push({ subject_id: '', level: '' });\n    }\n  }''',
)
replace_once(
    component,
    '''  if (draft.kind === 'governor_gear') {\n    return {\n      gear: draft.gear.map((gear) => {\n        const row: ReviewPayload = { slot_id: gear.slot_id };\n        if (gear.quality.trim()) row.quality = gear.quality.trim();\n        const level = optionalNumber(gear.level);\n        const star = optionalNumber(gear.star);\n        if (level !== undefined) row.level = level;\n        if (star !== undefined) row.star = star;\n        return row;\n      }),\n    };\n  }\n\n  return {\n    charms: draft.charms.map((charm) => {''',
    '''  if (draft.kind === 'governor_gear') {\n    return {\n      gear: draft.gear.map((gear) => {\n        const row: ReviewPayload = { slot_id: gear.slot_id };\n        if (gear.quality.trim()) row.quality = gear.quality.trim();\n        const level = optionalNumber(gear.level);\n        const star = optionalNumber(gear.star);\n        if (level !== undefined) row.level = level;\n        if (star !== undefined) row.star = star;\n        return row;\n      }),\n    };\n  }\n\n  if (\n    draft.kind === 'governor_buildings' ||\n    draft.kind === 'governor_academy_research' ||\n    draft.kind === 'governor_war_academy_research'\n  ) {\n    return {\n      states: draft.states\n        .filter((state) => state.subject_id.trim() !== '' && state.level.trim() !== '')\n        .map((state) => ({\n          subject_id: state.subject_id.trim(),\n          level: optionalNumber(state.level),\n        })),\n    };\n  }\n\n  return {\n    charms: draft.charms.map((charm) => {''',
)
replace_once(
    component,
    '''function addCharmRow(): void {\n  reviewDraft.value.charms.push({ slot_id: '', level: '' });\n}\n''',
    '''function addCharmRow(): void {\n  reviewDraft.value.charms.push({ slot_id: '', level: '' });\n}\n\nfunction addStructuredStateRow(): void {\n  reviewDraft.value.states.push({ subject_id: '', level: '' });\n}\n\nfunction structuredSubjectLabel(): string {\n  return reviewDraft.value.kind === 'governor_buildings'\n    ? t('progression.buildingName')\n    : t('progression.technologyName');\n}\n''',
)

# Render accepted structured progression facts next to Gear/Charms.
replace_once(
    component,
    '''        <details\n          v-if="progressionState.history.length"''',
    '''        <div\n          v-if="\n            Object.keys(progressionState.current.buildings).length ||\n            Object.keys(progressionState.current.academyResearch).length ||\n            Object.keys(progressionState.current.warAcademyResearch).length\n          "\n          class="grid gap-4 lg:grid-cols-3"\n        >\n          <article\n            v-for="group in [\n              { label: t('progression.governorBuildingsScreenshot'), facts: progressionState.current.buildings },\n              { label: t('progression.governorAcademyResearchScreenshot'), facts: progressionState.current.academyResearch },\n              { label: t('progression.governorWarAcademyResearchScreenshot'), facts: progressionState.current.warAcademyResearch },\n            ]"\n            :key="group.label"\n            class="rounded border border-[var(--ks-border)] p-4"\n          >\n            <h3 class="font-semibold">{{ group.label }}</h3>\n            <div class="mt-3 space-y-2">\n              <div\n                v-for="(facts, subject) in group.facts"\n                :key="String(subject)"\n                class="rounded border border-[var(--ks-border)] p-3 text-xs"\n              >\n                <p class="font-semibold">{{ subject }}</p>\n                <p class="mt-1 text-[var(--ks-muted)]">\n                  <span v-for="(fact, key) in facts" :key="key" class="me-2">\n                    {{ labelForFact(String(key)) }}: {{ factValue(fact) }}\n                  </span>\n                </p>\n              </div>\n            </div>\n          </article>\n        </div>\n\n        <details\n          v-if="progressionState.history.length"''',
)

# Add the review editor before the existing Charms editor.
replace_once(
    component,
    '''              <fieldset v-else-if="reviewDraft.kind === 'governor_charms'" class="space-y-3">''',
    '''              <fieldset\n                v-else-if="\n                  reviewDraft.kind === 'governor_buildings' ||\n                  reviewDraft.kind === 'governor_academy_research' ||\n                  reviewDraft.kind === 'governor_war_academy_research'\n                "\n                class="space-y-3"\n              >\n                <legend class="font-semibold">{{ labelForClass(reviewDraft.kind) }}</legend>\n                <div\n                  v-for="(state, index) in reviewDraft.states"\n                  :key="index"\n                  class="grid gap-3 rounded border border-[var(--ks-border)] p-3 sm:grid-cols-[minmax(0,1fr)_8rem_auto] sm:items-end"\n                >\n                  <label class="text-xs text-[var(--ks-muted)]">\n                    <span>{{ structuredSubjectLabel() }}</span>\n                    <input\n                      v-model="state.subject_id"\n                      required\n                      class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-3 text-sm"\n                    />\n                  </label>\n                  <label class="text-xs text-[var(--ks-muted)]">\n                    <span>{{ t('progression.level') }}</span>\n                    <input\n                      v-model="state.level"\n                      required\n                      inputmode="numeric"\n                      class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-3 text-sm"\n                    />\n                  </label>\n                  <button\n                    type="button"\n                    class="min-h-11 rounded border border-[var(--ks-border)] px-3 text-sm"\n                    @click="reviewDraft.states.splice(index, 1)"\n                  >\n                    {{ t('progression.removeRow') }}\n                  </button>\n                </div>\n                <button\n                  type="button"\n                  class="min-h-11 rounded border border-[var(--ks-border)] px-3 text-sm"\n                  @click="addStructuredStateRow"\n                >\n                  {{ t('progression.addProgressionState') }}\n                </button>\n              </fieldset>\n\n              <fieldset v-else-if="reviewDraft.kind === 'governor_charms'" class="space-y-3">''',
)

localization = 'resources/js/localization/messages/progression/en.ts'
replace_once(
    localization,
    '''    governorGearScreenshot: 'Governor Gear',\n    governorCharmsScreenshot: 'Governor Charms',''',
    '''    governorGearScreenshot: 'Governor Gear',\n    governorCharmsScreenshot: 'Governor Charms',\n    governorBuildingsScreenshot: 'Buildings',\n    governorAcademyResearchScreenshot: 'Academy Research',\n    governorWarAcademyResearchScreenshot: 'War Academy Research',''',
)
replace_once(
    localization,
    '''    observedCharmName: 'Observed Charm name',\n    addGearSlot: 'Add Gear slot',''',
    '''    observedCharmName: 'Observed Charm name',\n    buildingName: 'Building',\n    technologyName: 'Technology',\n    factualState: 'Factual state',\n    addProgressionState: 'Add progression state',\n    addGearSlot: 'Add Gear slot',''',
)

print('Progression structured screenshot UI edits applied successfully.')
