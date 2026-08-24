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
  release_group: string;
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
type Disposition = {
  family: string;
  status: string;
  discovered_entities: number;
  canonical_entities: number;
  reason: string;
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
  max_levels: Record<string, number>;
};

const props = defineProps<{
  user: { name: string; email: string };
  dataset: {
    id: string;
    version: string;
    observed_at: string;
    checksum: string;
    review_status: string;
  };
  filters: { q: string; generation: number | null; troop_class: string | null };
  heroes: Hero[];
  formations: Formation[];
  systems: Systems;
  sources: Source[];
  conflicts: Conflict[];
  dispositions: Disposition[];
}>();

const { t, formatNumber } = useLocale();
const search = ref(props.filters.q ?? '');
const generation = ref(props.filters.generation?.toString() ?? '');
const troopClass = ref(props.filters.troop_class ?? '');
const sourceById = computed(() => new Map(props.sources.map((source) => [source.id, source])));
const coverage = computed(() => ({
  discovered: props.dispositions.reduce((sum, row) => sum + row.discovered_entities, 0),
  canonical: props.dispositions.reduce((sum, row) => sum + row.canonical_entities, 0),
  conflicts: props.conflicts.length,
}));

function applyFilters(): void {
  router.get(
    '/progression',
    {
      q: search.value || undefined,
      generation: generation.value || undefined,
      troop_class: troopClass.value || undefined,
    },
    { preserveState: true, replace: true },
  );
}

function clearFilters(): void {
  search.value = '';
  generation.value = '';
  troopClass.value = '';
  applyFilters();
}
</script>

