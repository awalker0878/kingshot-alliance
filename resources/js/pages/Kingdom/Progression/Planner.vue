<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type Eligibility = {
  family: string;
  status: string;
  reason: string;
  datasetId: string;
  datasetVersion: string;
  datasetChecksum: string;
  qualificationReportChecksum: string;
  qualificationStatus: string;
  calculationVersion: string | null;
  sourceIds: string[];
  units: Record<string, string>;
  gates: Record<string, boolean>;
  blockers: string[];
};

type Family = {
  id: string;
  label: string;
  calculatorFamily: string | null;
  calculator: Eligibility | null;
};

type Subject = { id: string; label: string; context: Record<string, unknown> };

type State = {
  id: string;
  label: string;
  ordinal: number;
  sourceIds: string[];
  evidenceStatus: string;
  prerequisites: string[];
  attributes: Record<string, unknown>;
};

type CurrentState = {
  status: string;
  stateId: string | null;
  state: State | null;
  facts: Record<string, Record<string, unknown>>;
  capturedAt?: string | null;
  observationDatasetId?: string | null;
  observationDatasetChecksum?: string | null;
  reason: string | null;
};

type Comparison = {
  status: string;
  current: State | null;
  target: State | null;
  path: State[];
  remainingTransitions: number | null;
  reason: string | null;
};

type Calculation = {
  status: string;
  family: string;
  currentStateId: string;
  targetStateId: string;
  transitionIds: string[];
  resources: Record<string, { label: string; quantity: number; unit: string }>;
  datasetId: string;
  datasetVersion: string;
  datasetChecksum: string;
  calculationVersion: string | null;
  sourceIds: string[];
  assumptions: string[];
  reason: string | null;
};

type Source = {
  id: string;
  label: string;
  uri: string;
  authority_tier: string;
  observed_at: string;
};

const props = defineProps<{
  user: { name: string; email: string };
  governor: { id: string; name: string; allianceId: string | null; rosterEntryId: string | null };
  observationAccess: { canView: boolean };
  planner: {
    dataset: { id: string; version: string; checksum: string; observedAt: string; reviewStatus: string };
    families: Family[];
    selectedFamily: Family | null;
    subjects: Subject[];
    selectedSubject: Subject | null;
    states: State[];
    current: CurrentState;
    target: State | null;
    comparison: Comparison | null;
    prerequisites: Array<{ label: string; status: string }>;
    calculator: Eligibility | null;
    calculation: Calculation | null;
    sources: Source[];
  };
}>();

const { t, formatDate, formatNumber } = useLocale();
const family = ref(props.planner.selectedFamily?.id ?? '');
const subject = ref(props.planner.selectedSubject?.id ?? '');
const target = ref(props.planner.target?.id ?? '');
const sourceById = computed(() => new Map(props.planner.sources.map((source) => [source.id, source])));
const canCalculate = computed(
  () => props.planner.calculator?.status === 'calculator_ready' && props.planner.comparison?.status === 'comparable',
);

function valueOrUndefined(value: string): string | undefined {
  return value === '' ? undefined : value;
}

function navigate(options: {
  family?: string;
  subject?: string;
  target?: string;
  calculate?: boolean;
} = {}): void {
  const nextFamily = options.family !== undefined ? options.family : family.value;
  const nextSubject = options.subject !== undefined ? options.subject : subject.value;
  const nextTarget = options.target !== undefined ? options.target : target.value;

  router.get(
    '/progression/governor/planner',
    {
      dataset_id: props.planner.dataset.id,
      dataset_checksum: props.planner.dataset.checksum,
      family: valueOrUndefined(nextFamily),
      subject: valueOrUndefined(nextSubject),
      target: valueOrUndefined(nextTarget),
      calculate: options.calculate === true ? '1' : undefined,
    },
    { preserveScroll: true, replace: true },
  );
}

function changeFamily(): void {
  subject.value = '';
  target.value = '';
  navigate({ family: family.value, subject: '', target: '' });
}

function changeSubject(): void {
  target.value = '';
  navigate({ subject: subject.value, target: '' });
}

function changeTarget(): void {
  navigate({ target: target.value });
}

function calculate(): void {
  navigate({ calculate: true });
}

