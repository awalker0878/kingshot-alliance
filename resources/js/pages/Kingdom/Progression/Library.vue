<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type Hero = {
  id: string;
  name: string;
  rarity: string;
  troop_class: string;
  generation: number;
  typical_unlock_day: number;
};
type Formation = {
  id: string;
  name: string;
  infantry: number;
  cavalry: number;
  archer: number;
  mode: string;
  scope: string;
  evidence_status: string;
  source_ids: string[];
};
type Source = {
  id: string;
  label: string;
  uri: string;
  authority_tier: string;
  official: boolean;
  retrieved_at: string;
  license_note: string;
};
type Conflict = {
  id: string;
  family: string;
  description: string;
  claims: Array<{ source_id: string; value: string | number; unit: string }>;
  resolution: string;
};
type SourceGap = {
  id: string;
  family: string;
  source_id: string;
  entity: string;
  declared_max_level?: number;
  missing_visible_level_rows?: number;
  status: string;
  resolution: string;
};
type Disposition = {
  family: string;
  status: string;
  discovered_entities: number;
  canonical_entities: number;
  facts_imported?: number;
  unresolved_level_tables?: number;
  reason: string;
};
type FamilyRow = {
  path: string;
  values: Record<string, string | null>;
  sourceIds: string[];
  confidence: string | null;
};
type FamilyData = {
  family: string;
  columns: string[];
  rows: FamilyRow[];
  page: number;
  perPage: number;
  total: number;
  lastPage: number;
  sourceMeta: Record<string, unknown> | null;
};
type Systems = {
  hero_progression: { max_level: number; star_levels: number; shards_to_max_star: number };
  exclusive_equipment: {
    unlock_town_center: number;
    max_level: number;
    total_widgets_to_level_10: number;
  };
  hero_gear: {
    unlock_town_center: number;
    quality_level_caps: Record<string, number>;
    mastery_forging: { unlock_town_center: number; max_level: number };
  };
  governor_gear: {
    unlock_town_center: number;
    slots: Array<{ slot: string; troop_class: string; stat: string }>;
  };
  governor_charms: { unlock_town_center: number; families: string[]; evidence_status: string };
  research: {
    academy: {
      technologies: number;
      levels: number;
      trees: Array<{ name: string; technologies: number; levels: number }>;
    };
  };
  pets: string[];
  masters: Array<{ name: string; title: string }>;
};

const props = defineProps<{
  user: { name: string; email: string };
  dataset: {
    id: string;
    version: string;
    schemaVersion: number;
    observed_at: string;
    checksum: string;
    review_status: string;
  };
  filters: {
    q: string;
    generation: number | null;
    troop_class: string | null;
    family: string | null;
    family_q: string | null;
  };
  heroes: Hero[];
  formations: Formation[];
  systems: Systems;
  sources: Source[];
  conflicts: Conflict[];
  sourceGaps: SourceGap[];
  dispositions: Disposition[];
  familyOptions: string[];
  familyData: FamilyData | null;
}>();

const { t, formatNumber } = useLocale();
const search = ref(props.filters.q ?? '');
const generation = ref(props.filters.generation?.toString() ?? '');
const troopClass = ref(props.filters.troop_class ?? '');
const family = ref(props.filters.family ?? '');
const familySearch = ref(props.filters.family_q ?? '');
const sourceById = computed(() => new Map(props.sources.map((source) => [source.id, source])));
const coverage = computed(() => ({
  discovered: props.dispositions.reduce((sum, row) => sum + row.discovered_entities, 0),
  canonical: props.dispositions.reduce((sum, row) => sum + row.canonical_entities, 0),
  conflicts: props.conflicts.length,
  gaps: props.sourceGaps.length,
}));

function commonQuery(): Record<string, string | number | undefined> {
  return {
    q: search.value || undefined,
    generation: generation.value || undefined,
    troop_class: troopClass.value || undefined,
    family: family.value || undefined,
    family_q: familySearch.value || undefined,
  };
}

