<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';
import { useConfirmAction } from '@/components/ui/useConfirmAction';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type Readiness = 'not_started' | 'preparing' | 'ready' | 'blocked' | 'confirmed' | 'withdrawn';
type Outcome =
  | 'eligible_now'
  | 'eligible_with_action'
  | 'blocked'
  | 'needs_verification'
  | 'not_open_yet'
  | 'window_closed'
  | 'not_applicable';
type RequirementState = 'met' | 'unmet' | 'unknown' | 'stale' | 'conflicting' | 'not_applicable';
type ObservationKind =
  | 'governor_power'
  | 'transfer_score'
  | 'transfer_passes_available'
  | 'transfer_passes_required'
  | 'invitation_status'
  | 'in_game_rules_verified';
type SourceType = 'official_publication' | 'in_game' | 'evidence' | 'manager_note' | 'community';

type Requirement = {
  key: string;
  state: RequirementState;
  explanation: string;
  actual: string | number | boolean | null;
  required: string | number | boolean | null;
  nextAction: string | null;
  sourceType: SourceType | null;
  sourceReference: string | null;
  observedAt: string | null;
  validUntil: string | null;
};
type Observation = {
  id: string;
  kind: ObservationKind;
  value: string | number | boolean | null;
  details: string | null;
  targetKingdom: string | null;
  sourceType: SourceType;
  sourceReference: string;
  observedAt: string;
  validUntil: string | null;
};
type Blocker = {
  id: string;
  state: 'active' | 'resolved';
  summary: string;
  details: string | null;
  createdAt: string | null;
  resolvedAt: string | null;
  createdBy: { name: string } | null;
  resolvedBy: { name: string } | null;
};
type History = {
  from: Readiness | null;
  to: Readiness;
  changedAt: string;
  actor: { name: string } | null;
};
type Participant = {
  id: string;
  name: string;
  direction: 'staying' | 'outgoing' | 'incoming';
  readiness: Readiness;
  cohortName: string | null;
  destinationKingdom: string | null;
  sourceKingdom: string | null;
  withdrawnAt: string | null;
  completedAt: string | null;
  officialGroup: string | null;
  targetCondition: {
    powerCap: number | null;
    classification: string;
    sourceType: SourceType;
    sourceReference: string;
    observedAt: string;
  } | null;
  transferScore: {
    state: RequirementState;
    value: string | number | boolean | null;
    sourceType: SourceType | null;
    sourceReference: string | null;
    observedAt: string | null;
    validUntil: string | null;
    details: string | null;
  };
  eligibility: {
    outcome: Outcome;
    primaryAction: string | null;
    evaluatedAt: string;
    requirements: Requirement[];
  } | null;
  observations: Observation[];
  blockers: Blocker[];
  readinessHistory: History[];
};
type Plan = {
  id: string;
  label: string;
  homeKingdom: string;
  state: string;
  mutable: boolean;
  window: {
    id: string;
    label: string;
    phase: string;
    preTransferStartsAt: string;
    invitationalStartsAt: string;
    transferOpensAt: string;
    endsAt: string;
    sourceType: SourceType;
    sourceReference: string;
    observedAt: string;
  };
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string; kingdom: string };
  plan: Plan | null;
  participants: Participant[];
}>();
const { t, formatDate, formatNumber } = useLocale();
const { dialog, requestConfirmation, cancelConfirmation, confirmAction } = useConfirmAction();
const filter = ref('all');
const readinessDrafts = reactive(
  Object.fromEntries(props.participants.map((p) => [p.id, p.readiness])) as Record<
    string,
    Readiness
  >,
);
const blockerDrafts = reactive(
  Object.fromEntries(props.participants.map((p) => [p.id, { summary: '', details: '' }])) as Record<
    string,
    { summary: string; details: string }
  >,
);
const observationDrafts = reactive(
  Object.fromEntries(
    props.participants.map((p) => [
      p.id,
      {
        kind: 'governor_power' as ObservationKind,
        value: '',
        source_type: 'in_game' as SourceType,
        source_reference: 'KingShot in-game transfer screen',
        observed_at: localInputNow(),
        valid_until: localInputLater(),
        details: '',
      },
    ]),
  ) as Record<
    string,
    {
      kind: ObservationKind;
      value: string;
      source_type: SourceType;
      source_reference: string;
      observed_at: string;
      valid_until: string;
      details: string;
    }
  >,
);

