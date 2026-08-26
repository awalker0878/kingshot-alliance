<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import GovernorProgressionScreenshotIntake from '@/components/progression/GovernorProgressionScreenshotIntake.vue';
import type {
  GovernorProgressionEvidenceWorkspace,
  GovernorProgressionHero,
  GovernorProgressionState,
} from '@/components/progression/governorProgressionTypes';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type HeroObservation = {
  hero_id: string;
  level: number | null;
  star: number | null;
  widget_level: number | null;
  complete_roster_capture?: boolean;
};

type Observation = {
  id: string;
  capturedAt: string;
  source: string;
  power: string;
  progressionLevel: string | null;
  datasetId: string | null;
  datasetChecksum: string | null;
  heroObservations: HeroObservation[];
};

type Loadout = {
  id: string;
  name: string;
  infantryPercent: number;
  cavalryPercent: number;
  archerPercent: number;
  heroes: string[];
  notes: string | null;
  isDefault: boolean;
  datasetId: string | null;
  datasetChecksum: string | null;
};

type ObservationDraft = {
  hero_id: string;
  level: string;
  star: string;
  widget_level: string;
};

const props = defineProps<{
  user: { name: string; email: string };
  governor: {
    id: string;
    name: string;
    gamePlayerId: string | null;
    kingdomNumber: number | null;
    allianceId: string | null;
    rosterEntryId: string | null;
  };
  dataset: {
    id: string;
    version: string;
    checksum: string;
    heroes: GovernorProgressionHero[];
  };
  observationAccess: { canView: boolean; canManage: boolean };
  observations: Observation[];
  progressionObservationState: GovernorProgressionState;
  evidenceWorkspace: GovernorProgressionEvidenceWorkspace;
  loadouts: Loadout[];
}>();

const { t, formatDate } = useLocale();
const heroById = computed(() => new Map(props.dataset.heroes.map((hero) => [hero.id, hero])));
const observationRows = ref<ObservationDraft[]>([]);
const completeRosterCapture = ref(false);
const editingLoadoutId = ref<string | null>(null);

function localDateTimeValue(date = new Date()): string {
  const local = new Date(date.getTime() - date.getTimezoneOffset() * 60_000);
  return local.toISOString().slice(0, 16);
}

const observationForm = useForm({
  observed_name: props.governor.name,
  power: '',
  progression_level: props.observations[0]?.progressionLevel ?? '',
  observed_alliance_tag: '',
  captured_at: localDateTimeValue(),
  progression_dataset_id: props.dataset.id,
  progression_dataset_checksum: props.dataset.checksum,
  hero_observations: [] as HeroObservation[],
});

const loadoutForm = useForm({
  name: '',
  infantry_percent: 50,
  cavalry_percent: 20,
  archer_percent: 30,
  heroes: [] as string[],
  notes: '',
  is_default: false,
  progression_dataset_id: props.dataset.id,
  progression_dataset_checksum: props.dataset.checksum,
});

const loadoutTotal = computed(
  () =>
    loadoutForm.infantry_percent +
    loadoutForm.cavalry_percent +
    loadoutForm.archer_percent,
);

function addObservationHero(): void {
  if (observationRows.value.length >= 34) return;
  observationRows.value.push({ hero_id: '', level: '', star: '', widget_level: '' });
}

function removeObservationHero(index: number): void {
  observationRows.value.splice(index, 1);
}

function numberOrNull(value: string): number | null {
  if (value.trim() === '') return null;
  const parsed = Number.parseInt(value, 10);
  return Number.isFinite(parsed) ? parsed : null;
}

function recordObservation(): void {
  if (!props.governor.rosterEntryId) return;

  observationForm.hero_observations = observationRows.value
    .filter((row) => row.hero_id !== '')
    .map((row) => ({
      hero_id: row.hero_id,
      level: numberOrNull(row.level),
      star: numberOrNull(row.star),
      widget_level: numberOrNull(row.widget_level),
      complete_roster_capture: completeRosterCapture.value,
    }));

  observationForm
    .transform((data) => ({ ...data, captured_at: new Date(data.captured_at).toISOString() }))
    .post(`/alliance/roster/${props.governor.rosterEntryId}/snapshots`, {
      preserveScroll: true,
      onSuccess: () => {
        observationRows.value = [];
        completeRosterCapture.value = false;
        observationForm.power = '';
        observationForm.captured_at = localDateTimeValue();
      },
    });
}

