<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';

import AppLayout from '../../../layouts/AppLayout.vue';
import { useLocale } from '../../../localization';

type RecommendedFormation = {
  id: string;
  name: string;
  assignmentRole: string;
  heroes: string[];
  infantryPercent: number;
  cavalryPercent: number;
  archerPercent: number;
  notes: string | null;
  guidance: {
    name: string;
    source: string | null;
    rationale: string | null;
    effectiveFrom: string | null;
    effectiveUntil: string | null;
  } | null;
};

type RallyGroup = {
  id: string;
  name: string;
  maxJoiners: number | null;
  notes: string | null;
  assignments: Array<{
    id: string;
    membershipId: string;
    memberName: string;
    role: string;
    slotNumber: number | null;
    status: string;
    participationRecordedAt: string | null;
  }>;
};

type SavedFormation = {
  id: string;
  name: string;
  heroes: string[];
  infantryPercent: number;
  cavalryPercent: number;
  archerPercent: number;
  notes: string | null;
  isDefault: boolean;
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string; timezone: string };
  userTimezone: string;
  canManage: boolean;
  event: {
    id: string;
    eventId: string;
    title: string;
    startsAt: string;
    endsAt: string;
    allianceTimezone: string;
    capacity: number | null;
    status: string;
    registrationOpensAt: string | null;
    registrationClosesAt: string | null;
    registration: { status: string; waitlistPosition: number | null } | null;
    instructions: string | null;
    registeredCount: number;
    waitlistedCount: number;
  };
  recommendedFormations: RecommendedFormation[];
  rallyGroups: RallyGroup[];
  savedFormations: SavedFormation[];
}>();

const { t, formatDate, formatNumber } = useLocale();

const formationForm = useForm({
  name: '',
  heroes_text: '',
  infantry_percent: 10,
  cavalry_percent: 10,
  archer_percent: 80,
  notes: '',
  is_default: false,
});

function formatInZone(value: string, timeZone: string): string {
  return formatDate(value, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    timeZone,
    timeZoneName: 'short',
  });
}

function formatGuidanceDate(value: string): string {
  return formatDate(`${value}T12:00:00Z`, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    timeZone: 'UTC',
  });
}

function canJoin(): boolean {
  if (props.event.registration && props.event.registration.status !== 'cancelled') return false;

  const now = Date.now();
  if (
    props.event.registrationOpensAt &&
    now < new Date(props.event.registrationOpensAt).getTime()
  ) {
    return false;
  }
  if (
    props.event.registrationClosesAt &&
    now > new Date(props.event.registrationClosesAt).getTime()
  ) {
    return false;
  }

  return new Date(props.event.startsAt).getTime() > now;
}

function canCancel(): boolean {
  return ['registered', 'waitlisted'].includes(props.event.registration?.status ?? '');
}

function register(): void {
  router.post(`/alliance/events/${props.event.id}/registration`);
}

function cancel(): void {
  router.delete(`/alliance/events/${props.event.id}/registration`);
}

function saveFormation(): void {
  formationForm
    .transform((data) => ({
      name: data.name,
      heroes: data.heroes_text
        .split(',')
        .map((hero) => hero.trim())
        .filter(Boolean),
      infantry_percent: Number(data.infantry_percent),
      cavalry_percent: Number(data.cavalry_percent),
      archer_percent: Number(data.archer_percent),
      notes: data.notes || null,
      is_default: data.is_default,
    }))
    .post('/alliance/formations', {
      preserveScroll: true,
      onSuccess: () => formationForm.reset(),
    });
}

function statusLabel(value: string): string {
  const key = `allianceOperations.status.${value === 'no_show' ? 'noShow' : value}`;
  const translated = t(key);
  return translated === key ? value.replaceAll('_', ' ') : translated;
}

function registrationLabel(): string {
  if (!props.event.registration) return t('allianceOperations.events.notRegistered');
  if (
    props.event.registration.status === 'waitlisted' &&
    props.event.registration.waitlistPosition
  ) {
    return t('allianceOperations.events.waitlistedPosition', {
      position: formatNumber(props.event.registration.waitlistPosition),
    });
  }

  return statusLabel(props.event.registration.status);
}

function ratioStyle(value: number): Record<string, string> {
  return { width: `${Math.max(0, Math.min(100, value))}%` };
}
</script>