const observationKinds: ObservationKind[] = [
  'governor_power',
  'transfer_score',
  'transfer_passes_available',
  'transfer_passes_required',
  'invitation_status',
  'in_game_rules_verified',
];
const sourceTypes: SourceType[] = [
  'in_game',
  'evidence',
  'official_publication',
  'manager_note',
  'community',
];

const filtered = computed(() =>
  props.participants.filter((p) => {
    if (filter.value === 'all') return true;
    if (filter.value === 'missing_target')
      return p.direction === 'outgoing' && p.destinationKingdom === null;
    if (filter.value === 'needs_invite') return requirement(p, 'invitation')?.state === 'unmet';
    if (filter.value === 'insufficient_passes')
      return requirement(p, 'transfer_passes')?.state === 'unmet';
    if (filter.value === 'over_cap') {
      const r = requirement(p, 'power_cap');
      return (
        typeof r?.actual === 'number' && typeof r.required === 'number' && r.actual > r.required
      );
    }
    return p.eligibility?.outcome === filter.value;
  }),
);

function localInputNow(): string {
  const d = new Date();
  d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
  return d.toISOString().slice(0, 16);
}
function localInputLater(): string {
  const d = new Date(Date.now() + 24 * 60 * 60 * 1000);
  d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
  return d.toISOString().slice(0, 16);
}
function requirement(p: Participant, key: string): Requirement | undefined {
  return p.eligibility?.requirements.find((r) => r.key === key);
}
function timestamp(v: string | null): string {
  return v
    ? formatDate(v, { dateStyle: 'medium', timeStyle: 'short' })
    : t('kingdomP7D.notSpecified');
}
function sourceLabel(v: SourceType | null): string {
  return v ? t(`kingdomP7D.source_${v}`) : t('kingdomP7D.unknown');
}
function outcomeLabel(v: Outcome): string {
  return t(`kingdomP7D.eligibility_${v}`);
}
function requirementStateLabel(v: RequirementState): string {
  return t(`kingdomP7D.requirement_${v}`);
}
function phaseLabel(v: string): string {
  return t(`kingdomP7D.phase_${v}`);
}
function readinessLabel(v: Readiness): string {
  return t(`kingdomP7D.readiness_${v}`);
}
function kindLabel(v: ObservationKind): string {
  return t(`kingdomP7D.observation_${v}`);
}
function outcomeTone(v: Outcome): string {
  if (v === 'eligible_now') return 'border-green-400/30 bg-green-500/10 text-green-200';
  if (v === 'blocked') return 'border-red-400/30 bg-red-500/10 text-red-200';
  if (v === 'needs_verification') return 'border-amber-400/30 bg-amber-500/10 text-amber-100';
  return 'border-[var(--ks-border)] bg-black/15 text-[var(--ks-gold-bright)]';
}
function stateTone(v: RequirementState): string {
  if (v === 'met' || v === 'not_applicable') return 'text-green-200';
  if (v === 'unmet') return 'text-red-200';
  return 'text-amber-100';
}
function displayValue(v: unknown): string {
  if (typeof v === 'number') return formatNumber(v);
  if (typeof v === 'boolean') return v ? t('common.yes') : t('common.no');
  return v === null || v === undefined || v === '' ? '—' : String(v);
}
function allowedTransitions(p: Participant): Readiness[] {
  if (p.readiness === 'withdrawn') return ['withdrawn'];
  const map: Record<Exclude<Readiness, 'withdrawn'>, Readiness[]> = {
    not_started: ['not_started', 'preparing', 'blocked'],
    preparing: ['preparing', 'ready', 'blocked'],
    ready: ['ready', 'preparing', 'blocked', 'confirmed'],
    blocked: ['blocked', 'preparing', 'ready'],
    confirmed: ['confirmed', 'ready', 'blocked'],
  };
  return map[p.readiness];
}