function toggleLoadoutHero(heroId: string): void {
  const index = loadoutForm.heroes.indexOf(heroId);
  if (index >= 0) {
    loadoutForm.heroes.splice(index, 1);
    return;
  }
  if (loadoutForm.heroes.length < 5) loadoutForm.heroes.push(heroId);
}

function saveLoadout(): void {
  if (loadoutTotal.value !== 100) return;

  const options = {
    preserveScroll: true,
    onSuccess: () => resetLoadout(),
  };

  if (editingLoadoutId.value) {
    loadoutForm.patch(`/player/formations/${editingLoadoutId.value}`, options);
    return;
  }

  loadoutForm.post('/player/formations', options);
}

function editLoadout(loadout: Loadout): void {
  editingLoadoutId.value = loadout.id;
  loadoutForm.name = loadout.name;
  loadoutForm.infantry_percent = loadout.infantryPercent;
  loadoutForm.cavalry_percent = loadout.cavalryPercent;
  loadoutForm.archer_percent = loadout.archerPercent;
  loadoutForm.heroes = [...loadout.heroes];
  loadoutForm.notes = loadout.notes ?? '';
  loadoutForm.is_default = loadout.isDefault;
  loadoutForm.progression_dataset_id = props.dataset.id;
  loadoutForm.progression_dataset_checksum = props.dataset.checksum;
}

function resetLoadout(): void {
  editingLoadoutId.value = null;
  loadoutForm.reset();
  loadoutForm.infantry_percent = 50;
  loadoutForm.cavalry_percent = 20;
  loadoutForm.archer_percent = 30;
  loadoutForm.heroes = [];
  loadoutForm.progression_dataset_id = props.dataset.id;
  loadoutForm.progression_dataset_checksum = props.dataset.checksum;
}

function deleteLoadout(loadout: Loadout): void {
  router.delete(`/player/formations/${loadout.id}`, { preserveScroll: true });
}

function heroName(heroId: string): string {
  return heroById.value.get(heroId)?.name ?? heroId;
}

function formatCaptured(value: string): string {
  return formatDate(value, { dateStyle: 'medium', timeStyle: 'short' });
}
</script>