function applyHeroFilters(): void {
  router.get('/progression', commonQuery(), { preserveState: true, replace: true });
}

function clearHeroFilters(): void {
  search.value = '';
  generation.value = '';
  troopClass.value = '';
  applyHeroFilters();
}

function browseFamily(page = 1): void {
  router.get(
    '/progression',
    { ...commonQuery(), family_page: page },
    { preserveState: true, preserveScroll: true, replace: true },
  );
}

function clearFamily(): void {
  family.value = '';
  familySearch.value = '';
  browseFamily(1);
}

function familyLabel(value: string): string {
  return value
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}

function sourceLabels(ids: string[]): string {
  if (ids.length === 0) return t('progression.unknown');
  return ids.map((id) => sourceById.value.get(id)?.label ?? id).join(' · ');
}

function sourceMetaValue(key: string): string | null {
  const value = props.familyData?.sourceMeta?.[key];
  if (value === undefined || value === null || value === '') return null;
  if (Array.isArray(value)) return value.map(String).join(' · ');
  if (typeof value === 'object') return JSON.stringify(value);
  return String(value);
}
</script>

<template>
  <Head :title="t('progression.title')" />
  <AppLayout :user="user">
    <div class="mx-auto w-full max-w-[104rem] space-y-6">
      <section
        class="overflow-hidden rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] bg-[rgba(5,13,13,.88)] p-5 shadow-xl sm:p-7"
        aria-labelledby="progression-title"
      >
        <div class="grid gap-5 xl:grid-cols-[1fr_auto] xl:items-start">
          <div>
            <p class="ks-kicker">{{ t('progression.eyebrow') }}</p>
            <h1 id="progression-title" class="ks-display mt-2 text-3xl font-semibold sm:text-4xl">
              {{ t('progression.title') }}
            </h1>
            <p class="mt-3 max-w-4xl text-sm leading-6 text-[var(--ks-text-secondary)]">
              {{ t('progression.subtitle') }}
            </p>
          </div>
          <dl class="grid grid-cols-2 gap-2 text-xs sm:grid-cols-4 xl:min-w-[38rem]">
            <div class="rounded border border-[var(--ks-border)] p-3">
              <dt class="text-[var(--ks-muted)]">{{ t('progression.datasetVersion') }}</dt>
              <dd class="mt-1 font-semibold">{{ dataset.version }}</dd>
            </div>
            <div class="rounded border border-[var(--ks-border)] p-3">
              <dt class="text-[var(--ks-muted)]">{{ t('progression.schemaVersion') }}</dt>
              <dd class="mt-1 font-semibold">{{ dataset.schemaVersion }}</dd>
            </div>
            <div class="rounded border border-[var(--ks-border)] p-3">
              <dt class="text-[var(--ks-muted)]">{{ t('progression.conflicts') }}</dt>
              <dd class="mt-1 font-semibold">{{ coverage.conflicts }}</dd>
            </div>
            <div class="rounded border border-[var(--ks-border)] p-3">
              <dt class="text-[var(--ks-muted)]">{{ t('progression.sourceGaps') }}</dt>
              <dd class="mt-1 font-semibold">{{ coverage.gaps }}</dd>
            </div>
          </dl>
        </div>
        <div
          class="mt-4 rounded border border-amber-400/30 bg-amber-300/5 p-3 text-sm text-amber-100"
        >
          <strong>{{ t('progression.factualOnly') }}</strong>
          {{ t('progression.noRecommendations') }}
        </div>
        <dl class="mt-4 grid gap-2 text-xs md:grid-cols-2">
          <div class="rounded border border-[var(--ks-border)] bg-black/10 p-3">
            <dt class="font-semibold text-[var(--ks-text-secondary)]">
              {{ t('progression.datasetChecksum') }}
            </dt>
            <dd class="mt-1 font-mono text-[0.68rem] break-all text-[var(--ks-muted)]">
              {{ dataset.checksum }}
            </dd>
          </div>
          <div class="rounded border border-[var(--ks-border)] bg-black/10 p-3">
            <dt class="font-semibold text-[var(--ks-text-secondary)]">
              {{ t('progression.observedAt') }}
            </dt>
            <dd class="mt-1 text-[var(--ks-muted)]">
              {{ dataset.observed_at }} · {{ dataset.review_status }}
            </dd>
          </div>
        </dl>
      </section>

      <section class="ks-surface p-5 sm:p-6" aria-labelledby="facts-heading">
        <div class="grid gap-4 xl:grid-cols-[1fr_auto] xl:items-end">
          <div>
            <p class="ks-kicker">{{ t('progression.detailedFacts') }}</p>
            <h2 id="facts-heading" class="ks-display mt-1 text-2xl font-semibold">
              {{ familyData ? familyLabel(familyData.family) : t('progression.selectFamily') }}
            </h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--ks-text-secondary)]">
              {{ t('progression.detailedFactsHelp') }}
            </p>
          </div>
          <form
            class="grid gap-2 sm:grid-cols-[minmax(13rem,1fr)_minmax(13rem,1fr)_auto]"
            @submit.prevent="browseFamily(1)"
          >
            <label class="text-xs text-[var(--ks-muted)]">
              <span>{{ t('progression.family') }}</span>
              <select
                v-model="family"
                class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-[#091313] px-3 text-sm"
                @change="browseFamily(1)"
              >
                <option value="">{{ t('progression.selectFamily') }}</option>
                <option v-for="option in familyOptions" :key="option" :value="option">
                  {{ familyLabel(option) }}
                </option>
              </select>
            </label>
            <label class="text-xs text-[var(--ks-muted)]">
              <span>{{ t('progression.searchFamily') }}</span>
              <input
                v-model="familySearch"
                type="search"
                class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-3 text-sm"
                :disabled="family === ''"
              />
            </label>
            <div class="flex items-end gap-2">
              <button
                class="min-h-11 rounded bg-[var(--ks-teal)] px-4 text-sm font-semibold disabled:opacity-40"
                type="submit"
                :disabled="family === ''"
              >
                {{ t('progression.showFamily') }}
              </button>
              <button
                v-if="family !== '' || familySearch !== ''"
                class="min-h-11 rounded border border-[var(--ks-border)] px-3 text-sm"
                type="button"
                @click="clearFamily"
              >
                {{ t('progression.clear') }}
              </button>
            </div>
          </form>
        </div>

        <div v-if="familyData" class="mt-5 space-y-4">
          <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="ks-chip"
              >{{ formatNumber(familyData.total) }} {{ t('progression.factRows') }}</span
            >
            <span v-if="sourceMetaValue('confidence')" class="ks-chip">
              {{ t('progression.sourceConfidence') }}: {{ sourceMetaValue('confidence') }}
            </span>
            <span v-if="sourceMetaValue('verified') || sourceMetaValue('updated')" class="ks-chip">
              {{ t('progression.sourceVerified') }}:
              {{ sourceMetaValue('verified') ?? sourceMetaValue('updated') }}
            </span>
            <span v-if="sourceMetaValue('license')" class="ks-chip">{{
              sourceMetaValue('license')
            }}</span>
          </div>

          <div v-if="familyData.rows.length" class="space-y-3 md:hidden">
            <article
              v-for="row in familyData.rows"
              :key="row.path"
              class="rounded border border-[var(--ks-border)] bg-black/10 p-4"
            >
              <dl class="space-y-2 text-sm">
                <div
                  v-for="column in familyData.columns"
                  :key="column"
                  class="grid grid-cols-[minmax(7rem,.42fr)_1fr] gap-3"
                >
                  <dt class="text-xs font-semibold text-[var(--ks-muted)]">{{ column }}</dt>
                  <dd class="break-words text-[var(--ks-text-secondary)]">
                    {{ row.values[column] ?? t('progression.unknown') }}
                  </dd>
                </div>
              </dl>
              <div
                class="mt-3 border-t border-[var(--ks-border)] pt-3 text-xs text-[var(--ks-muted)]"
              >
                <p>
                  <strong>{{ t('progression.provenance') }}:</strong>
                  {{ sourceLabels(row.sourceIds) }}
                </p>
                <p v-if="row.confidence" class="mt-1">
                  <strong>{{ t('progression.sourceConfidence') }}:</strong> {{ row.confidence }}
                </p>
              </div>
            </article>
          </div>

          <div
            v-if="familyData.rows.length"
            class="hidden overflow-x-auto rounded border border-[var(--ks-border)] md:block"
          >
            <table class="min-w-full text-left text-xs">
              <thead class="bg-black/25 text-[var(--ks-muted)]">
                <tr>
                  <th
                    v-for="column in familyData.columns"
                    :key="column"
                    class="px-3 py-2 font-semibold whitespace-nowrap"
                  >
                    {{ column }}
                  </th>
                  <th class="px-3 py-2 font-semibold whitespace-nowrap">
                    {{ t('progression.provenance') }}
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[var(--ks-border)]">
                <tr
                  v-for="row in familyData.rows"
                  :key="row.path"
                  class="align-top hover:bg-white/[.02]"
                >
                  <td
                    v-for="column in familyData.columns"
                    :key="column"
                    class="max-w-[28rem] px-3 py-2.5 text-[var(--ks-text-secondary)]"
                  >
                    {{ row.values[column] ?? '—' }}
                  </td>
                  <td class="max-w-[18rem] px-3 py-2.5 text-[var(--ks-muted)]">
                    {{ sourceLabels(row.sourceIds) }}
                    <span v-if="row.confidence" class="mt-1 block">{{ row.confidence }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <p
            v-else
            class="rounded border border-dashed border-[var(--ks-border)] p-5 text-sm text-[var(--ks-muted)]"
          >
            {{ t('progression.noFamilyRows') }}
          </p>

          <nav
            v-if="familyData.lastPage > 1"
            class="flex items-center justify-between gap-3"
            :aria-label="t('common.pagination')"
          >
            <button
              type="button"
              class="min-h-11 rounded border border-[var(--ks-border)] px-4 text-sm disabled:opacity-35"
              :disabled="familyData.page <= 1"
              @click="browseFamily(familyData.page - 1)"
            >
              ← {{ t('progression.previous') }}
            </button>
            <span class="text-xs text-[var(--ks-muted)]">
              {{ t('progression.pageOf', { page: familyData.page, pages: familyData.lastPage }) }}
            </span>
            <button
              type="button"
              class="min-h-11 rounded border border-[var(--ks-border)] px-4 text-sm disabled:opacity-35"
              :disabled="familyData.page >= familyData.lastPage"
              @click="browseFamily(familyData.page + 1)"
            >
              {{ t('progression.next') }} →
            </button>
          </nav>
        </div>
      </section>

      <section aria-labelledby="hero-heading" class="ks-surface p-5 sm:p-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
          <div>
            <h2 id="hero-heading" class="ks-display text-2xl font-semibold">
              {{ t('progression.heroRoster') }}
            </h2>
            <p class="mt-1 text-sm text-[var(--ks-muted)]">{{ t('progression.heroRosterHelp') }}</p>
          </div>
          <form class="grid gap-2 sm:grid-cols-4" @submit.prevent="applyHeroFilters">
            <label class="text-xs text-[var(--ks-muted)]">
              <span class="sr-only">{{ t('progression.search') }}</span>
              <input
                v-model="search"
                type="search"
                :placeholder="t('progression.search')"
                class="min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-3"
              />
            </label>
            <label class="text-xs text-[var(--ks-muted)]">
              <span class="sr-only">{{ t('progression.generation') }}</span>
              <select
                v-model="generation"
                class="min-h-11 w-full rounded border border-[var(--ks-border)] bg-[#091313] px-3"
              >
                <option value="">{{ t('progression.allGenerations') }}</option>
                <option v-for="value in 7" :key="value" :value="String(value)">
                  {{ t('progression.generationShort', { value }) }}
                </option>
              </select>
            </label>
            <label class="text-xs text-[var(--ks-muted)]">
              <span class="sr-only">{{ t('progression.troopClass') }}</span>
              <select
                v-model="troopClass"
                class="min-h-11 w-full rounded border border-[var(--ks-border)] bg-[#091313] px-3"
              >
                <option value="">{{ t('progression.allClasses') }}</option>
                <option value="Infantry">{{ t('progression.infantry') }}</option>
                <option value="Cavalry">{{ t('progression.cavalry') }}</option>
                <option value="Archer">{{ t('progression.archer') }}</option>
              </select>
            </label>
            <div class="flex gap-2">
              <button
                type="submit"
                class="min-h-11 flex-1 rounded bg-[var(--ks-teal)] px-3 font-semibold"
              >
                {{ t('progression.apply') }}
              </button>
              <button
                type="button"
                class="min-h-11 rounded border border-[var(--ks-border)] px-3"
                @click="clearHeroFilters"
              >
                {{ t('progression.clear') }}
              </button>
            </div>
          </form>
        </div>
        <div
          v-if="heroes.length"
          class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
          <article
            v-for="hero in heroes"
            :key="hero.id"
            class="rounded border border-[var(--ks-border)] bg-black/15 p-4"
          >
            <div class="flex items-start justify-between gap-3">
              <div>
                <h3 class="ks-display text-lg text-[var(--ks-gold-bright)]">{{ hero.name }}</h3>
                <p class="text-xs text-[var(--ks-muted)]">
                  {{ hero.rarity }} · {{ hero.troop_class }}
                </p>
              </div>
              <span class="rounded-full border border-[var(--ks-border)] px-2 py-1 text-xs"
                >G{{ hero.generation }}</span
              >
            </div>
            <p class="mt-3 text-xs text-[var(--ks-text-secondary)]">
              {{ t('progression.typicalUnlock', { day: hero.typical_unlock_day }) }}
            </p>
          </article>
        </div>
        <p
          v-else
          class="mt-5 rounded border border-dashed border-[var(--ks-border)] p-5 text-sm text-[var(--ks-muted)]"
        >
          {{ t('progression.noHeroes') }}
        </p>
      </section>

      <section class="grid gap-4 lg:grid-cols-3">
        <article class="ks-surface p-5">
          <h2 class="ks-display text-xl font-semibold">{{ t('progression.heroProgression') }}</h2>
          <dl class="mt-4 space-y-2 text-sm">
            <div class="flex justify-between gap-3">
              <dt>{{ t('progression.maxHeroLevel') }}</dt>
              <dd>{{ systems.hero_progression.max_level }}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt>{{ t('progression.shardsToMax') }}</dt>
              <dd>{{ formatNumber(systems.hero_progression.shards_to_max_star) }}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt>{{ t('progression.widgetMax') }}</dt>
              <dd>{{ systems.exclusive_equipment.max_level }}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt>{{ t('progression.widgetsTotal') }}</dt>
              <dd>{{ systems.exclusive_equipment.total_widgets_to_level_10 }}</dd>
            </div>
          </dl>
        </article>
        <article class="ks-surface p-5">
          <h2 class="ks-display text-xl font-semibold">{{ t('progression.gear') }}</h2>
          <dl class="mt-4 space-y-2 text-sm">
            <div class="flex justify-between gap-3">
              <dt>{{ t('progression.heroGearUnlock') }}</dt>
              <dd>
                {{
                  t('progression.townCenterLevel', { level: systems.hero_gear.unlock_town_center })
                }}
              </dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt>{{ t('progression.masteryUnlock') }}</dt>
              <dd>
                {{
                  t('progression.townCenterLevel', {
                    level: systems.hero_gear.mastery_forging.unlock_town_center,
                  })
                }}
              </dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt>{{ t('progression.masteryMax') }}</dt>
              <dd>{{ systems.hero_gear.mastery_forging.max_level }}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt>{{ t('progression.governorGearUnlock') }}</dt>
              <dd>
                {{
                  t('progression.townCenterLevel', {
                    level: systems.governor_gear.unlock_town_center,
                  })
                }}
              </dd>
            </div>
          </dl>
        </article>
        <article class="ks-surface p-5">
          <h2 class="ks-display text-xl font-semibold">{{ t('progression.research') }}</h2>
          <dl class="mt-4 space-y-2 text-sm">
            <div class="flex justify-between gap-3">
              <dt>{{ t('progression.technologies') }}</dt>
              <dd>{{ systems.research.academy.technologies }}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt>{{ t('progression.researchLevels') }}</dt>
              <dd>{{ systems.research.academy.levels }}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt>{{ t('progression.pets') }}</dt>
              <dd>{{ systems.pets.length }}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt>{{ t('progression.masters') }}</dt>
              <dd>{{ systems.masters.length }}</dd>
            </div>
          </dl>
        </article>
      </section>

      <section class="ks-surface p-5 sm:p-6" aria-labelledby="formations-heading">
        <h2 id="formations-heading" class="ks-display text-2xl font-semibold">
          {{ t('progression.formations') }}
        </h2>
        <p class="mt-1 text-sm text-[var(--ks-muted)]">{{ t('progression.formationsHelp') }}</p>
        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
          <article
            v-for="formation in formations"
            :key="formation.id"
            class="rounded border border-[var(--ks-border)] p-4"
          >
            <div class="flex items-start justify-between gap-2">
              <h3 class="font-semibold">{{ formation.name }}</h3>
              <span
                class="rounded bg-amber-300/10 px-2 py-1 text-[.65rem] font-bold text-amber-200"
                >{{ t('progression.communityConvention') }}</span
              >
            </div>
            <p class="ks-display mt-3 text-lg">
              {{ formation.infantry }} / {{ formation.cavalry }} / {{ formation.archer }}
            </p>
            <p class="text-xs text-[var(--ks-muted)]">
              {{ t('progression.infantry') }} / {{ t('progression.cavalry') }} /
              {{ t('progression.archer') }}
            </p>
            <p class="mt-3 text-xs leading-5 text-[var(--ks-text-secondary)]">
              {{ formation.scope }}
            </p>
            <p class="mt-2 text-xs text-[var(--ks-muted)]">
              {{ sourceLabels(formation.source_ids) }}
            </p>
          </article>
        </div>
      </section>

      <section v-if="conflicts.length || sourceGaps.length" class="grid gap-4 xl:grid-cols-2">
        <article
          v-if="conflicts.length"
          class="rounded-[var(--ks-radius-lg)] border border-amber-400/30 p-5"
        >
          <h2 class="ks-display text-2xl font-semibold text-amber-100">
            {{ t('progression.sourceConflicts') }}
          </h2>
          <div class="mt-4 space-y-3">
            <div
              v-for="conflict in conflicts"
              :key="conflict.id"
              class="rounded border border-amber-400/20 bg-amber-300/5 p-4"
            >
              <h3 class="font-semibold">{{ familyLabel(conflict.family) }}</h3>
              <p class="mt-1 text-sm">{{ conflict.description }}</p>
              <ul
                v-if="conflict.claims.length"
                class="mt-2 text-xs text-[var(--ks-text-secondary)]"
              >
                <li v-for="claim in conflict.claims" :key="`${claim.source_id}-${claim.value}`">
                  {{ sourceById.get(claim.source_id)?.label ?? claim.source_id }}: {{ claim.value }}
                  {{ claim.unit }}
                </li>
              </ul>
              <p class="mt-2 text-xs text-amber-200">{{ conflict.resolution }}</p>
            </div>
          </div>
        </article>
        <article
          v-if="sourceGaps.length"
          class="rounded-[var(--ks-radius-lg)] border border-sky-400/30 p-5"
        >
          <h2 class="ks-display text-2xl font-semibold text-sky-100">
            {{ t('progression.sourceGaps') }}
          </h2>
          <div class="mt-4 space-y-3">
            <div
              v-for="gap in sourceGaps"
              :key="gap.id"
              class="rounded border border-sky-400/20 bg-sky-300/5 p-4"
            >
              <h3 class="font-semibold">{{ gap.entity }}</h3>
              <p class="mt-1 text-xs text-[var(--ks-muted)]">
                {{ familyLabel(gap.family) }} ·
                {{ sourceById.get(gap.source_id)?.label ?? gap.source_id }}
              </p>
              <p class="mt-2 text-sm text-[var(--ks-text-secondary)]">{{ gap.resolution }}</p>
            </div>
          </div>
        </article>
      </section>

      <section class="ks-surface p-5 sm:p-6" aria-labelledby="coverage-heading">
        <h2 id="coverage-heading" class="ks-display text-2xl font-semibold">
          {{ t('progression.coverage') }}
        </h2>
        <p class="mt-1 text-sm text-[var(--ks-muted)]">{{ t('progression.coverageHelp') }}</p>
        <div class="mt-4 overflow-x-auto">
          <table class="min-w-full text-left text-sm">
            <thead class="text-xs text-[var(--ks-muted)]">
              <tr>
                <th class="p-2">{{ t('progression.family') }}</th>
                <th class="p-2">{{ t('progression.status') }}</th>
                <th class="p-2">{{ t('progression.discovered') }}</th>
                <th class="p-2">{{ t('progression.canonical') }}</th>
                <th class="p-2">{{ t('progression.disposition') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in dispositions"
                :key="row.family"
                class="border-t border-[var(--ks-border)] align-top"
              >
                <td class="p-2 font-medium">{{ familyLabel(row.family) }}</td>
                <td class="p-2">{{ row.status }}</td>
                <td class="p-2">{{ row.discovered_entities }}</td>
                <td class="p-2">{{ row.canonical_entities }}</td>
                <td class="max-w-2xl p-2 text-xs leading-5 text-[var(--ks-text-secondary)]">
                  {{ row.reason }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="ks-surface p-5 sm:p-6" aria-labelledby="sources-heading">
        <h2 id="sources-heading" class="ks-display text-2xl font-semibold">
          {{ t('progression.sources') }}
        </h2>
        <div class="mt-4 grid gap-3 lg:grid-cols-2">
          <article
            v-for="source in sources"
            :key="source.id"
            class="rounded border border-[var(--ks-border)] p-4"
          >
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="font-semibold">{{ source.label }}</h3>
              <span class="ks-chip">{{
                t('progression.authorityTier', { tier: source.authority_tier })
              }}</span>
              <span v-if="source.official" class="ks-chip">{{ t('progression.official') }}</span>
              <span v-else class="ks-chip">{{ t('progression.community') }}</span>
            </div>
            <p class="mt-2 text-xs leading-5 text-[var(--ks-muted)]">{{ source.license_note }}</p>
            <a
              :href="source.uri"
              target="_blank"
              rel="noopener noreferrer"
              class="mt-3 inline-block text-xs break-all text-[var(--ks-teal-bright)] underline underline-offset-2"
            >
              {{ source.uri }}
            </a>
          </article>
        </div>
      </section>
    </div>
  </AppLayout>
</template>