function saveReadiness(p: Participant): void {
  if (!props.plan?.mutable || p.withdrawnAt) return;
  router.patch(
    `/alliance/transfers/${props.plan.id}/participants/${p.id}/readiness`,
    { readiness: readinessDrafts[p.id] },
    { preserveScroll: true },
  );
}
function addBlocker(p: Participant): void {
  if (!props.plan?.mutable || p.withdrawnAt) return;
  const d = blockerDrafts[p.id]!;
  router.post(`/alliance/transfers/${props.plan.id}/participants/${p.id}/blockers`, d, {
    preserveScroll: true,
    onSuccess: () => {
      d.summary = '';
      d.details = '';
    },
  });
}
function resolveBlocker(p: Participant, b: Blocker): void {
  if (!props.plan?.mutable || b.state !== 'active') return;
  router.post(
    `/alliance/transfers/${props.plan.id}/participants/${p.id}/blockers/${b.id}/resolve`,
    {},
    { preserveScroll: true },
  );
}
function withdrawParticipant(p: Participant): void {
  if (!props.plan?.mutable || p.withdrawnAt) return;
  const id = props.plan.id;
  requestConfirmation({
    id: 'transfer-readiness-withdrawal-confirmation',
    title: t('kingdomP7D.withdraw'),
    description: t('kingdomP7D.withdrawConfirm', { name: p.name }),
    confirmLabel: t('kingdomP7D.withdraw'),
    cancelLabel: t('common.cancel'),
    perform: (finish) =>
      router.post(
        `/alliance/transfers/${id}/participants/${p.id}/withdraw`,
        {},
        { preserveScroll: true, onFinish: finish },
      ),
  });
}
function recordObservation(p: Participant): void {
  if (!props.plan?.mutable || p.withdrawnAt) return;
  const d = observationDrafts[p.id]!;
  let value: string | number | boolean = d.value;
  if (
    [
      'governor_power',
      'transfer_score',
      'transfer_passes_available',
      'transfer_passes_required',
    ].includes(d.kind)
  )
    value = Number(d.value);
  if (d.kind === 'in_game_rules_verified') value = d.value === 'true';
  router.post(
    `/alliance/transfers/${props.plan.id}/participants/${p.id}/observations`,
    {
      ...d,
      value,
      observed_at: new Date(d.observed_at).toISOString(),
      valid_until: d.valid_until ? new Date(d.valid_until).toISOString() : null,
      details: d.details || null,
    },
    { preserveScroll: true },
  );
}
</script>