<template>
  <Head :title="t('progression.governorProgression')" />
  <AppLayout :user="user">
    <div class="mx-auto w-full max-w-[100rem] space-y-6">
      <section class="ks-surface-gold p-5 sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <p class="ks-kicker">{{ t('progression.governorProgression') }}</p>
            <h1 class="ks-display mt-1 text-3xl font-semibold">{{ governor.name }}</h1>
            <p class="mt-2 text-sm text-[var(--ks-text-secondary)]">
              <span v-if="governor.gamePlayerId">{{ governor.gamePlayerId }}</span>
              <span v-if="governor.kingdomNumber"> · K{{ governor.kingdomNumber }}</span>
            </p>
          </div>
          <div class="flex flex-wrap gap-2">
            <Link href="/progression" class="ks-command-link" data-variant="secondary">
              {{ t('progression.backToLibrary') }}
            </Link>
            <span class="ks-chip">{{ dataset.version }}</span>
          </div>
        </div>
        <p class="mt-4 max-w-4xl text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('progression.separationHelp') }}
        </p>
      </section>

      <GovernorProgressionScreenshotIntake
        v-if="observationAccess.canView && governor.rosterEntryId"
        :roster-entry-id="governor.rosterEntryId"
        :heroes="dataset.heroes"
        :schemas="evidenceWorkspace.schemas"
        :evidence="evidenceWorkspace.evidence"
        :progression-state="progressionObservationState"
        :can-manage="observationAccess.canManage"
      />

      <div class="grid gap-6 2xl:grid-cols-2">
        <section class="ks-surface overflow-hidden" aria-labelledby="observations-heading">
          <div class="border-b border-[var(--ks-border)] p-5 sm:p-6">
            <p class="ks-kicker">{{ t('progression.observedFacts') }}</p>
            <h2 id="observations-heading" class="ks-display mt-1 text-2xl font-semibold">
              {{ t('progression.governorObservations') }}
            </h2>
            <p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
              {{ t('progression.observationHelp') }}
            </p>
          </div>

          <div v-if="!observationAccess.canView" class="p-6 text-sm text-[var(--ks-muted)]">
            {{ t('progression.observationUnavailable') }}
          </div>

          <form
            v-else-if="observationAccess.canManage && governor.rosterEntryId"
            class="border-b border-[var(--ks-border)] p-5 sm:p-6"
            @submit.prevent="recordObservation"
          >
            <div class="grid gap-4 sm:grid-cols-2">
              <label class="text-xs text-[var(--ks-muted)]">
                <span>{{ t('progression.observedPower') }}</span>
                <input
                  v-model="observationForm.power"
                  required
                  inputmode="numeric"
                  pattern="[0-9]+"
                  maxlength="19"
                  class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-3 text-sm"
                />
              </label>
              <label class="text-xs text-[var(--ks-muted)]">
                <span>{{ t('progression.capturedAt') }}</span>
                <input
                  v-model="observationForm.captured_at"
                  required
                  type="datetime-local"
                  class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-3 text-sm"
                />
              </label>
              <label class="text-xs text-[var(--ks-muted)]">
                <span>{{ t('progression.progressionLevel') }}</span>
                <input
                  v-model="observationForm.progression_level"
                  maxlength="64"
                  class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-3 text-sm"
                />
              </label>
              <label
                class="flex min-h-11 items-center gap-2 self-end rounded border border-[var(--ks-border)] px-3 text-sm"
              >
                <input v-model="completeRosterCapture" type="checkbox" />
                <span>{{ t('progression.completeRosterCapture') }}</span>
              </label>
            </div>

            <div class="mt-5 flex items-center justify-between gap-3">
              <div>
                <h3 class="font-semibold">{{ t('progression.heroObservations') }}</h3>
                <p class="mt-1 text-xs text-[var(--ks-muted)]">
                  {{ t('progression.heroObservationHelp') }}
                </p>
              </div>
              <button
                type="button"
                class="min-h-11 rounded border border-[var(--ks-border)] px-3 text-sm"
                @click="addObservationHero"
              >
                + {{ t('progression.addHero') }}
              </button>
            </div>

            <div class="mt-3 space-y-3">
              <fieldset
                v-for="(row, index) in observationRows"
                :key="index"
                class="rounded border border-[var(--ks-border)] p-3"
              >
                <legend class="px-1 text-xs text-[var(--ks-muted)]">
                  {{ t('progression.heroObservationNumber', { number: index + 1 }) }}
                </legend>
                <div class="grid gap-2 sm:grid-cols-4">
                  <label class="text-xs text-[var(--ks-muted)] sm:col-span-1">
                    <span>{{ t('progression.hero') }}</span>
                    <select
                      v-model="row.hero_id"
                      required
                      class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-[#091313] px-2 text-sm"
                    >
                      <option value="">{{ t('progression.selectHero') }}</option>
                      <option v-for="hero in dataset.heroes" :key="hero.id" :value="hero.id">
                        {{ hero.name }} · G{{ hero.generation }}
                      </option>
                    </select>
                  </label>
                  <label class="text-xs text-[var(--ks-muted)]">
                    <span>{{ t('progression.level') }}</span>
                    <input
                      v-model="row.level"
                      type="number"
                      min="0"
                      max="80"
                      class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm"
                    />
                  </label>
                  <label class="text-xs text-[var(--ks-muted)]">
                    <span>{{ t('progression.star') }}</span>
                    <input
                      v-model="row.star"
                      type="number"
                      min="0"
                      max="5"
                      class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm"
                    />
                  </label>
                  <div class="grid grid-cols-[1fr_auto] gap-2">
                    <label class="text-xs text-[var(--ks-muted)]">
                      <span>{{ t('progression.widgetLevel') }}</span>
                      <input
                        v-model="row.widget_level"
                        type="number"
                        min="0"
                        max="10"
                        class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm"
                      />
                    </label>
                    <button
                      type="button"
                      class="mt-5 min-h-11 rounded border border-red-400/25 px-3 text-sm text-red-200"
                      :aria-label="t('progression.removeHero')"
                      @click="removeObservationHero(index)"
                    >
                      ×
                    </button>
                  </div>
                </div>
              </fieldset>
            </div>

            <p
              v-if="Object.keys(observationForm.errors).length"
              class="mt-4 text-sm text-red-300"
              role="alert"
            >
              {{ Object.values(observationForm.errors)[0] }}
            </p>
            <button
              type="submit"
              class="mt-5 min-h-11 rounded bg-[var(--ks-teal)] px-4 text-sm font-semibold disabled:opacity-50"
              :disabled="observationForm.processing"
            >
              {{ t('progression.recordObservation') }}
            </button>
          </form>

          <div v-if="observationAccess.canView" class="divide-y divide-[var(--ks-border)]">
            <article v-for="observation in observations" :key="observation.id" class="p-5 sm:p-6">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p class="font-semibold">{{ formatCaptured(observation.capturedAt) }}</p>
                  <p class="mt-1 text-xs text-[var(--ks-muted)]">
                    {{ observation.source }} ·
                    {{ observation.datasetId ?? t('progression.unpinnedLegacyObservation') }}
                  </p>
                </div>
                <div class="text-end">
                  <p class="ks-display text-xl">{{ observation.power }}</p>
                  <p class="text-xs text-[var(--ks-muted)]">
                    {{ observation.progressionLevel ?? '—' }}
                  </p>
                </div>
              </div>
              <div v-if="observation.heroObservations.length" class="mt-4 flex flex-wrap gap-2">
                <span
                  v-for="hero in observation.heroObservations"
                  :key="hero.hero_id"
                  class="ks-chip"
                >
                  {{ heroName(hero.hero_id) }}
                  <template v-if="hero.level !== null">
                    · {{ t('progression.levelShort', { value: hero.level }) }}
                  </template>
                  <template v-if="hero.star !== null"> · ★{{ hero.star }}</template>
                  <template v-if="hero.widget_level !== null"> · W{{ hero.widget_level }}</template>
                </span>
              </div>
              <p v-else class="mt-3 text-xs text-[var(--ks-muted)]">
                {{ t('progression.noHeroObservations') }}
              </p>
            </article>
            <p v-if="observations.length === 0" class="p-6 text-sm text-[var(--ks-muted)]">
              {{ t('progression.noObservations') }}
            </p>
          </div>
        </section>

        <section class="ks-surface overflow-hidden" aria-labelledby="loadouts-heading">
          <div class="border-b border-[var(--ks-border)] p-5 sm:p-6">
            <p class="ks-kicker">{{ t('progression.planningIntent') }}</p>
            <h2 id="loadouts-heading" class="ks-display mt-1 text-2xl font-semibold">
              {{ t('progression.savedLoadouts') }}
            </h2>
            <p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
              {{ t('progression.loadoutHelp') }}
            </p>
          </div>

          <form class="border-b border-[var(--ks-border)] p-5 sm:p-6" @submit.prevent="saveLoadout">
            <label class="text-xs text-[var(--ks-muted)]">
              <span>{{ t('progression.loadoutName') }}</span>
              <input
                v-model="loadoutForm.name"
                required
                maxlength="120"
                class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-3 text-sm"
              />
            </label>

            <div class="mt-4 grid grid-cols-3 gap-2">
              <label class="text-xs text-[var(--ks-muted)]">
                <span>{{ t('progression.infantry') }} %</span>
                <input
                  v-model.number="loadoutForm.infantry_percent"
                  type="number"
                  min="0"
                  max="100"
                  class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm"
                />
              </label>
              <label class="text-xs text-[var(--ks-muted)]">
                <span>{{ t('progression.cavalry') }} %</span>
                <input
                  v-model.number="loadoutForm.cavalry_percent"
                  type="number"
                  min="0"
                  max="100"
                  class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm"
                />
              </label>
              <label class="text-xs text-[var(--ks-muted)]">
                <span>{{ t('progression.archer') }} %</span>
                <input
                  v-model.number="loadoutForm.archer_percent"
                  type="number"
                  min="0"
                  max="100"
                  class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-black/20 px-2 text-sm"
                />
              </label>
            </div>

            <p
              class="mt-2 text-xs"
              :class="loadoutTotal === 100 ? 'text-green-300' : 'text-red-300'"
            >
              {{ t('progression.formationTotal', { total: loadoutTotal }) }}
            </p>

            <fieldset class="mt-4">
              <legend class="text-xs font-semibold text-[var(--ks-muted)]">
                {{ t('progression.heroesUpToFive') }}
              </legend>
              <div
                class="mt-2 grid max-h-64 gap-1 overflow-y-auto rounded border border-[var(--ks-border)] p-2 sm:grid-cols-2"
              >
                <label
                  v-for="hero in dataset.heroes"
                  :key="hero.id"
                  class="flex min-h-10 items-center gap-2 rounded px-2 text-sm hover:bg-white/[.025]"
                >
                  <input
                    type="checkbox"
                    :checked="loadoutForm.heroes.includes(hero.id)"
                    :disabled="
                      !loadoutForm.heroes.includes(hero.id) && loadoutForm.heroes.length >= 5
                    "
                    @change="toggleLoadoutHero(hero.id)"
                  />
                  <span>
                    {{ hero.name }}
                    <small class="text-[var(--ks-muted)]">G{{ hero.generation }}</small>
                  </span>
                </label>
              </div>
            </fieldset>

            <label class="mt-4 block text-xs text-[var(--ks-muted)]">
              <span>{{ t('progression.notes') }}</span>
              <textarea
                v-model="loadoutForm.notes"
                rows="3"
                maxlength="10000"
                class="mt-1 w-full rounded border border-[var(--ks-border)] bg-black/20 p-3 text-sm"
              />
            </label>
            <label class="mt-3 flex min-h-11 items-center gap-2 text-sm">
              <input v-model="loadoutForm.is_default" type="checkbox" />
              <span>{{ t('progression.defaultLoadout') }}</span>
            </label>
            <p class="mt-2 break-all text-[0.68rem] text-[var(--ks-muted)]">
              {{ t('progression.pinnedTo') }} {{ dataset.version }} · {{ dataset.checksum }}
            </p>
            <p
              v-if="Object.keys(loadoutForm.errors).length"
              class="mt-3 text-sm text-red-300"
              role="alert"
            >
              {{ Object.values(loadoutForm.errors)[0] }}
            </p>
            <div class="mt-4 flex gap-2">
              <button
                type="submit"
                class="min-h-11 rounded bg-[var(--ks-teal)] px-4 text-sm font-semibold disabled:opacity-50"
                :disabled="loadoutTotal !== 100 || loadoutForm.processing"
              >
                {{
                  editingLoadoutId ? t('progression.updateLoadout') : t('progression.saveLoadout')
                }}
              </button>
              <button
                v-if="editingLoadoutId"
                type="button"
                class="min-h-11 rounded border border-[var(--ks-border)] px-4 text-sm"
                @click="resetLoadout"
              >
                {{ t('common.cancel') }}
              </button>
            </div>
          </form>

          <div class="divide-y divide-[var(--ks-border)]">
            <article v-for="loadout in loadouts" :key="loadout.id" class="p-5 sm:p-6">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <h3 class="font-semibold">{{ loadout.name }}</h3>
                  <p class="mt-1 text-sm">
                    {{ loadout.infantryPercent }} / {{ loadout.cavalryPercent }} /
                    {{ loadout.archerPercent }}
                  </p>
                  <p class="mt-1 text-xs text-[var(--ks-muted)]">
                    {{ t('progression.userPlanningIntent') }} ·
                    {{ loadout.datasetId ?? t('progression.unpinnedLegacyLoadout') }}
                  </p>
                </div>
                <span v-if="loadout.isDefault" class="ks-chip">
                  {{ t('progression.defaultLoadout') }}
                </span>
              </div>
              <div v-if="loadout.heroes.length" class="mt-3 flex flex-wrap gap-2">
                <span v-for="heroId in loadout.heroes" :key="heroId" class="ks-chip">
                  {{ heroName(heroId) }}
                </span>
              </div>
              <p
                v-if="loadout.notes"
                class="mt-3 text-xs leading-5 text-[var(--ks-text-secondary)]"
              >
                {{ loadout.notes }}
              </p>
              <div class="mt-4 flex gap-2">
                <button
                  type="button"
                  class="min-h-10 rounded border border-[var(--ks-border)] px-3 text-xs"
                  @click="editLoadout(loadout)"
                >
                  {{ t('progression.editLoadout') }}
                </button>
                <button
                  type="button"
                  class="min-h-10 rounded border border-red-400/25 px-3 text-xs text-red-200"
                  @click="deleteLoadout(loadout)"
                >
                  {{ t('progression.deleteLoadout') }}
                </button>
              </div>
            </article>
            <p v-if="loadouts.length === 0" class="p-6 text-sm text-[var(--ks-muted)]">
              {{ t('progression.noLoadouts') }}
            </p>
          </div>
        </section>
      </div>
    </div>
  </AppLayout>
</template>