<template>
  <Head :title="t('progression.title')" />
  <AppLayout :user="user">
    <main
      id="main-content"
      class="mx-auto w-full max-w-[96rem] space-y-6 px-4 py-6 sm:px-6 lg:px-8"
    >
      <section
        class="overflow-hidden rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] bg-[rgba(5,13,13,.88)] p-5 shadow-xl sm:p-7"
      >
        <div class="grid gap-5 lg:grid-cols-[1fr_auto] lg:items-start">
          <div>
            <p class="text-xs font-extrabold tracking-[.18em] text-[var(--ks-gold)] uppercase">
              {{ t('progression.eyebrow') }}
            </p>
            <h1
              class="mt-2 text-3xl font-[var(--ks-font-display)] text-[var(--ks-gold-bright)] sm:text-4xl"
            >
              {{ t('progression.title') }}
            </h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-[var(--ks-text-secondary)]">
              {{ t('progression.subtitle') }}
            </p>
          </div>
          <dl class="grid grid-cols-2 gap-2 text-xs sm:grid-cols-3 lg:min-w-[28rem]">
            <div class="rounded border border-[var(--ks-border)] p-3">
              <dt class="text-[var(--ks-muted)]">{{ t('progression.datasetVersion') }}</dt>
              <dd class="mt-1 font-semibold">{{ dataset.version }}</dd>
            </div>
            <div class="rounded border border-[var(--ks-border)] p-3">
              <dt class="text-[var(--ks-muted)]">{{ t('progression.heroes') }}</dt>
              <dd class="mt-1 font-semibold">34</dd>
            </div>
            <div class="rounded border border-[var(--ks-border)] p-3">
              <dt class="text-[var(--ks-muted)]">{{ t('progression.conflicts') }}</dt>
              <dd class="mt-1 font-semibold">{{ coverage.conflicts }}</dd>
            </div>
          </dl>
        </div>
        <div
          class="mt-4 rounded border border-amber-400/30 bg-amber-300/5 p-3 text-sm text-amber-100"
        >
          <strong>{{ t('progression.factualOnly') }}</strong>
          {{ t('progression.noRecommendations') }}
        </div>
      </section>

      <section
        aria-labelledby="hero-heading"
        class="rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] bg-[rgba(5,13,13,.78)] p-5"
      >
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <h2 id="hero-heading" class="text-2xl font-[var(--ks-font-display)]">
              {{ t('progression.heroRoster') }}
            </h2>
            <p class="mt-1 text-sm text-[var(--ks-muted)]">{{ t('progression.heroRosterHelp') }}</p>
          </div>
          <form class="grid gap-2 sm:grid-cols-4" @submit.prevent="applyFilters">
            <label class="text-xs text-[var(--ks-muted)]"
              ><span class="sr-only">{{ t('progression.search') }}</span
              ><input
                v-model="search"
                type="search"
                :placeholder="t('progression.search')"
                class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-3 text-[var(--ks-text)]"
            /></label>
            <label class="text-xs text-[var(--ks-muted)]"
              ><span class="sr-only">{{ t('progression.generation') }}</span
              ><select
                v-model="generation"
                class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-[#091313] px-3"
              >
                <option value="">{{ t('progression.allGenerations') }}</option>
                <option v-for="value in 7" :key="value" :value="String(value)">
                  {{ t('progression.generationShort', { value }) }}
                </option>
              </select></label
            >
            <label class="text-xs text-[var(--ks-muted)]"
              ><span class="sr-only">{{ t('progression.troopClass') }}</span
              ><select
                v-model="troopClass"
                class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-[#091313] px-3"
              >
                <option value="">{{ t('progression.allClasses') }}</option>
                <option value="Infantry">{{ t('progression.infantry') }}</option>
                <option value="Cavalry">{{ t('progression.cavalry') }}</option>
                <option value="Archer">{{ t('progression.archer') }}</option>
              </select></label
            >
            <div class="flex gap-2">
              <button
                type="submit"
                class="min-h-11 flex-1 rounded bg-[var(--ks-teal)] px-3 font-semibold"
              >
                {{ t('progression.apply') }}</button
              ><button
                type="button"
                class="min-h-11 rounded border border-[var(--ks-border)] px-3"
                @click="clearFilters"
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
                <h3 class="text-lg font-[var(--ks-font-display)] text-[var(--ks-gold-bright)]">
                  {{ hero.name }}
                </h3>
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
        <article class="rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] p-5">
          <h2 class="text-xl font-[var(--ks-font-display)]">
            {{ t('progression.heroProgression') }}
          </h2>
          <dl class="mt-4 space-y-2 text-sm">
            <div class="flex justify-between">
              <dt>{{ t('progression.maxHeroLevel') }}</dt>
              <dd>{{ systems.hero_progression.max_level }}</dd>
            </div>
            <div class="flex justify-between">
              <dt>{{ t('progression.shardsToMax') }}</dt>
              <dd>{{ formatNumber(systems.hero_progression.shards_to_max_star) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt>{{ t('progression.widgetMax') }}</dt>
              <dd>{{ systems.exclusive_equipment.max_level }}</dd>
            </div>
            <div class="flex justify-between">
              <dt>{{ t('progression.widgetsTotal') }}</dt>
              <dd>{{ systems.exclusive_equipment.total_widgets_to_level_10 }}</dd>
            </div>
          </dl>
        </article>
        <article class="rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] p-5">
          <h2 class="text-xl font-[var(--ks-font-display)]">{{ t('progression.gear') }}</h2>
          <dl class="mt-4 space-y-2 text-sm">
            <div class="flex justify-between">
              <dt>{{ t('progression.heroGearUnlock') }}</dt>
              <dd>
                {{
                  t('progression.townCenterLevel', { level: systems.hero_gear.unlock_town_center })
                }}
              </dd>
            </div>
            <div class="flex justify-between">
              <dt>{{ t('progression.masteryUnlock') }}</dt>
              <dd>
                {{
                  t('progression.townCenterLevel', {
                    level: systems.hero_gear.mastery_forging.unlock_town_center,
                  })
                }}
              </dd>
            </div>
            <div class="flex justify-between">
              <dt>{{ t('progression.masteryMax') }}</dt>
              <dd>{{ systems.hero_gear.mastery_forging.max_level }}</dd>
            </div>
            <div class="flex justify-between">
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
        <article class="rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] p-5">
          <h2 class="text-xl font-[var(--ks-font-display)]">{{ t('progression.research') }}</h2>
          <dl class="mt-4 space-y-2 text-sm">
            <div class="flex justify-between">
              <dt>{{ t('progression.technologies') }}</dt>
              <dd>{{ systems.research.academy.technologies }}</dd>
            </div>
            <div class="flex justify-between">
              <dt>{{ t('progression.researchLevels') }}</dt>
              <dd>{{ systems.research.academy.levels }}</dd>
            </div>
            <div class="flex justify-between">
              <dt>{{ t('progression.pets') }}</dt>
              <dd>{{ systems.pets.length }}</dd>
            </div>
            <div class="flex justify-between">
              <dt>{{ t('progression.masters') }}</dt>
              <dd>{{ systems.masters.length }}</dd>
            </div>
          </dl>
        </article>
      </section>

      <section
        aria-labelledby="formations-heading"
        class="rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] p-5"
      >
        <h2 id="formations-heading" class="text-2xl font-[var(--ks-font-display)]">
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
            <p class="mt-3 text-lg font-[var(--ks-font-display)]">
              {{ formation.infantry }} / {{ formation.cavalry }} / {{ formation.archer }}
            </p>
            <p class="text-xs text-[var(--ks-muted)]">
              {{ t('progression.infantry') }} / {{ t('progression.cavalry') }} /
              {{ t('progression.archer') }}
            </p>
            <p class="mt-3 text-xs leading-5 text-[var(--ks-text-secondary)]">
              {{ formation.scope }}
            </p>
          </article>
        </div>
      </section>

      <section
        v-if="conflicts.length"
        aria-labelledby="conflicts-heading"
        class="rounded-[var(--ks-radius-lg)] border border-amber-400/30 p-5"
      >
        <h2 id="conflicts-heading" class="text-2xl font-[var(--ks-font-display)] text-amber-100">
          {{ t('progression.sourceConflicts') }}
        </h2>
        <div class="mt-4 space-y-3">
          <article
            v-for="conflict in conflicts"
            :key="conflict.id"
            class="rounded border border-amber-400/20 bg-amber-300/5 p-4"
          >
            <h3 class="font-semibold">{{ conflict.family }}</h3>
            <p class="mt-1 text-sm">{{ conflict.description }}</p>
            <ul class="mt-2 text-xs text-[var(--ks-text-secondary)]">
              <li v-for="claim in conflict.claims" :key="`${claim.source_id}-${claim.value}`">
                {{ sourceById.get(claim.source_id)?.label ?? claim.source_id }}: {{ claim.value }}
                {{ claim.unit }}
              </li>
            </ul>
            <p class="mt-2 text-xs text-amber-200">{{ conflict.resolution }}</p>
          </article>
        </div>
      </section>

      <section
        aria-labelledby="coverage-heading"
        class="rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] p-5"
      >
        <h2 id="coverage-heading" class="text-2xl font-[var(--ks-font-display)]">
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
                class="border-t border-[var(--ks-border)]"
              >
                <td class="p-2 font-medium">{{ row.family }}</td>
                <td class="p-2">{{ row.status }}</td>
                <td class="p-2">{{ row.discovered_entities }}</td>
                <td class="p-2">{{ row.canonical_entities }}</td>
                <td class="max-w-xl p-2 text-xs text-[var(--ks-text-secondary)]">
                  {{ row.reason }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section
        aria-labelledby="sources-heading"
        class="rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] p-5"
      >
        <h2 id="sources-heading" class="text-2xl font-[var(--ks-font-display)]">
          {{ t('progression.sources') }}
        </h2>
        <div class="mt-4 grid gap-3 lg:grid-cols-2">
          <article
            v-for="source in sources"
            :key="source.id"
            class="rounded border border-[var(--ks-border)] p-4"
          >
            <div class="flex justify-between gap-3">
              <a
                :href="source.uri"
                target="_blank"
                rel="noreferrer"
                class="font-semibold text-[var(--ks-teal-bright)] underline"
                >{{ source.label }}</a
              ><span class="text-xs">{{
                t('progression.authorityTier', { tier: source.authority_tier })
              }}</span>
            </div>
            <p class="mt-2 text-xs text-[var(--ks-muted)]">
              {{ source.official ? t('progression.official') : t('progression.community') }} ·
              {{ t('progression.retrieved') }} {{ source.retrieved_at }}
            </p>
            <p class="mt-2 text-xs leading-5 text-[var(--ks-text-secondary)]">
              {{ source.license_note }}
            </p>
          </article>
        </div>
        <p class="mt-4 text-[.7rem] break-all text-[var(--ks-muted)]">
          SHA-256 {{ dataset.checksum }}
        </p>
      </section>
    </main>
  </AppLayout>
</template>
