<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';
import { useConfirmAction } from '@/components/ui/useConfirmAction';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type SourceType = 'official_publication' | 'in_game' | 'evidence' | 'manager_note' | 'community';
type WindowRow = {
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
type Plan = {
  id: string;
  label: string;
  homeKingdom: string;
  state: string;
  createdAt: string | null;
  window: WindowRow;
};
type OfficialGroup = {
  id: string;
  officialLabel: string;
  revision: number;
  kingdoms: { id: string; number: string }[];
  sourceType: SourceType;
  sourceReference: string;
  observedAt: string;
  supersededAt: string | null;
};
type Condition = {
  id: string;
  kingdom: string;
  powerCap: number | null;
  classification: string;
  sourceType: SourceType;
  sourceReference: string;
  observedAt: string;
  isCorrection: boolean;
};
type Cohort = {
  id: string;
  name: string;
  direction: 'incoming' | 'outgoing';
  destinationKingdom: string | null;
  state: 'active' | 'archived';
  coordinator: { name: string } | null;
  coordinatorPlayerId: string | null;
  managerNotes: string | null;
};
type Participant = {
  id: string;
  direction: 'staying' | 'outgoing' | 'incoming';
  readiness: string;
  name: string;
  gamePlayerId: string | null;
  rosterEntryId?: string | null;
  transferCohortId?: string | null;
  sourceKingdom: string | null;
  destinationKingdom: string | null;
  player: { id: string; name: string };
  cohort: {
    name: string;
    direction: 'incoming' | 'outgoing';
    destinationKingdom: string | null;
    coordinator: { name: string } | null;
  } | null;
  managerNotes?: string | null;
  withdrawnAt: string | null;
};
type Roster = { id: string; name: string; gamePlayerId: string | null; playerId: string };
type PlayerOption = { id: string; name: string };
const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string; kingdom: string };
  plans: Plan[];
  mutablePlan: Plan | null;
  windows: WindowRow[];
  officialGroups: OfficialGroup[];
  conditions: Condition[];
  cohorts: Cohort[];
  participants: Participant[];
  rosterOptions: Roster[];
  players: PlayerOption[];
}>();
const { t, formatDate, formatNumber } = useLocale();
const { dialog, requestConfirmation, cancelConfirmation, confirmAction } = useConfirmAction();
const sourceTypes: SourceType[] = [
  'official_publication',
  'in_game',
  'manager_note',
  'community',
];
function local(v?: string): string {
  if (v) {
    const d = new Date(v);
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().slice(0, 16);
  }
  const d = new Date();
  d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
  return d.toISOString().slice(0, 16);
}
const windowForm = useForm({
  label: '',
  pre_transfer_starts_at: '',
  invitational_starts_at: '',
  transfer_opens_at: '',
  ends_at: '',
  source_type: 'official_publication' as SourceType,
  source_reference: '',
  observed_at: local(),
});
const planForm = useForm({ label: '', transfer_window_id: '' });
const groupForm = useForm({
  official_label: '',
  kingdom_numbers: '',
  source_type: 'in_game' as SourceType,
  source_reference: 'KingShot Transfer Group screen',
  observed_at: local(),
});
const conditionForm = useForm({
  kingdom_number: '',
  power_cap: '',
  classification: 'ordinary',
  source_type: 'in_game' as SourceType,
  source_reference: 'KingShot Kingdom Transfer screen',
  observed_at: local(),
  is_correction: false,
});
const cohortForm = useForm({
  name: '',
  direction: 'incoming' as 'incoming' | 'outgoing',
  destination_kingdom: '',
  coordinator_player_id: '',
  manager_notes: '',
});
const participantForm = useForm({
  direction: 'staying' as 'staying' | 'outgoing' | 'incoming',
  roster_entry_id: '',
  name: '',
  game_player_id: '',
  source_kingdom: '',
  destination_kingdom: '',
  manager_notes: '',
});
const cohortDrafts = reactive(
  Object.fromEntries(
    props.cohorts.map((c) => [
      c.id,
      {
        name: c.name,
        direction: c.direction,
        destination_kingdom: c.destinationKingdom ?? '',
        coordinator_player_id: c.coordinatorPlayerId ?? '',
        manager_notes: c.managerNotes ?? '',
      },
    ]),
  ) as Record<
    string,
    {
      name: string;
      direction: 'incoming' | 'outgoing';
      destination_kingdom: string;
      coordinator_player_id: string;
      manager_notes: string;
    }
  >,
);
const assignments = reactive(
  Object.fromEntries(props.participants.map((p) => [p.id, p.transferCohortId ?? ''])) as Record<
    string,
    string
  >,
);
const participantDrafts = reactive(
  Object.fromEntries(
    props.participants.map((p) => [
      p.id,
      {
        direction: p.direction,
        roster_entry_id: p.rosterEntryId ?? '',
        name: p.name,
        game_player_id: p.gamePlayerId ?? '',
        source_kingdom: p.sourceKingdom ?? '',
        destination_kingdom: p.destinationKingdom ?? '',
        manager_notes: p.managerNotes ?? '',
      },
    ]),
  ) as Record<
    string,
    {
      direction: 'staying' | 'outgoing' | 'incoming';
      roster_entry_id: string;
      name: string;
      game_player_id: string;
      source_kingdom: string;
      destination_kingdom: string;
      manager_notes: string;
    }
  >,
);
const activeCohorts = computed(() => props.cohorts.filter((c) => c.state === 'active'));
function sourceLabel(v: SourceType): string {
  return t(`kingdomP7D.source_${v}`);
}
function phaseLabel(v: string): string {
  return t(`kingdomP7D.phase_${v}`);
}
function ts(v: string): string {
  return formatDate(v, { dateStyle: 'medium', timeStyle: 'short' });
}
function stateLabel(v: string): string {
  return t(`kingdomP7D.state_${v}`);
}
function createWindow(): void {
  windowForm.post('/alliance/transfers/windows', {
    preserveScroll: true,
    onSuccess: () => windowForm.reset(),
  });
}
function createPlan(): void {
  planForm.post('/alliance/transfers', { preserveScroll: true, onSuccess: () => planForm.reset() });
}
function transition(p: Plan, a: 'open' | 'lock' | 'close' | 'cancel'): void {
  if (a === 'cancel') {
    requestConfirmation({
      id: 'transfer-plan-cancel',
      title: t('kingdomP7D.cancel'),
      description: t('kingdomP7D.cancelCycleConfirm', { label: p.label }),
      confirmLabel: t('kingdomP7D.cancel'),
      cancelLabel: t('common.cancel'),
      perform: (finish) =>
        router.post(
          `/alliance/transfers/${p.id}/${a}`,
          {},
          { preserveScroll: true, onFinish: finish },
        ),
    });
    return;
  }
  router.post(`/alliance/transfers/${p.id}/${a}`, {}, { preserveScroll: true });
}
function createOfficialGroup(): void {
  if (!props.mutablePlan) return;
  const data = {
    ...groupForm.data(),
    kingdom_numbers: groupForm.kingdom_numbers
      .split(',')
      .map((v) => Number(v.trim()))
      .filter((v) => Number.isInteger(v) && v > 0),
  };
  router.post(`/alliance/transfers/windows/${props.mutablePlan.window.id}/official-groups`, data, {
    preserveScroll: true,
  });
}
function recordCondition(): void {
  if (!props.mutablePlan) return;
  router.post(
    `/alliance/transfers/windows/${props.mutablePlan.window.id}/conditions`,
    {
      ...conditionForm.data(),
      kingdom_number: Number(conditionForm.kingdom_number),
      power_cap: conditionForm.power_cap === '' ? null : Number(conditionForm.power_cap),
    },
    { preserveScroll: true },
  );
}
function createCohort(): void {
  if (!props.mutablePlan) return;
  cohortForm.post(`/alliance/transfers/${props.mutablePlan.id}/cohorts`, {
    preserveScroll: true,
    onSuccess: () => cohortForm.reset(),
  });
}
function saveCohort(c: Cohort): void {
  if (!props.mutablePlan || c.state !== 'active') return;
  router.patch(`/alliance/transfers/${props.mutablePlan.id}/cohorts/${c.id}`, cohortDrafts[c.id], {
    preserveScroll: true,
  });
}
function archiveCohort(c: Cohort): void {
  if (!props.mutablePlan) return;
  requestConfirmation({
    id: 'archive-transfer-cohort',
    title: t('kingdomP7D.archive'),
    description: t('kingdomP7D.archiveCohortConfirm', { name: c.name }),
    confirmLabel: t('kingdomP7D.archive'),
    cancelLabel: t('common.cancel'),
    perform: (finish) =>
      router.post(
        `/alliance/transfers/${props.mutablePlan!.id}/cohorts/${c.id}/archive`,
        {},
        { preserveScroll: true, onFinish: finish },
      ),
  });
}
function createParticipant(): void {
  if (!props.mutablePlan) return;
  participantForm.post(`/alliance/transfers/${props.mutablePlan.id}/participants`, {
    preserveScroll: true,
    onSuccess: () => participantForm.reset(),
  });
}
function saveParticipant(p: Participant): void {
  if (!props.mutablePlan) return;
  router.patch(
    `/alliance/transfers/${props.mutablePlan.id}/participants/${p.id}`,
    participantDrafts[p.id],
    { preserveScroll: true },
  );
}
function assignCohort(p: Participant): void {
  if (!props.mutablePlan) return;
  router.patch(
    `/alliance/transfers/${props.mutablePlan.id}/participants/${p.id}/cohort`,
    { transfer_cohort_id: assignments[p.id] || null },
    { preserveScroll: true },
  );
}
function compatibleCohorts(p: Participant): Cohort[] {
  return activeCohorts.value.filter(
    (c) =>
      c.direction === p.direction &&
      (c.direction !== 'outgoing' ||
        c.destinationKingdom === null ||
        c.destinationKingdom === p.destinationKingdom),
  );
}
</script>

