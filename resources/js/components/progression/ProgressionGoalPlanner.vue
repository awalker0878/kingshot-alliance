<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';

import { useLocale } from '@/localization';
import type {
  CalculatorQualificationReport,
  ProgressionGoalPlanner,
} from './progressionGoalPlannerTypes';

const props = defineProps<{
  planner: ProgressionGoalPlanner;
  calculatorEligibility: CalculatorQualificationReport[];
  canViewObservations: boolean;
}>();

const { t, formatDate } = useLocale();
const selectedFamily = computed(() =>
  props.planner.families.find((family) => family.id === props.planner.selection.family),
);
const selectedQualification = computed(() => {
  const family = selectedFamily.value?.calculatorFamily;
  return family
    ? props.calculatorEligibility.find((report) => report.family === family) ?? null
    : null;
});

function navigate(family: string | null, subject: string | null, target: string | null): void {
  router.get(
    '/progression/governor',
    {
      planner_family: family || undefined,
      planner_subject: subject || undefined,
      planner_target: target || undefined,
    },
    { preserveScroll: true, preserveState: false, replace: true },
  );
}

function chooseFamily(event: Event): void {
  navigate((event.target as HTMLSelectElement).value || null, null, null);
}

function chooseSubject(event: Event): void {
  navigate(
    props.planner.selection.family,
    (event.target as HTMLSelectElement).value || null,
    null,
  );
}

function chooseTarget(event: Event): void {
  navigate(
    props.planner.selection.family,
    props.planner.selection.subjectId,
    (event.target as HTMLSelectElement).value || null,
  );
}

function statusLabel(status: string): string {
  const key = `progression.plannerStatus.${status}`;
  const translated = t(key);
  return translated === key ? status.replaceAll('_', ' ') : translated;
}

function capturedAt(value: string | null | undefined): string {
  return value ? formatDate(value, { dateStyle: 'medium', timeStyle: 'short' }) : '—';
}
</script>