<template>
  <Head :title="`${t('kingdomP7D.eligibilityTitle')} · ${alliance.name}`" />
  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <header class="flex flex-wrap items-start justify-between gap-5">
      <div class="max-w-3xl">
        <p class="ks-kicker">{{ t('kingdomP7D.eyebrow') }}</p>
        <h1 class="ks-display mt-2 text-3xl font-bold sm:text-4xl">
          {{ t('kingdomP7D.eligibilityTitle') }}
        </h1>
        <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('kingdomP7D.eligibilitySubtitle') }}
        </p>
      </div>
      <nav :aria-label="t('kingdomP7D.overviewNavigation')" class="flex flex-wrap gap-2">
        <Link class="ks-command-link" href="/alliance/transfers">{{ t('kingdomP7D.title') }}</Link
        ><Link class="ks-command-link" href="/alliance/transfers/manage">{{
          t('kingdomP7D.manageTransfers')
        }}</Link
        ><Link class="ks-command-link" href="/alliance/transfers/completion">{{
          t('kingdomP7D.completion')
        }}</Link>
      </nav>
    </header>

    <section v-if="plan" class="ks-surface-gold mt-6 p-5 sm:p-6" aria-labelledby="window-heading">
      <div class="flex flex-wrap justify-between gap-4">
        <div>
          <p class="ks-kicker">{{ t('kingdomP7D.transferWindow') }}</p>
          <h2 id="window-heading" class="ks-display mt-1 text-2xl">{{ plan.window.label }}</h2>
          <p class="mt-2 text-sm text-[var(--ks-muted)]">
            {{ phaseLabel(plan.window.phase) }} · {{ timestamp(plan.window.endsAt) }}
          </p>
        </div>
        <div class="text-sm text-[var(--ks-muted)]">
          <p>{{ sourceLabel(plan.window.sourceType) }}</p>
          <p>{{ timestamp(plan.window.observedAt) }}</p>
          <p class="max-w-sm break-all">{{ plan.window.sourceReference }}</p>
        </div>
      </div>
    </section>
    <section v-if="plan" class="ks-surface mt-4 p-4">
      <label class="ks-kicker" for="eligibility-filter">{{
        t('kingdomP7D.eligibilityFilter')
      }}</label
      ><select
        id="eligibility-filter"
        v-model="filter"
        class="mt-2 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2 sm:max-w-sm"
      >
        <option value="all">{{ t('kingdomP7D.filter_all') }}</option>
        <option value="eligible_now">{{ t('kingdomP7D.eligibility_eligible_now') }}</option>
        <option value="blocked">{{ t('kingdomP7D.eligibility_blocked') }}</option>
        <option value="needs_verification">
          {{ t('kingdomP7D.eligibility_needs_verification') }}
        </option>
        <option value="needs_invite">{{ t('kingdomP7D.filter_needs_invite') }}</option>
        <option value="insufficient_passes">
          {{ t('kingdomP7D.filter_insufficient_passes') }}
        </option>
        <option value="over_cap">{{ t('kingdomP7D.filter_over_cap') }}</option>
        <option value="missing_target">{{ t('kingdomP7D.filter_missing_target') }}</option>
      </select>
    </section>

    <section v-if="plan && filtered.length" class="mt-5 grid gap-5">
      <article v-for="p in filtered" :key="p.id" class="ks-surface overflow-hidden">
        <div class="p-5 sm:p-6">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
              <p class="ks-kicker">
                {{
                  p.direction === 'incoming' ? t('kingdomP7D.source') : t('kingdomP7D.destination')
                }}
                ·
                {{
                  p.direction === 'incoming'
                    ? (p.sourceKingdom ?? '—')
                    : (p.destinationKingdom ?? t('kingdomP7D.undecided'))
                }}
              </p>
              <h2 class="ks-display mt-1 text-2xl">{{ p.name }}</h2>
              <p class="mt-2 text-sm text-[var(--ks-muted)]">
                {{ t('kingdomP7D.planningCohort') }}:
                {{ p.cohortName ?? t('kingdomP7D.unassigned') }} · {{ t('kingdomP7D.readiness') }}:
                {{ readinessLabel(p.readiness) }}
              </p>
            </div>
            <span
              v-if="p.eligibility"
              :class="[
                'rounded-full border px-3 py-1 text-sm font-bold',
                outcomeTone(p.eligibility.outcome),
              ]"
              >{{ outcomeLabel(p.eligibility.outcome) }}</span
            >
          </div>
          <div
            v-if="p.eligibility"
            class="mt-4 rounded-xl border border-[var(--ks-border)] bg-black/15 p-4"
          >
            <p class="font-semibold">
              {{ p.eligibility.primaryAction ?? t('kingdomP7D.noRemainingActions') }}
            </p>
            <p class="mt-1 text-xs text-[var(--ks-muted)]">
              {{ t('kingdomP7D.evaluatedAt') }} {{ timestamp(p.eligibility.evaluatedAt) }}
            </p>
          </div>
          <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border border-[var(--ks-border)] p-3">
              <dt class="ks-kicker">{{ t('kingdomP7D.officialTransferGroup') }}</dt>
              <dd class="mt-1 font-semibold">
                {{ p.officialGroup ?? t('kingdomP7D.needsVerification') }}
              </dd>
            </div>
            <div class="rounded-lg border border-[var(--ks-border)] p-3">
              <dt class="ks-kicker">{{ t('kingdomP7D.powerCap') }}</dt>
              <dd class="mt-1 font-semibold">
                {{
                  p.targetCondition?.powerCap == null
                    ? t('kingdomP7D.needsVerification')
                    : formatNumber(p.targetCondition.powerCap)
                }}
              </dd>
            </div>
            <div class="rounded-lg border border-[var(--ks-border)] p-3">
              <dt class="ks-kicker">{{ t('kingdomP7D.transferScore') }}</dt>
              <dd class="mt-1 font-semibold">{{ displayValue(p.transferScore.value) }}</dd>
              <dd class="mt-1 text-xs text-[var(--ks-muted)]">
                {{
                  p.transferScore.observedAt
                    ? timestamp(p.transferScore.observedAt)
                    : t('kingdomP7D.noObservation')
                }}
              </dd>
            </div>
            <div class="rounded-lg border border-[var(--ks-border)] p-3">
              <dt class="ks-kicker">{{ t('kingdomP7D.windowPhase') }}</dt>
              <dd class="mt-1 font-semibold">{{ phaseLabel(plan.window.phase) }}</dd>
            </div>
          </dl>
        </div>

        <div v-if="p.eligibility" class="border-t border-[var(--ks-border)] p-5 sm:p-6">
          <h3 class="text-lg font-semibold">{{ t('kingdomP7D.eligibilityRequirements') }}</h3>
          <ul class="mt-3 grid gap-3">
            <li
              v-for="r in p.eligibility.requirements"
              :key="r.key"
              class="rounded-xl border border-[var(--ks-border)] p-4"
            >
              <div class="flex flex-wrap justify-between gap-2">
                <strong>{{ t(`kingdomP7D.requirementKey_${r.key}`) }}</strong
                ><span :class="['text-sm font-bold', stateTone(r.state)]">{{
                  requirementStateLabel(r.state)
                }}</span>
              </div>
              <p class="mt-2 text-sm text-[var(--ks-text-secondary)]">{{ r.explanation }}</p>
              <p v-if="r.actual !== null || r.required !== null" class="mt-2 text-sm">
                {{ t('kingdomP7D.actual') }}: {{ displayValue(r.actual) }} ·
                {{ t('kingdomP7D.required') }}: {{ displayValue(r.required) }}
              </p>
              <p
                v-if="r.nextAction"
                class="mt-2 text-sm font-semibold text-[var(--ks-gold-bright)]"
              >
                {{ r.nextAction }}
              </p>
              <div class="mt-3 text-xs text-[var(--ks-muted)]">
                <span>{{ sourceLabel(r.sourceType) }}</span
                ><span v-if="r.observedAt"> · {{ timestamp(r.observedAt) }}</span
                ><span v-if="r.validUntil">
                  · {{ t('kingdomP7D.validUntil') }} {{ timestamp(r.validUntil) }}</span
                >
                <p v-if="r.sourceReference" class="mt-1 break-all">{{ r.sourceReference }}</p>
              </div>
            </li>
          </ul>
        </div>

        <div class="border-t border-[var(--ks-border)] p-5 sm:p-6">
          <details>
            <summary class="cursor-pointer text-lg font-semibold">
              {{ t('kingdomP7D.recordObservation') }}
            </summary>
            <form
              class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
              @submit.prevent="recordObservation(p)"
            >
              <label class="text-sm font-semibold"
                >{{ t('kingdomP7D.observationKind')
                }}<select
                  v-model="observationDrafts[p.id]!.kind"
                  class="mt-1 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2"
                >
                  <option v-for="k in observationKinds" :key="k" :value="k">
                    {{ kindLabel(k) }}
                  </option>
                </select></label
              ><label class="text-sm font-semibold"
                >{{ t('kingdomP7D.observedValue')
                }}<select
                  v-if="observationDrafts[p.id]!.kind === 'invitation_status'"
                  v-model="observationDrafts[p.id]!.value"
                  class="mt-1 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2"
                  required
                >
                  <option value="none">{{ t('kingdomP7D.invitation_none') }}</option>
                  <option value="ordinary_received">
                    {{ t('kingdomP7D.invitation_ordinary_received') }}
                  </option>
                  <option value="special_pending">
                    {{ t('kingdomP7D.invitation_special_pending') }}
                  </option>
                  <option value="special_approved">
                    {{ t('kingdomP7D.invitation_special_approved') }}
                  </option></select
                ><select
                  v-else-if="observationDrafts[p.id]!.kind === 'in_game_rules_verified'"
                  v-model="observationDrafts[p.id]!.value"
                  class="mt-1 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2"
                  required
                >
                  <option value="true">{{ t('common.yes') }}</option>
                  <option value="false">{{ t('common.no') }}</option></select
                ><input
                  v-else
                  v-model="observationDrafts[p.id]!.value"
                  class="mt-1 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2"
                  min="0"
                  required
                  type="number" /></label
              ><label class="text-sm font-semibold"
                >{{ t('kingdomP7D.sourceType')
                }}<select
                  v-model="observationDrafts[p.id]!.source_type"
                  class="mt-1 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2"
                >
                  <option v-for="s in sourceTypes" :key="s" :value="s">{{ sourceLabel(s) }}</option>
                </select></label
              ><label class="text-sm font-semibold sm:col-span-2"
                >{{ t('kingdomP7D.sourceReference')
                }}<input
                  v-model="observationDrafts[p.id]!.source_reference"
                  class="mt-1 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2"
                  maxlength="2048"
                  required /></label
              ><label class="text-sm font-semibold"
                >{{ t('kingdomP7D.observedAt')
                }}<input
                  v-model="observationDrafts[p.id]!.observed_at"
                  class="mt-1 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2"
                  required
                  type="datetime-local" /></label
              ><label class="text-sm font-semibold"
                >{{ t('kingdomP7D.validUntil')
                }}<input
                  v-model="observationDrafts[p.id]!.valid_until"
                  class="mt-1 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2"
                  type="datetime-local" /></label
              ><label class="text-sm font-semibold sm:col-span-2"
                >{{ t('kingdomP7D.details')
                }}<textarea
                  v-model="observationDrafts[p.id]!.details"
                  class="mt-1 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2"
                  rows="2"
                />
              </label>
              <div>
                <button
                  class="rounded-lg bg-[var(--ks-gold)] px-4 py-2 text-sm font-bold text-[var(--ks-ink)]"
                  type="submit"
                >
                  {{ t('kingdomP7D.recordObservation') }}
                </button>
              </div>
            </form>
          </details>
          <details class="mt-4">
            <summary class="cursor-pointer font-semibold">
              {{ t('kingdomP7D.observationHistory') }} ({{ p.observations.length }})
            </summary>
            <ul class="mt-3 grid gap-2">
              <li
                v-for="o in p.observations"
                :key="o.id"
                class="rounded-lg border border-[var(--ks-border)] p-3 text-sm"
              >
                <strong>{{ kindLabel(o.kind) }} · {{ displayValue(o.value) }}</strong>
                <p class="mt-1 text-[var(--ks-muted)]">
                  {{ sourceLabel(o.sourceType) }} · {{ timestamp(o.observedAt)
                  }}<span v-if="o.validUntil">
                    · {{ t('kingdomP7D.validUntil') }} {{ timestamp(o.validUntil) }}</span
                  >
                </p>
                <p class="mt-1 text-xs break-all text-[var(--ks-muted)]">{{ o.sourceReference }}</p>
                <p v-if="o.details" class="mt-2">{{ o.details }}</p>
              </li>
            </ul>
          </details>
        </div>

        <div class="border-t border-[var(--ks-border)] p-5 sm:p-6">
          <h3 class="text-lg font-semibold">{{ t('kingdomP7D.readinessWorkflow') }}</h3>
          <p class="mt-2 text-sm text-[var(--ks-muted)]">
            {{ t('kingdomP7D.readinessIndependentHelp') }}
          </p>
          <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <fieldset class="rounded-xl border border-[var(--ks-border)] p-4">
              <legend class="px-2 font-semibold">{{ t('kingdomP7D.readiness') }}</legend>
              <select
                v-if="!p.withdrawnAt"
                v-model="readinessDrafts[p.id]"
                :disabled="!plan.mutable"
                class="w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2"
              >
                <option v-for="s in allowedTransitions(p)" :key="s" :value="s">
                  {{ readinessLabel(s) }}
                </option>
              </select>
              <div class="mt-3 flex gap-2">
                <button
                  v-if="!p.withdrawnAt"
                  :disabled="!plan.mutable || readinessDrafts[p.id] === p.readiness"
                  class="rounded-lg border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold disabled:opacity-40"
                  type="button"
                  @click="saveReadiness(p)"
                >
                  {{ t('kingdomP7D.saveReadiness') }}</button
                ><button
                  v-if="!p.withdrawnAt"
                  :disabled="!plan.mutable"
                  class="rounded-lg border border-red-400/30 px-3 py-2 text-sm text-red-200 disabled:opacity-40"
                  type="button"
                  @click="withdrawParticipant(p)"
                >
                  {{ t('kingdomP7D.withdraw') }}
                </button>
              </div>
            </fieldset>
            <fieldset class="rounded-xl border border-[var(--ks-border)] p-4">
              <legend class="px-2 font-semibold">
                {{ t('kingdomP7D.manualPlanningBlockers') }}
              </legend>
              <form v-if="!p.withdrawnAt" @submit.prevent="addBlocker(p)">
                <input
                  v-model="blockerDrafts[p.id]!.summary"
                  :disabled="!plan.mutable"
                  :placeholder="t('kingdomP7D.blockerSummary')"
                  class="w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2"
                  required
                /><textarea
                  v-model="blockerDrafts[p.id]!.details"
                  :disabled="!plan.mutable"
                  :placeholder="t('kingdomP7D.privateDetails')"
                  class="mt-2 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2"
                  rows="2"
                /><button
                  :disabled="!plan.mutable"
                  class="mt-2 rounded-lg border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold"
                  type="submit"
                >
                  {{ t('kingdomP7D.addBlocker') }}
                </button>
              </form>
              <ul class="mt-3 grid gap-2">
                <li
                  v-for="b in p.blockers"
                  :key="b.id"
                  class="rounded-lg border border-[var(--ks-border)] p-3 text-sm"
                >
                  <div class="flex justify-between gap-2">
                    <strong>{{ b.summary }}</strong
                    ><button
                      v-if="b.state === 'active' && plan.mutable"
                      class="text-[var(--ks-gold-bright)]"
                      type="button"
                      @click="resolveBlocker(p, b)"
                    >
                      {{ t('kingdomP7D.resolve') }}
                    </button>
                  </div>
                  <p v-if="b.details" class="mt-1 text-[var(--ks-muted)]">{{ b.details }}</p>
                </li>
              </ul>
            </fieldset>
          </div>
          <details class="mt-4">
            <summary class="cursor-pointer font-semibold">
              {{ t('kingdomP7D.readinessHistory') }}
            </summary>
            <ul class="mt-2 text-sm text-[var(--ks-muted)]">
              <li v-for="h in p.readinessHistory" :key="`${h.changedAt}-${h.to}`">
                {{ readinessLabel(h.from ?? h.to) }} → {{ readinessLabel(h.to) }} ·
                {{ timestamp(h.changedAt) }} · {{ h.actor?.name ?? t('kingdomP7D.unknownActor') }}
              </li>
            </ul>
          </details>
        </div>
      </article>
    </section>
    <section v-else-if="plan" class="ks-surface mt-5 p-6">
      <p>{{ t('kingdomP7D.noEligibilityMatch') }}</p>
    </section>
    <section v-else class="ks-surface mt-6 p-6">
      <h2 class="text-xl font-semibold">{{ t('kingdomP7D.noCurrentCycle') }}</h2>
      <p class="mt-2 text-[var(--ks-muted)]">{{ t('kingdomP7D.createWindowAndPlan') }}</p>
    </section>
    <ConfirmActionDialog :dialog="dialog" @cancel="cancelConfirmation" @confirm="confirmAction" />
  </AppLayout>
</template>