<template>
  <Head :title="`${t('kingdomP7D.manageTitle')} · ${alliance.name}`" /><AppLayout
    :user="user"
    :player-alliance-name="alliance.name"
    :has-player-alliance="true"
  >
    <header class="flex flex-wrap justify-between gap-5">
      <div>
        <p class="ks-kicker">{{ t('kingdomP7D.eyebrow') }}</p>
        <h1 class="ks-display mt-2 text-3xl font-bold">{{ t('kingdomP7D.manageTitle') }}</h1>
        <p class="mt-2 text-[var(--ks-muted)]">{{ t('kingdomP7D.managePlanningSubtitle') }}</p>
      </div>
      <nav class="flex flex-wrap gap-2">
        <Link class="ks-command-link" href="/alliance/transfers">{{ t('kingdomP7D.title') }}</Link
        ><Link class="ks-command-link" href="/alliance/transfers/readiness">{{
          t('kingdomP7D.eligibilityTitle')
        }}</Link>
      </nav>
    </header>
    <section class="mt-6 grid gap-5 xl:grid-cols-2">
      <form class="ks-surface p-5" @submit.prevent="createWindow">
        <h2 class="text-xl font-semibold">{{ t('kingdomP7D.createTransferWindow') }}</h2>
        <p class="mt-2 text-sm text-[var(--ks-muted)]">{{ t('kingdomP7D.windowSourceHelp') }}</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
          <label
            >{{ t('kingdomP7D.windowLabel')
            }}<input v-model="windowForm.label" class="ks-input mt-1 w-full" required /></label
          ><label
            >{{ t('kingdomP7D.sourceType')
            }}<select v-model="windowForm.source_type" class="ks-input mt-1 w-full">
              <option v-for="s in sourceTypes" :key="s" :value="s">{{ sourceLabel(s) }}</option>
            </select></label
          ><label
            >{{ t('kingdomP7D.phase_pre_transfer')
            }}<input
              v-model="windowForm.pre_transfer_starts_at"
              class="ks-input mt-1 w-full"
              required
              type="datetime-local" /></label
          ><label
            >{{ t('kingdomP7D.phase_invitational_transfer')
            }}<input
              v-model="windowForm.invitational_starts_at"
              class="ks-input mt-1 w-full"
              required
              type="datetime-local" /></label
          ><label
            >{{ t('kingdomP7D.phase_transfer_opens')
            }}<input
              v-model="windowForm.transfer_opens_at"
              class="ks-input mt-1 w-full"
              required
              type="datetime-local" /></label
          ><label
            >{{ t('kingdomP7D.ends')
            }}<input
              v-model="windowForm.ends_at"
              class="ks-input mt-1 w-full"
              required
              type="datetime-local" /></label
          ><label class="sm:col-span-2"
            >{{ t('kingdomP7D.sourceReference')
            }}<input
              v-model="windowForm.source_reference"
              class="ks-input mt-1 w-full"
              required /></label
          ><label
            >{{ t('kingdomP7D.observedAt')
            }}<input
              v-model="windowForm.observed_at"
              class="ks-input mt-1 w-full"
              required
              type="datetime-local"
          /></label>
        </div>
        <button
          class="mt-4 rounded-lg bg-[var(--ks-gold)] px-4 py-2 font-bold text-[var(--ks-ink)]"
        >
          {{ t('kingdomP7D.createTransferWindow') }}
        </button>
      </form>
      <div class="ks-surface p-5">
        <h2 class="text-xl font-semibold">{{ t('kingdomP7D.transferWindows') }}</h2>
        <div class="mt-3 grid gap-3">
          <article
            v-for="w in windows"
            :key="w.id"
            class="rounded-xl border border-[var(--ks-border)] p-4"
          >
            <div class="flex justify-between gap-3">
              <strong>{{ w.label }}</strong
              ><span>{{ phaseLabel(w.phase) }}</span>
            </div>
            <p class="mt-2 text-xs text-[var(--ks-muted)]">
              {{ ts(w.preTransferStartsAt) }} → {{ ts(w.endsAt) }}
            </p>
            <p class="mt-1 text-xs break-all text-[var(--ks-muted)]">
              {{ sourceLabel(w.sourceType) }} · {{ w.sourceReference }} · {{ ts(w.observedAt) }}
            </p>
          </article>
          <p v-if="!windows.length">{{ t('kingdomP7D.noTransferWindows') }}</p>
        </div>
      </div>
    </section>
    <section class="ks-surface mt-5 p-5">
      <h2 class="text-xl font-semibold">{{ t('kingdomP7D.transferPlans') }}</h2>
      <form class="mt-4 flex flex-wrap gap-3" @submit.prevent="createPlan">
        <input
          v-model="planForm.label"
          class="ks-input min-w-60 flex-1"
          :placeholder="t('kingdomP7D.cycleLabel')"
          required
        /><select v-model="planForm.transfer_window_id" class="ks-input min-w-60" required>
          <option value="" disabled>{{ t('kingdomP7D.chooseTransferWindow') }}</option>
          <option v-for="w in windows" :key="w.id" :value="w.id">{{ w.label }}</option></select
        ><button class="rounded-lg bg-[var(--ks-gold)] px-4 py-2 font-bold text-[var(--ks-ink)]">
          {{ t('kingdomP7D.createDraft') }}
        </button>
      </form>
      <div class="mt-4 grid gap-2">
        <article
          v-for="p in plans"
          :key="p.id"
          class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-[var(--ks-border)] p-3"
        >
          <div>
            <strong>{{ p.label }}</strong>
            <p class="text-xs text-[var(--ks-muted)]">
              {{ p.window.label }} · {{ stateLabel(p.state) }}
            </p>
          </div>
          <div class="flex gap-2">
            <button
              v-if="p.state === 'draft'"
              class="ks-command-link"
              @click="transition(p, 'open')"
            >
              {{ t('kingdomP7D.open') }}</button
            ><button
              v-if="p.state === 'open'"
              class="ks-command-link"
              @click="transition(p, 'lock')"
            >
              {{ t('kingdomP7D.lock') }}</button
            ><button
              v-if="p.state === 'locked'"
              class="ks-command-link"
              @click="transition(p, 'close')"
            >
              {{ t('kingdomP7D.close') }}</button
            ><button
              v-if="['draft', 'open', 'locked'].includes(p.state)"
              class="ks-command-link"
              @click="transition(p, 'cancel')"
            >
              {{ t('kingdomP7D.cancel') }}
            </button>
          </div>
        </article>
      </div>
    </section>
    <template v-if="mutablePlan"
      ><section class="mt-5 grid gap-5 xl:grid-cols-2">
        <form class="ks-surface p-5" @submit.prevent="createOfficialGroup">
          <h2 class="text-xl font-semibold">{{ t('kingdomP7D.officialTransferGroups') }}</h2>
          <p class="mt-2 text-sm text-[var(--ks-muted)]">{{ t('kingdomP7D.officialGroupHelp') }}</p>
          <label class="mt-3 block"
            >{{ t('kingdomP7D.officialGroupLabel')
            }}<input
              v-model="groupForm.official_label"
              class="ks-input mt-1 w-full"
              required /></label
          ><label class="mt-3 block"
            >{{ t('kingdomP7D.kingdomNumbers')
            }}<input
              v-model="groupForm.kingdom_numbers"
              class="ks-input mt-1 w-full"
              :placeholder="t('kingdomP7D.kingdomNumbersExample')"
              required /></label
          ><label class="mt-3 block"
            >{{ t('kingdomP7D.sourceReference')
            }}<input v-model="groupForm.source_reference" class="ks-input mt-1 w-full" required
          /></label>
          <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <select v-model="groupForm.source_type" class="ks-input">
              <option v-for="s in sourceTypes" :key="s" :value="s">
                {{ sourceLabel(s) }}
              </option></select
            ><input
              v-model="groupForm.observed_at"
              class="ks-input"
              required
              type="datetime-local"
            />
          </div>
          <button
            class="mt-3 rounded-lg bg-[var(--ks-gold)] px-4 py-2 font-bold text-[var(--ks-ink)]"
          >
            {{ t('kingdomP7D.recordOfficialGroup') }}
          </button>
        </form>
        <div class="ks-surface p-5">
          <h2 class="text-xl font-semibold">{{ t('kingdomP7D.officialGroupHistory') }}</h2>
          <article
            v-for="g in officialGroups"
            :key="g.id"
            class="mt-3 rounded-xl border border-[var(--ks-border)] p-3"
          >
            <div class="flex justify-between">
              <strong>{{ g.officialLabel }} · v{{ g.revision }}</strong
              ><span>{{
                g.supersededAt ? t('kingdomP7D.historical') : t('kingdomP7D.current')
              }}</span>
            </div>
            <p class="mt-2 text-sm">{{ g.kingdoms.map((k) => k.number).join(', ') }}</p>
            <p class="mt-1 text-xs break-all text-[var(--ks-muted)]">
              {{ sourceLabel(g.sourceType) }} · {{ ts(g.observedAt) }} · {{ g.sourceReference }}
            </p>
          </article>
        </div>
      </section>
      <section class="mt-5 grid gap-5 xl:grid-cols-2">
        <form class="ks-surface p-5" @submit.prevent="recordCondition">
          <h2 class="text-xl font-semibold">{{ t('kingdomP7D.targetKingdomCondition') }}</h2>
          <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <label
              >{{ t('kingdomP7D.destinationKingdom')
              }}<input
                v-model="conditionForm.kingdom_number"
                class="ks-input mt-1 w-full"
                min="1"
                required
                type="number" /></label
            ><label
              >{{ t('kingdomP7D.powerCap')
              }}<input
                v-model="conditionForm.power_cap"
                class="ks-input mt-1 w-full"
                min="0"
                type="number" /></label
            ><label
              >{{ t('kingdomP7D.kingdomClassification')
              }}<select v-model="conditionForm.classification" class="ks-input mt-1 w-full">
                <option value="ordinary">{{ t('kingdomP7D.classification_ordinary') }}</option>
                <option value="leading">{{ t('kingdomP7D.classification_leading') }}</option>
                <option value="unknown">{{ t('kingdomP7D.unknown') }}</option>
              </select></label
            ><label
              >{{ t('kingdomP7D.sourceType')
              }}<select v-model="conditionForm.source_type" class="ks-input mt-1 w-full">
                <option v-for="s in sourceTypes" :key="s" :value="s">{{ sourceLabel(s) }}</option>
              </select></label
            ><label class="sm:col-span-2"
              >{{ t('kingdomP7D.sourceReference')
              }}<input
                v-model="conditionForm.source_reference"
                class="ks-input mt-1 w-full"
                required /></label
            ><label
              >{{ t('kingdomP7D.observedAt')
              }}<input
                v-model="conditionForm.observed_at"
                class="ks-input mt-1 w-full"
                type="datetime-local" /></label
            ><label class="flex items-end gap-2"
              ><input v-model="conditionForm.is_correction" type="checkbox" />
              {{ t('kingdomP7D.authoritativeCorrection') }}</label
            >
          </div>
          <button
            class="mt-3 rounded-lg bg-[var(--ks-gold)] px-4 py-2 font-bold text-[var(--ks-ink)]"
          >
            {{ t('kingdomP7D.recordCondition') }}
          </button>
        </form>
        <div class="ks-surface p-5">
          <h2 class="text-xl font-semibold">{{ t('kingdomP7D.conditionHistory') }}</h2>
          <article
            v-for="c in conditions"
            :key="c.id"
            class="mt-3 rounded-xl border border-[var(--ks-border)] p-3"
          >
            <strong
              >{{ t('kingdomP7D.kingdomValue', { kingdom: c.kingdom }) }} ·
              {{ c.powerCap == null ? '—' : formatNumber(c.powerCap) }} ·
              {{ t(`kingdomP7D.classification_${c.classification}`) }}</strong
            >
            <p class="mt-1 text-xs break-all text-[var(--ks-muted)]">
              {{ sourceLabel(c.sourceType) }} · {{ ts(c.observedAt) }} · {{ c.sourceReference }}
            </p>
          </article>
        </div>
      </section>
      <section class="ks-surface mt-5 p-5">
        <h2 class="text-xl font-semibold">{{ t('kingdomP7D.planningCohorts') }}</h2>
        <p class="mt-2 text-sm text-[var(--ks-muted)]">{{ t('kingdomP7D.cohortHelp') }}</p>
        <form class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="createCohort">
          <input
            v-model="cohortForm.name"
            class="ks-input"
            :placeholder="t('kingdomP7D.cohortName')"
            required
          /><select v-model="cohortForm.direction" class="ks-input">
            <option value="incoming">{{ t('kingdomP7D.directionIncoming') }}</option>
            <option value="outgoing">{{ t('kingdomP7D.directionOutgoing') }}</option></select
          ><input
            v-model="cohortForm.destination_kingdom"
            class="ks-input"
            :disabled="cohortForm.direction === 'incoming'"
            :placeholder="t('kingdomP7D.destinationKingdom')"
            type="number"
          /><select v-model="cohortForm.coordinator_player_id" class="ks-input">
            <option value="">{{ t('kingdomP7D.unassigned') }}</option>
            <option v-for="p in players" :key="p.id" :value="p.id">{{ p.name }}</option></select
          ><button class="rounded-lg border border-[var(--ks-border)] px-3 py-2 font-semibold">
            {{ t('kingdomP7D.createCohort') }}
          </button>
        </form>
        <article
          v-for="c in cohorts"
          :key="c.id"
          class="mt-3 grid gap-2 rounded-xl border border-[var(--ks-border)] p-3 lg:grid-cols-6"
        >
          <input
            v-model="cohortDrafts[c.id]!.name"
            class="ks-input"
            :disabled="c.state === 'archived'"
          /><select
            v-model="cohortDrafts[c.id]!.direction"
            class="ks-input"
            :disabled="c.state === 'archived'"
          >
            <option value="incoming">{{ t('kingdomP7D.directionIncoming') }}</option>
            <option value="outgoing">{{ t('kingdomP7D.directionOutgoing') }}</option></select
          ><input
            v-model="cohortDrafts[c.id]!.destination_kingdom"
            class="ks-input"
            :disabled="c.state === 'archived' || cohortDrafts[c.id]!.direction === 'incoming'"
            type="number"
          /><select
            v-model="cohortDrafts[c.id]!.coordinator_player_id"
            class="ks-input"
            :disabled="c.state === 'archived'"
          >
            <option value="">{{ t('kingdomP7D.unassigned') }}</option>
            <option v-for="p in players" :key="p.id" :value="p.id">{{ p.name }}</option></select
          ><button
            :disabled="c.state === 'archived'"
            class="ks-command-link"
            @click="saveCohort(c)"
          >
            {{ t('kingdomP7D.save') }}</button
          ><button v-if="c.state === 'active'" class="ks-command-link" @click="archiveCohort(c)">
            {{ t('kingdomP7D.archive') }}
          </button>
        </article>
      </section>
      <section class="ks-surface mt-5 p-5">
        <h2 class="text-xl font-semibold">{{ t('kingdomP7D.participants') }}</h2>
        <form
          class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
          @submit.prevent="createParticipant"
        >
          <select v-model="participantForm.direction" class="ks-input">
            <option value="staying">{{ t('kingdomP7D.directionStaying') }}</option>
            <option value="outgoing">{{ t('kingdomP7D.directionOutgoing') }}</option>
            <option value="incoming">{{ t('kingdomP7D.directionIncoming') }}</option></select
          ><select
            v-if="participantForm.direction !== 'incoming'"
            v-model="participantForm.roster_entry_id"
            class="ks-input"
            required
          >
            <option value="">{{ t('kingdomP7D.chooseRosterEntry') }}</option>
            <option v-for="r in rosterOptions" :key="r.id" :value="r.id">
              {{ r.name }}
            </option></select
          ><input
            v-else
            v-model="participantForm.name"
            class="ks-input"
            :placeholder="t('kingdomP7D.observedName')"
            required
          /><input
            v-if="participantForm.direction === 'incoming'"
            v-model="participantForm.source_kingdom"
            class="ks-input"
            :placeholder="t('kingdomP7D.sourceKingdom')"
            required
            type="number"
          /><input
            v-if="participantForm.direction === 'outgoing'"
            v-model="participantForm.destination_kingdom"
            class="ks-input"
            :placeholder="t('kingdomP7D.destinationKingdom')"
            type="number"
          /><button class="rounded-lg border border-[var(--ks-border)] px-3 py-2 font-semibold">
            {{ t('kingdomP7D.createParticipant') }}
          </button>
        </form>
        <article
          v-for="p in participants"
          :key="p.id"
          class="mt-3 rounded-xl border border-[var(--ks-border)] p-4"
        >
          <div class="flex flex-wrap justify-between gap-3">
            <strong>{{ p.name }}</strong
            ><span
              >{{ p.direction }} ·
              {{ p.withdrawnAt ? t('kingdomP7D.withdrawn') : p.readiness }}</span
            >
          </div>
          <div v-if="!p.withdrawnAt" class="mt-3 grid gap-2 md:grid-cols-4">
            <select v-model="assignments[p.id]" class="ks-input">
              <option value="">{{ t('kingdomP7D.noCohort') }}</option>
              <option v-for="c in compatibleCohorts(p)" :key="c.id" :value="c.id">
                {{ c.name }}
              </option></select
            ><button class="ks-command-link" @click="assignCohort(p)">
              {{ t('kingdomP7D.saveCohortAssignment') }}</button
            ><input
              v-if="p.direction === 'outgoing'"
              v-model="participantDrafts[p.id]!.destination_kingdom"
              class="ks-input"
              type="number"
            /><button class="ks-command-link" @click="saveParticipant(p)">
              {{ t('kingdomP7D.save') }}
            </button>
          </div>
        </article>
      </section></template
    >
    <section v-else class="ks-surface mt-5 p-6">
      <p>{{ t('kingdomP7D.noMutableCycle') }}</p>
    </section>
    <ConfirmActionDialog v-bind="dialog" @cancel="cancelConfirmation" @confirm="confirmAction"
  /></AppLayout>
</template>