<template>
  <Head :title="`${event.title} · ${t('navigation.events')}`" />

  <AppLayout :user="user" :alliance-name="alliance.name" :has-active-alliance="true">
    <header class="flex flex-wrap items-center justify-between gap-4">
      <Link
        class="inline-flex min-h-10 items-center text-sm font-semibold text-[var(--ks-blue-strong)] transition hover:text-white"
        href="/alliance/events"
      >
        ← {{ t('navigation.events') }}
      </Link>
      <Link
        v-if="canManage"
        class="inline-flex min-h-11 items-center justify-center rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold text-[var(--ks-text-secondary)] transition hover:border-[var(--ks-border-strong)] hover:text-white"
        href="/alliance/events/manage"
      >
        {{ t('allianceOperations.events.coordinate') }}
      </Link>
    </header>

    <section class="ks-surface mt-5 overflow-hidden p-5 sm:p-7">
      <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-start">
        <div class="min-w-0">
          <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
            {{ t('allianceOperations.events.eyebrow') }}
          </p>
          <div class="mt-2 flex flex-wrap items-center gap-3">
            <h1 class="ks-display text-3xl font-bold sm:text-4xl">{{ event.title }}</h1>
            <span
              class="rounded-full border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-3 py-1 text-xs font-semibold text-[var(--ks-text-secondary)]"
            >
              {{ registrationLabel() }}
            </span>
          </div>

          <dl class="mt-6 grid gap-3 sm:grid-cols-2">
            <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-bg)]/35 p-4">
              <dt class="text-xs font-bold tracking-[0.1em] text-[var(--ks-text-muted)] uppercase">
                {{ t('allianceOperations.events.yourTime', { zone: userTimezone }) }}
              </dt>
              <dd class="mt-2 font-semibold">{{ formatInZone(event.startsAt, userTimezone) }}</dd>
            </div>
            <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-bg)]/35 p-4">
              <dt class="text-xs font-bold tracking-[0.1em] text-[var(--ks-text-muted)] uppercase">
                {{ t('allianceOperations.events.allianceTime', { zone: event.allianceTimezone }) }}
              </dt>
              <dd class="mt-2 font-semibold">
                {{ formatInZone(event.startsAt, event.allianceTimezone) }}
              </dd>
            </div>
          </dl>

          <div v-if="event.instructions" class="mt-6 border-t border-[var(--ks-border)] pt-5">
            <h2 class="ks-display text-lg font-semibold">{{ t('eventDetail.instructions') }}</h2>
            <p class="mt-2 text-sm leading-6 whitespace-pre-wrap text-[var(--ks-text-secondary)]">
              {{ event.instructions }}
            </p>
          </div>
        </div>

        <aside class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-bg)]/45 p-5">
          <div class="flex flex-wrap gap-2">
            <button
              v-if="canJoin()"
              class="min-h-11 flex-1 rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--ks-blue-strong)]"
              type="button"
              @click="register"
            >
              {{ t('allianceOperations.events.join') }}
            </button>
            <button
              v-if="canCancel()"
              class="min-h-11 flex-1 rounded-[var(--ks-radius-sm)] border border-red-400/30 bg-red-500/10 px-4 py-2 text-sm font-semibold text-red-200 transition hover:bg-red-500/20"
              type="button"
              @click="cancel"
            >
              {{ t('allianceOperations.events.cancelRegistration') }}
            </button>
          </div>

          <dl class="mt-5 grid grid-cols-2 gap-3">
            <div class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] p-3">
              <dt class="text-xs text-[var(--ks-text-muted)]">{{ t('eventDetail.registeredCount', { count: '' }) }}</dt>
              <dd class="ks-display mt-1 text-2xl font-semibold">{{ formatNumber(event.registeredCount) }}</dd>
            </div>
            <div class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] p-3">
              <dt class="text-xs text-[var(--ks-text-muted)]">{{ t('eventDetail.waitlistedCount', { count: '' }) }}</dt>
              <dd class="ks-display mt-1 text-2xl font-semibold">{{ formatNumber(event.waitlistedCount) }}</dd>
            </div>
          </dl>

          <div class="mt-3 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] p-3">
            <p class="text-xs font-bold tracking-[0.1em] text-[var(--ks-text-muted)] uppercase">
              {{ t('allianceOperations.events.capacity', { capacity: '' }) }}
            </p>
            <p class="mt-1 font-semibold">
              {{ event.capacity === null ? t('eventDetail.noLimit') : formatNumber(event.capacity) }}
            </p>
          </div>
        </aside>
      </div>
    </section>

    <section class="mt-9" aria-labelledby="recommended-formations-heading">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <p class="text-xs font-bold tracking-[0.18em] text-[var(--ks-gold)] uppercase">
            {{ t('eventDetail.troopRatio') }}
          </p>
          <h2 id="recommended-formations-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('eventDetail.recommendedFormations') }}
          </h2>
        </div>
        <span v-if="recommendedFormations.length" class="text-sm text-[var(--ks-text-muted)]">
          {{ formatNumber(recommendedFormations.length) }}
        </span>
      </div>

      <div v-if="recommendedFormations.length" class="mt-5 grid gap-4 xl:grid-cols-2">
        <article
          v-for="formation in recommendedFormations"
          :key="formation.id"
          class="ks-surface p-5 sm:p-6"
        >
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h3 class="text-lg font-semibold">{{ formation.name }}</h3>
              <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
                {{ t('eventDetail.assignmentRole') }} · {{ formation.assignmentRole.replaceAll('_', ' ') }}
              </p>
            </div>
            <span class="rounded-full border border-purple-400/25 bg-purple-500/10 px-2.5 py-1 text-xs font-semibold text-purple-200">
              {{ formation.infantryPercent }}/{{ formation.cavalryPercent }}/{{ formation.archerPercent }}
            </span>
          </div>

          <div class="mt-5 grid gap-3 sm:grid-cols-3">
            <div class="rounded-[var(--ks-radius-sm)] border border-red-400/20 bg-red-500/5 p-3">
              <div class="flex items-center justify-between gap-2 text-xs">
                <span class="font-semibold text-red-200">{{ t('eventDetail.infantry') }}</span>
                <span>{{ formatNumber(formation.infantryPercent) }}%</span>
              </div>
              <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-black/30">
                <div class="h-full rounded-full bg-red-400" :style="ratioStyle(formation.infantryPercent)" />
              </div>
            </div>
            <div class="rounded-[var(--ks-radius-sm)] border border-amber-400/20 bg-amber-500/5 p-3">
              <div class="flex items-center justify-between gap-2 text-xs">
                <span class="font-semibold text-amber-200">{{ t('eventDetail.cavalry') }}</span>
                <span>{{ formatNumber(formation.cavalryPercent) }}%</span>
              </div>
              <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-black/30">
                <div class="h-full rounded-full bg-amber-400" :style="ratioStyle(formation.cavalryPercent)" />
              </div>
            </div>
            <div class="rounded-[var(--ks-radius-sm)] border border-green-400/20 bg-green-500/5 p-3">
              <div class="flex items-center justify-between gap-2 text-xs">
                <span class="font-semibold text-green-200">{{ t('eventDetail.archers') }}</span>
                <span>{{ formatNumber(formation.archerPercent) }}%</span>
              </div>
              <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-black/30">
                <div class="h-full rounded-full bg-green-400" :style="ratioStyle(formation.archerPercent)" />
              </div>
            </div>
          </div>

          <div v-if="formation.heroes.length" class="mt-4">
            <p class="text-xs font-bold tracking-[0.1em] text-[var(--ks-text-muted)] uppercase">
              {{ t('eventDetail.heroes') }}
            </p>
            <div class="mt-2 flex flex-wrap gap-2">
              <span
                v-for="hero in formation.heroes"
                :key="hero"
                class="rounded-full border border-[var(--ks-border)] bg-[var(--ks-surface-2)] px-3 py-1 text-sm text-[var(--ks-text-secondary)]"
              >
                {{ hero }}
              </span>
            </div>
          </div>

          <p v-if="formation.notes" class="mt-4 text-sm leading-6 text-[var(--ks-text-secondary)]">
            {{ formation.notes }}
          </p>

          <div
            v-if="formation.guidance"
            class="mt-5 rounded-[var(--ks-radius-md)] border border-blue-400/20 bg-[var(--ks-blue-soft)] p-4"
          >
            <p class="text-xs font-bold tracking-[0.1em] text-[var(--ks-blue-strong)] uppercase">
              {{ t('eventDetail.guidance') }}
            </p>
            <p class="mt-1 font-semibold">{{ formation.guidance.name }}</p>
            <p v-if="formation.guidance.rationale" class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
              {{ formation.guidance.rationale }}
            </p>
            <p v-if="formation.guidance.source" class="mt-3 text-xs text-[var(--ks-text-muted)]">
              {{ t('eventDetail.source') }}: {{ formation.guidance.source }}
            </p>
            <p v-if="formation.guidance.effectiveFrom" class="mt-1 text-xs text-[var(--ks-text-muted)]">
              {{
                t('eventDetail.effective', {
                  date: formatGuidanceDate(formation.guidance.effectiveFrom),
                })
              }}<template v-if="formation.guidance.effectiveUntil">
                {{
                  t('eventDetail.through', {
                    date: formatGuidanceDate(formation.guidance.effectiveUntil),
                  })
                }}
              </template>
            </p>
          </div>
        </article>
      </div>

      <div v-else class="ks-surface mt-5 border-dashed p-8 text-center">
        <p class="text-sm text-[var(--ks-text-muted)]">
          {{ t('eventDetail.noRecommendedFormations') }}
        </p>
      </div>
    </section>

    <section class="mt-9" aria-labelledby="rally-groups-heading">
      <div class="flex items-center justify-between gap-4">
        <h2 id="rally-groups-heading" class="ks-display text-2xl font-semibold">
          {{ t('eventDetail.rallyGroups') }}
        </h2>
        <span v-if="rallyGroups.length" class="text-sm text-[var(--ks-text-muted)]">
          {{ formatNumber(rallyGroups.length) }}
        </span>
      </div>

      <div v-if="rallyGroups.length" class="mt-5 grid gap-4 xl:grid-cols-2">
        <article v-for="group in rallyGroups" :key="group.id" class="ks-surface p-5 sm:p-6">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h3 class="text-lg font-semibold">{{ group.name }}</h3>
              <p v-if="group.notes" class="mt-1 text-sm text-[var(--ks-text-muted)]">{{ group.notes }}</p>
            </div>
            <span v-if="group.maxJoiners" class="rounded-full border border-[var(--ks-border)] px-2.5 py-1 text-xs text-[var(--ks-text-muted)]">
              {{ t('eventDetail.maxJoiners', { count: formatNumber(group.maxJoiners) }) }}
            </span>
          </div>

          <ul v-if="group.assignments.length" class="mt-4 divide-y divide-[var(--ks-border)]">
            <li
              v-for="assignment in group.assignments"
              :key="assignment.id"
              class="grid gap-2 py-3 text-sm sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"
            >
              <span class="font-semibold">{{ assignment.memberName }}</span>
              <span class="text-[var(--ks-text-secondary)]">
                {{ assignment.role.replaceAll('_', ' ') }}<span v-if="assignment.slotNumber">
                  #{{ formatNumber(assignment.slotNumber) }}</span
                >
                · {{ statusLabel(assignment.status) }}
              </span>
            </li>
          </ul>
          <p v-else class="mt-4 text-sm text-[var(--ks-text-muted)]">{{ t('eventDetail.noAssigned') }}</p>
        </article>
      </div>

      <div v-else class="ks-surface mt-5 border-dashed p-8 text-center">
        <p class="text-sm text-[var(--ks-text-muted)]">{{ t('eventDetail.noRallyGroups') }}</p>
      </div>
    </section>

    <section class="mt-9 grid gap-6 xl:grid-cols-[minmax(0,1fr)_26rem]" aria-labelledby="saved-formations-heading">
      <div class="ks-surface p-5 sm:p-6">
        <div class="flex items-center justify-between gap-4">
          <h2 id="saved-formations-heading" class="ks-display text-xl font-semibold">
            {{ t('eventDetail.savedFormations') }}
          </h2>
          <span v-if="savedFormations.length" class="text-sm text-[var(--ks-text-muted)]">
            {{ formatNumber(savedFormations.length) }}
          </span>
        </div>

        <div v-if="savedFormations.length" class="mt-5 grid gap-3 md:grid-cols-2">
          <article
            v-for="formation in savedFormations"
            :key="formation.id"
            class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-bg)]/35 p-4"
          >
            <div class="flex flex-wrap items-center justify-between gap-2">
              <h3 class="font-semibold">{{ formation.name }}</h3>
              <span v-if="formation.isDefault" class="text-xs font-semibold text-[var(--ks-gold)]">
                {{ t('eventDetail.default') }}
              </span>
            </div>
            <p class="mt-3 font-mono text-sm text-[var(--ks-text-secondary)]">
              {{ formation.infantryPercent }} / {{ formation.cavalryPercent }} /
              {{ formation.archerPercent }}
            </p>
            <div v-if="formation.heroes.length" class="mt-3 flex flex-wrap gap-1.5">
              <span
                v-for="hero in formation.heroes"
                :key="hero"
                class="rounded-full border border-[var(--ks-border)] px-2.5 py-1 text-xs text-[var(--ks-text-muted)]"
              >
                {{ hero }}
              </span>
            </div>
            <p v-if="formation.notes" class="mt-3 text-sm leading-6 text-[var(--ks-text-muted)]">
              {{ formation.notes }}
            </p>
          </article>
        </div>
        <p v-else class="mt-4 text-sm text-[var(--ks-text-muted)]">{{ t('eventDetail.noSavedFormations') }}</p>
      </div>

      <form class="ks-surface p-5 sm:p-6" @submit.prevent="saveFormation">
        <h2 class="ks-display text-xl font-semibold">{{ t('eventDetail.saveFormation') }}</h2>
        <div class="mt-5 space-y-4">
          <div>
            <label class="text-sm font-medium" for="formation-name">{{ t('eventDetail.formationName') }}</label>
            <input
              id="formation-name"
              v-model="formationForm.name"
              class="mt-1 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5"
              required
              maxlength="100"
            />
          </div>
          <div>
            <label class="text-sm font-medium" for="formation-heroes">{{ t('eventDetail.heroes') }}</label>
            <input
              id="formation-heroes"
              v-model="formationForm.heroes_text"
              class="mt-1 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5"
              :placeholder="t('eventDetail.heroesHint')"
            />
          </div>
          <fieldset>
            <legend class="text-sm font-medium">{{ t('eventDetail.troopRatio') }}</legend>
            <div class="mt-2 grid grid-cols-3 gap-2">
              <label class="text-xs text-[var(--ks-text-muted)]">
                {{ t('eventDetail.infantry') }} %
                <input
                  v-model="formationForm.infantry_percent"
                  class="mt-1 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-2 py-2 text-white"
                  max="100"
                  min="0"
                  type="number"
                />
              </label>
              <label class="text-xs text-[var(--ks-text-muted)]">
                {{ t('eventDetail.cavalry') }} %
                <input
                  v-model="formationForm.cavalry_percent"
                  class="mt-1 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-2 py-2 text-white"
                  max="100"
                  min="0"
                  type="number"
                />
              </label>
              <label class="text-xs text-[var(--ks-text-muted)]">
                {{ t('eventDetail.archers') }} %
                <input
                  v-model="formationForm.archer_percent"
                  class="mt-1 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-2 py-2 text-white"
                  max="100"
                  min="0"
                  type="number"
                />
              </label>
            </div>
          </fieldset>
          <div>
            <label class="text-sm font-medium" for="formation-notes">{{ t('eventDetail.notes') }}</label>
            <textarea
              id="formation-notes"
              v-model="formationForm.notes"
              class="mt-1 min-h-24 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5"
              maxlength="2000"
            />
          </div>
          <label class="flex items-center gap-2 text-sm text-[var(--ks-text-secondary)]">
            <input v-model="formationForm.is_default" type="checkbox" />
            {{ t('eventDetail.makeDefault') }}
          </label>
          <p v-if="Object.keys(formationForm.errors).length" class="text-sm text-red-300" role="alert">
            {{ t('eventDetail.formationValidation') }}
          </p>
          <button
            class="min-h-11 w-full rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--ks-blue-strong)] disabled:opacity-60"
            :disabled="formationForm.processing"
            type="submit"
          >
            {{ t('eventDetail.save') }}
          </button>
        </div>
      </form>
    </section>
  </AppLayout>
</template>