<template>
  <section class="ks-surface overflow-hidden" aria-labelledby="goal-planner-heading" data-testid="progression-goal-planner">
    <div class="border-b border-[var(--ks-border)] p-5 sm:p-6">
      <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
        <div>
          <p class="ks-kicker">{{ t('progression.planningIntent') }}</p>
          <h2 id="goal-planner-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('progression.goalPlanner') }}
          </h2>
          <p class="mt-2 max-w-4xl text-sm leading-6 text-[var(--ks-text-secondary)]">
            {{ t('progression.goalPlannerHelp') }}
          </p>
        </div>
        <div class="rounded border border-[var(--ks-border)] px-3 py-2 text-xs text-[var(--ks-muted)]">
          <span class="font-semibold text-[var(--ks-text)]">{{ planner.dataset.version }}</span>
          <span> · {{ t('progression.observedAt') }} {{ planner.dataset.observedAt }}</span>
        </div>
      </div>
    </div>

    <div class="grid gap-5 p-5 sm:p-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
      <div class="space-y-4">
        <div class="grid gap-3 md:grid-cols-3">
          <label class="text-xs text-[var(--ks-muted)]">
            <span>{{ t('progression.goalFamily') }}</span>
            <select
              :value="planner.selection.family ?? ''"
              class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-[#091313] px-3 text-sm"
              @change="chooseFamily"
            >
              <option value="">{{ t('progression.selectGoalFamily') }}</option>
              <option v-for="family in planner.families" :key="family.id" :value="family.id">
                {{ family.label }}
              </option>
            </select>
          </label>

          <label class="text-xs text-[var(--ks-muted)]">
            <span>{{ t('progression.goalSubject') }}</span>
            <select
              :value="planner.selection.subjectId ?? ''"
              :disabled="!planner.selection.family || planner.subjects.length === 0"
              class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-[#091313] px-3 text-sm disabled:opacity-50"
              @change="chooseSubject"
            >
              <option value="">{{ t('progression.selectGoalSubject') }}</option>
              <option v-for="subject in planner.subjects" :key="subject.id" :value="subject.id">
                {{ subject.label }}<template v-if="subject.status === 'source_gap'"> · {{ t('progression.sourceGap') }}</template>
              </option>
            </select>
          </label>

          <label class="text-xs text-[var(--ks-muted)]">
            <span>{{ t('progression.goalTarget') }}</span>
            <select
              :value="planner.selection.targetStateId ?? ''"
              :disabled="!planner.selection.subjectId || planner.states.length === 0"
              class="mt-1 min-h-11 w-full rounded border border-[var(--ks-border)] bg-[#091313] px-3 text-sm disabled:opacity-50"
              @change="chooseTarget"
            >
              <option value="">{{ t('progression.selectGoalTarget') }}</option>
              <option v-for="state in planner.states" :key="state.id" :value="state.id">
                {{ state.label }}
              </option>
            </select>
          </label>
        </div>

        <div v-if="planner.selection.family && planner.subjects.length === 0" class="rounded border border-amber-400/25 bg-amber-300/5 p-4 text-sm text-amber-100">
          {{ t('progression.plannerTopologyUnavailable') }}
        </div>
        <div v-else-if="planner.selection.subjectId && planner.states.length === 0" class="rounded border border-amber-400/25 bg-amber-300/5 p-4 text-sm text-amber-100">
          {{ t('progression.plannerTargetUnavailable') }}
        </div>

        <div class="grid gap-3 md:grid-cols-2">
          <article class="rounded border border-[var(--ks-border)] bg-black/15 p-4">
            <p class="text-xs font-bold tracking-wide text-[var(--ks-muted)] uppercase">{{ t('progression.currentState') }}</p>
            <p class="mt-2 text-lg font-semibold">
              {{ planner.current?.state?.label ?? statusLabel(planner.current?.status ?? (canViewObservations ? 'not_observed' : 'permission_unavailable')) }}
            </p>
            <p v-if="planner.current?.provenance" class="mt-2 text-xs leading-5 text-[var(--ks-muted)]">
              {{ t('progression.observationLabel') }} · {{ capturedAt(planner.current.provenance.capturedAt) }}
              <br />
              {{ planner.current.provenance.observationId }}
            </p>
            <p v-else class="mt-2 text-xs leading-5 text-[var(--ks-muted)]">
              {{ canViewObservations ? t('progression.noPlannerObservation') : t('progression.observationUnavailable') }}
            </p>
          </article>

          <article class="rounded border border-[var(--ks-border)] bg-black/15 p-4">
            <p class="text-xs font-bold tracking-wide text-[var(--ks-muted)] uppercase">{{ t('progression.goalTarget') }}</p>
            <p class="mt-2 text-lg font-semibold">
              {{ planner.target?.label ?? t('progression.noTargetSelected') }}
            </p>
            <p class="mt-2 text-xs leading-5 text-[var(--ks-muted)]">
              {{ t('progression.pinnedTo') }} {{ planner.dataset.version }} · {{ planner.dataset.checksum }}
            </p>
          </article>
        </div>

        <article v-if="planner.comparison" class="rounded border border-[var(--ks-border)] p-4">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p class="text-xs font-bold tracking-wide text-[var(--ks-muted)] uppercase">{{ t('progression.factualPath') }}</p>
              <p class="mt-1 font-semibold">{{ statusLabel(planner.comparison.status) }}</p>
            </div>
            <span v-if="planner.comparison.remainingTransitions !== null" class="ks-chip">
              {{ t('progression.transitionsRemaining', { count: planner.comparison.remainingTransitions }) }}
            </span>
          </div>

          <ol v-if="planner.comparison.path.length" class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="step in planner.comparison.path" :key="step.id" class="rounded border border-[var(--ks-border)] bg-black/15 px-3 py-2 text-sm">
              {{ step.label }}
            </li>
          </ol>

          <div v-if="planner.comparison.prerequisites.length" class="mt-4">
            <p class="text-xs font-semibold text-[var(--ks-muted)]">{{ t('progression.prerequisites') }}</p>
            <ul class="mt-2 space-y-2">
              <li v-for="(prerequisite, index) in planner.comparison.prerequisites" :key="`${prerequisite.label}-${index}`" class="flex items-start justify-between gap-3 rounded border border-[var(--ks-border)] px-3 py-2 text-sm">
                <span>{{ prerequisite.label }}</span>
                <span class="ks-chip">{{ statusLabel(prerequisite.status) }}</span>
              </li>
            </ul>
          </div>
        </article>
      </div>

      <aside class="space-y-4">
        <article class="rounded border border-[var(--ks-border)] bg-black/15 p-4">
          <p class="text-xs font-bold tracking-wide text-[var(--ks-muted)] uppercase">{{ t('progression.calculatorStatus') }}</p>
          <template v-if="selectedQualification">
            <div class="mt-2 flex flex-wrap items-center gap-2">
              <span class="ks-chip">{{ statusLabel(selectedQualification.status) }}</span>
              <span class="text-xs text-[var(--ks-muted)]">{{ t('progression.reviewedAt') }} {{ selectedQualification.reviewedAt }}</span>
            </div>
            <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">{{ selectedQualification.reason }}</p>
            <p v-if="selectedQualification.status !== 'calculator_ready'" class="mt-3 rounded border border-amber-400/25 bg-amber-300/5 p-3 text-sm text-amber-100">
              {{ t('progression.calculatorUnavailableHelp') }}
            </p>
            <details class="mt-4">
              <summary class="cursor-pointer text-sm font-semibold">{{ t('progression.evidenceGateDetails') }}</summary>
              <ul class="mt-3 space-y-2">
                <li v-for="(gate, gateId) in selectedQualification.gates" :key="gateId" class="rounded border border-[var(--ks-border)] p-3 text-xs">
                  <div class="flex items-center justify-between gap-2">
                    <span class="font-semibold">{{ String(gateId).replaceAll('_', ' ') }}</span>
                    <span class="ks-chip">{{ gate.status }}</span>
                  </div>
                  <p class="mt-1 leading-5 text-[var(--ks-muted)]">{{ gate.reason }}</p>
                </li>
              </ul>
            </details>
          </template>
          <p v-else class="mt-2 text-sm leading-6 text-[var(--ks-muted)]">
            {{ t('progression.noCalculatorFamily') }}
          </p>
        </article>

        <article v-if="planner.conflicts.length" class="rounded border border-amber-400/25 bg-amber-300/5 p-4">
          <p class="text-xs font-bold tracking-wide text-amber-100 uppercase">{{ t('progression.sourceConflicts') }}</p>
          <p class="mt-2 text-sm leading-6 text-amber-50">{{ t('progression.plannerConflictHelp') }}</p>
        </article>
      </aside>
    </div>
  </section>
</template>