function sourceLabel(id: string): string {
  return sourceById.value.get(id)?.label ?? id;
}

function shortChecksum(value: string): string {
  return value.length > 18 ? `${value.slice(0, 10)}…${value.slice(-6)}` : value;
}
</script>

<template>
  <Head :title="t('progression.goalPlanner')" />
  <AppLayout :user="user">
    <main id="main-content" class="mx-auto w-full max-w-[100rem] space-y-6 px-4 py-6 sm:px-6 lg:px-8">
      <section class="ks-surface-gold p-5 sm:p-6" data-testid="progression-goal-planner-hero">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <p class="ks-kicker">{{ t('progression.planningIntent') }}</p>
            <h1 class="ks-display mt-1 text-3xl font-semibold">{{ t('progression.goalPlanner') }}</h1>
            <p class="mt-3 max-w-4xl text-sm leading-6 text-[var(--ks-text-secondary)]">
              {{ t('progression.goalPlannerHelp') }}
            </p>
          </div>
          <nav class="flex flex-wrap gap-2" :aria-label="t('progression.goalPlanner')">
            <Link href="/progression/governor" class="ks-command-link" data-variant="secondary">
              {{ t('progression.governorProgression') }}
            </Link>
            <Link href="/progression" class="ks-command-link" data-variant="secondary">
              {{ t('progression.backToLibrary') }}
            </Link>
          </nav>
        </div>
        <dl class="mt-5 grid gap-3 text-xs sm:grid-cols-2 lg:grid-cols-4">
          <div class="rounded border border-[var(--ks-border)] p-3">
            <dt class="text-[var(--ks-muted)]">{{ t('progression.datasetVersion') }}</dt>
            <dd class="mt-1 font-semibold">{{ planner.dataset.version }}</dd>
          </div>
          <div class="rounded border border-[var(--ks-border)] p-3">
            <dt class="text-[var(--ks-muted)]">{{ t('progression.observedAt') }}</dt>
            <dd class="mt-1 font-semibold">{{ planner.dataset.observedAt }}</dd>
          </div>
          <div class="rounded border border-[var(--ks-border)] p-3 sm:col-span-2">
            <dt class="text-[var(--ks-muted)]">{{ t('progression.datasetChecksum') }}</dt>
            <dd class="mt-1 break-all font-mono">{{ planner.dataset.checksum }}</dd>
          </div>
        </dl>
      </section>

      <section class="ks-surface p-5 sm:p-6" aria-labelledby="planner-target-heading">
        <p class="ks-kicker">{{ t('progression.selectGoal') }}</p>
        <h2 id="planner-target-heading" class="ks-display mt-1 text-2xl font-semibold">
          {{ t('progression.currentToTarget') }}
        </h2>
        <div class="mt-5 grid gap-4 lg:grid-cols-3">
          <label class="text-xs text-[var(--ks-muted)]">
            <span>{{ t('progression.goalFamily') }}</span>
            <select
              v-model="family"
              data-testid="planner-family"
              class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-[#091313] px-3"
              @change="changeFamily"
            >
              <option value="">{{ t('progression.selectGoalFamily') }}</option>
              <option v-for="item in planner.families" :key="item.id" :value="item.id">{{ item.label }}</option>
            </select>
          </label>
          <label class="text-xs text-[var(--ks-muted)]">
            <span>{{ t('progression.goalSubject') }}</span>
            <select
              v-model="subject"
              data-testid="planner-subject"
              :disabled="family === ''"
              class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-[#091313] px-3 disabled:opacity-50"
              @change="changeSubject"
            >
              <option value="">{{ t('progression.selectGoalSubject') }}</option>
              <option v-for="item in planner.subjects" :key="item.id" :value="item.id">{{ item.label }}</option>
            </select>
          </label>
          <label class="text-xs text-[var(--ks-muted)]">
            <span>{{ t('progression.targetState') }}</span>
            <select
              v-model="target"
              data-testid="planner-target"
              :disabled="subject === '' || planner.states.length === 0"
              class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-[#091313] px-3 disabled:opacity-50"
              @change="changeTarget"
            >
              <option value="">{{ t('progression.selectTargetState') }}</option>
              <option v-for="state in planner.states" :key="state.id" :value="state.id">{{ state.label }}</option>
            </select>
          </label>
        </div>
        <p
          v-if="subject !== '' && planner.states.length === 0"
          class="mt-4 rounded border border-amber-400/30 bg-amber-300/5 p-3 text-sm text-amber-100"
          role="status"
        >
          {{ t('progression.noDeterministicStates') }}
        </p>
      </section>

      <section v-if="planner.selectedSubject" class="grid gap-6 xl:grid-cols-2">
        <article class="ks-surface p-5 sm:p-6" data-testid="planner-current-state">
          <p class="ks-kicker">{{ t('progression.currentObservedState') }}</p>
          <h2 class="ks-display mt-1 text-2xl font-semibold">{{ planner.selectedSubject.label }}</h2>
          <template v-if="planner.current.status === 'observed' && planner.current.state">
            <p class="mt-5 text-3xl font-semibold text-[var(--ks-gold-bright)]">{{ planner.current.state.label }}</p>
            <p v-if="planner.current.capturedAt" class="mt-2 text-sm text-[var(--ks-text-secondary)]">
              {{
                t('progression.factCaptured', {
                  date: formatDate(planner.current.capturedAt, { dateStyle: 'medium', timeStyle: 'short' }),
                })
              }}
            </p>
            <dl class="mt-4 space-y-2 text-xs text-[var(--ks-muted)]">
              <div v-if="planner.current.observationDatasetId" class="flex flex-wrap justify-between gap-2">
                <dt>{{ t('progression.observationDataset') }}</dt>
                <dd>{{ planner.current.observationDatasetId }}</dd>
              </div>
              <div v-if="planner.current.observationDatasetChecksum" class="flex flex-wrap justify-between gap-2">
                <dt>{{ t('progression.datasetChecksum') }}</dt>
                <dd class="font-mono">{{ shortChecksum(planner.current.observationDatasetChecksum) }}</dd>
              </div>
            </dl>
          </template>
          <div v-else class="mt-5 rounded border border-dashed border-[var(--ks-border)] p-4 text-sm text-[var(--ks-muted)]">
            <strong class="block text-[var(--ks-text)]">{{ t('progression.currentStateUnknown') }}</strong>
            <span>{{ planner.current.reason }}</span>
            <p v-if="!observationAccess.canView" class="mt-2">{{ t('progression.observationUnavailable') }}</p>
          </div>
        </article>

        <article class="ks-surface p-5 sm:p-6" data-testid="planner-target-state">
          <p class="ks-kicker">{{ t('progression.factualTarget') }}</p>
          <template v-if="planner.target">
            <h2 class="ks-display mt-1 text-2xl font-semibold">{{ planner.target.label }}</h2>
            <p class="mt-2 text-sm text-[var(--ks-text-secondary)]">
              {{ t('progression.targetPinnedHelp', { version: planner.dataset.version }) }}
            </p>
            <template v-if="planner.comparison?.status === 'comparable'">
              <p class="mt-5 text-lg font-semibold">
                {{ t('progression.stepsRemaining', { count: planner.comparison.remainingTransitions ?? 0 }) }}
              </p>
              <ol class="mt-4 flex flex-wrap items-center gap-2" :aria-label="t('progression.progressionPath')">
                <li v-for="(state, index) in planner.comparison.path" :key="state.id" class="flex items-center gap-2">
                  <span class="rounded border border-[var(--ks-border)] px-3 py-2 text-sm">{{ state.label }}</span>
                  <span v-if="index < planner.comparison.path.length - 1" aria-hidden="true" class="text-[var(--ks-muted)]">→</span>
                </li>
              </ol>
            </template>
            <p
              v-else-if="planner.comparison"
              class="mt-5 rounded border border-amber-400/30 bg-amber-300/5 p-3 text-sm text-amber-100"
              role="status"
            >
              {{ planner.comparison.reason }}
            </p>
          </template>
          <p v-else class="mt-5 text-sm text-[var(--ks-muted)]">{{ t('progression.chooseTargetHelp') }}</p>
        </article>
      </section>

      <section
        v-if="planner.target && planner.prerequisites.length > 0"
        class="ks-surface p-5 sm:p-6"
        aria-labelledby="prereq-heading"
      >
        <p class="ks-kicker">{{ t('progression.prerequisites') }}</p>
        <h2 id="prereq-heading" class="ks-display mt-1 text-2xl font-semibold">
          {{ t('progression.sourcedPrerequisites') }}
        </h2>
        <ul class="mt-4 space-y-2">
          <li v-for="item in planner.prerequisites" :key="item.label" class="rounded border border-[var(--ks-border)] p-3 text-sm">
            <span class="font-semibold">{{ item.label }}</span>
            <span class="ml-2 text-[var(--ks-muted)]">— {{ t('progression.prerequisiteUnknown') }}</span>
          </li>
        </ul>
      </section>

      <section v-if="planner.selectedFamily" class="ks-surface p-5 sm:p-6" aria-labelledby="calculator-heading" data-testid="planner-calculator-gate">
        <p class="ks-kicker">{{ t('progression.calculatorEvidence') }}</p>
        <h2 id="calculator-heading" class="ks-display mt-1 text-2xl font-semibold">{{ t('progression.calculatorStatus') }}</h2>
        <div v-if="planner.calculator" class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start">
          <div>
            <p class="text-lg font-semibold">{{ t(`progression.calculatorState.${planner.calculator.status}`) }}</p>
            <p class="mt-2 max-w-4xl text-sm leading-6 text-[var(--ks-text-secondary)]">{{ planner.calculator.reason }}</p>
            <ul v-if="planner.calculator.blockers.length > 0" class="mt-3 space-y-1 text-xs text-[var(--ks-muted)]">
              <li v-for="blocker in planner.calculator.blockers" :key="blocker">{{ blocker }}</li>
            </ul>
            <div v-if="planner.calculator.sourceIds.length > 0" class="mt-4 flex flex-wrap gap-2">
              <span v-for="sourceId in planner.calculator.sourceIds" :key="sourceId" class="ks-chip">{{ sourceLabel(sourceId) }}</span>
            </div>
          </div>
          <button v-if="canCalculate" type="button" class="ks-command-link" data-testid="planner-calculate" @click="calculate">
            {{ t('progression.calculateResources') }}
          </button>
        </div>
        <p v-else class="mt-4 rounded border border-dashed border-[var(--ks-border)] p-4 text-sm text-[var(--ks-muted)]">
          {{ t('progression.noCalculatorProgram') }}
        </p>
      </section>

      <section
        v-if="planner.calculation"
        class="ks-surface-gold p-5 sm:p-6"
        aria-labelledby="calculation-heading"
        aria-live="polite"
        data-testid="planner-calculation-result"
      >
        <p class="ks-kicker">{{ t('progression.calculationResult') }}</p>
        <h2 id="calculation-heading" class="ks-display mt-1 text-2xl font-semibold">
          {{ planner.calculation.status === 'calculated' ? t('progression.resourceRequirements') : t('progression.calculationUnavailable') }}
        </h2>
        <template v-if="planner.calculation.status === 'calculated'">
          <dl class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="resource in planner.calculation.resources" :key="resource.label" class="rounded border border-[var(--ks-border)] bg-black/15 p-4">
              <dt class="text-xs text-[var(--ks-muted)]">{{ resource.label }}</dt>
              <dd class="mt-1 text-2xl font-semibold">{{ formatNumber(resource.quantity) }}</dd>
            </div>
          </dl>
          <div class="mt-5 grid gap-3 text-xs text-[var(--ks-muted)] lg:grid-cols-3">
            <p>{{ t('progression.datasetVersion') }}: <strong class="text-[var(--ks-text)]">{{ planner.calculation.datasetVersion }}</strong></p>
            <p>{{ t('progression.calculationVersion') }}: <strong class="text-[var(--ks-text)]">{{ planner.calculation.calculationVersion }}</strong></p>
            <p>{{ t('progression.transitionsIncluded') }}: <strong class="text-[var(--ks-text)]">{{ planner.calculation.transitionIds.length }}</strong></p>
          </div>
          <ul class="mt-4 space-y-1 text-xs text-[var(--ks-muted)]">
            <li v-for="assumption in planner.calculation.assumptions" :key="assumption">{{ assumption }}</li>
          </ul>
        </template>
        <p v-else class="mt-4 text-sm text-amber-100">{{ planner.calculation.reason }}</p>
      </section>
    </main>
  </AppLayout>
</template>
