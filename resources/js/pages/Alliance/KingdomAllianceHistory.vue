<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Observation = {
  id?: string;
  observedName: string;
  observedTag: string | null;
  power: string | null;
  memberCount: number | null;
  capturedAt: string;
  source: string;
  actorName?: string | null;
  correctsObservationId?: string | null;
  invalidatedAt?: string | null;
  invalidatedByName?: string | null;
  invalidationReason?: string | null;
};

const props = defineProps<{
  alliance: {
    id: string;
    name: string;
    kingdom: string | null;
  };
  canManage: boolean;
  tracking: {
    id?: string;
    name: string;
    tag: string | null;
    state: string;
    kingdom: string;
    contextCurrent: boolean;
  };
  freshness: 'current' | 'stale' | 'missing';
  freshDays: number;
  latest: Observation | null;
  history: Observation[];
}>();

function localDateTimeNow(): string {
  const now = new Date();
  return new Date(now.getTime() - now.getTimezoneOffset() * 60_000).toISOString().slice(0, 16);
}

const recordForm = useForm({
  observed_name: props.tracking.name,
  observed_tag: props.tracking.tag ?? '',
  power: '',
  member_count: '',
  captured_at: localDateTimeNow(),
  corrects_observation_id: '',
  correction_reason: '',
});

const invalidateTargetId = ref<string | null>(null);
const invalidateForm = useForm({ reason: '' });

const recordDomainError = computed(
  () => (recordForm.errors as Record<string, string | undefined>).observation,
);

const canMutate = computed(
  () => props.canManage && props.tracking.state === 'active' && props.tracking.contextCurrent,
);

function formatDate(value: string): string {
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value));
}

function recordObservation(): void {
  if (!props.tracking.id) return;

  recordForm.post(`/alliance/kingdom-alliances/${props.tracking.id}/observations`, {
    preserveScroll: true,
    onSuccess: () => {
      recordForm.power = '';
      recordForm.member_count = '';
      recordForm.captured_at = localDateTimeNow();
      recordForm.corrects_observation_id = '';
      recordForm.correction_reason = '';
    },
  });
}

function beginCorrection(observation: Observation): void {
  if (!observation.id) return;

  recordForm.clearErrors();
  recordForm.observed_name = observation.observedName;
  recordForm.observed_tag = observation.observedTag ?? '';
  recordForm.power = observation.power ?? '';
  recordForm.member_count = observation.memberCount === null ? '' : String(observation.memberCount);
  recordForm.captured_at = localDateTimeNow();
  recordForm.corrects_observation_id = observation.id;
  recordForm.correction_reason = '';
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cancelCorrection(): void {
  recordForm.corrects_observation_id = '';
  recordForm.correction_reason = '';
}

function beginInvalidation(observation: Observation): void {
  if (!observation.id) return;
  invalidateTargetId.value = observation.id;
  invalidateForm.reset();
  invalidateForm.clearErrors();
}

function cancelInvalidation(): void {
  invalidateTargetId.value = null;
  invalidateForm.reset();
  invalidateForm.clearErrors();
}

function invalidateObservation(): void {
  if (!props.tracking.id || !invalidateTargetId.value) return;

  invalidateForm.post(
    `/alliance/kingdom-alliances/${props.tracking.id}/observations/${invalidateTargetId.value}/invalidate`,
    {
      preserveScroll: true,
      onSuccess: () => cancelInvalidation(),
    },
  );
}
</script>

<template>
  <Head :title="`${tracking.name} observation history`" />

  <main class="mx-auto min-h-screen max-w-6xl px-6 py-12 lg:px-8">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="text-sm font-semibold tracking-[0.2em] text-cyan-300 uppercase">
          Kingdom intelligence
        </p>
        <h1 class="mt-2 text-3xl font-bold">{{ tracking.name }} observation history</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-400">
          Kingdom {{ tracking.kingdom }} · {{ tracking.tag ?? 'no tag recorded' }}. Observations are
          factual historical records and do not imply threat, ranking, or diplomacy state.
        </p>
      </div>
      <Link
        class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200"
        href="/alliance/kingdom-alliances"
      >
        Back to tracked alliances
      </Link>
    </header>

    <section class="mt-8 grid gap-4 md:grid-cols-3">
      <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
        <p class="text-xs font-semibold tracking-wide text-slate-400 uppercase">Freshness</p>
        <p class="mt-2 text-xl font-semibold">{{ freshness }}</p>
        <p class="mt-1 text-xs text-slate-500">Current means captured within {{ freshDays }} days.</p>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
        <p class="text-xs font-semibold tracking-wide text-slate-400 uppercase">Latest power</p>
        <p class="mt-2 text-xl font-semibold">{{ latest?.power ?? 'missing' }}</p>
        <p class="mt-1 text-xs text-slate-500">Missing is distinct from zero.</p>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
        <p class="text-xs font-semibold tracking-wide text-slate-400 uppercase">Latest members</p>
        <p class="mt-2 text-xl font-semibold">{{ latest?.memberCount ?? 'missing' }}</p>
        <p v-if="latest" class="mt-1 text-xs text-slate-500">
          Captured {{ formatDate(latest.capturedAt) }}
        </p>
      </div>
    </section>

    <section
      v-if="canManage"
      class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-6"
    >
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="text-xl font-semibold">
            {{ recordForm.corrects_observation_id ? 'Record correction' : 'Record observation' }}
          </h2>
          <p class="mt-1 text-sm text-slate-400">
            Exact retries are idempotent. A correction appends a new row and invalidates the
            original; it never rewrites history.
          </p>
        </div>
        <button
          v-if="recordForm.corrects_observation_id"
          class="rounded-lg border border-slate-700 px-3 py-2 text-sm font-semibold"
          type="button"
          @click="cancelCorrection"
        >
          Cancel correction
        </button>
      </div>

      <p
        v-if="!tracking.contextCurrent"
        class="mt-4 rounded-xl border border-amber-900 bg-amber-950/40 p-4 text-sm text-amber-200"
      >
        This tracking record belongs to historical Kingdom context. Observation mutations are
        blocked; history remains readable.
      </p>

      <form class="mt-6 grid gap-5 md:grid-cols-2" @submit.prevent="recordObservation">
        <div>
          <label class="block text-sm font-medium" for="observation-name">Observed name</label>
          <input
            id="observation-name"
            v-model="recordForm.observed_name"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            maxlength="160"
            required
            type="text"
          />
          <p v-if="recordForm.errors.observed_name" class="mt-1 text-sm text-rose-300">
            {{ recordForm.errors.observed_name }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="observation-tag">Observed tag</label>
          <input
            id="observation-tag"
            v-model="recordForm.observed_tag"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            maxlength="32"
            type="text"
          />
        </div>

        <div>
          <label class="block text-sm font-medium" for="observation-power">Power</label>
          <input
            id="observation-power"
            v-model="recordForm.power"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            inputmode="numeric"
            pattern="[0-9]*"
            placeholder="Leave blank if unknown"
            type="text"
          />
          <p v-if="recordForm.errors.power" class="mt-1 text-sm text-rose-300">
            {{ recordForm.errors.power }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="observation-members">Member count</label>
          <input
            id="observation-members"
            v-model="recordForm.member_count"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            min="0"
            placeholder="Leave blank if unknown"
            type="number"
          />
          <p v-if="recordForm.errors.member_count" class="mt-1 text-sm text-rose-300">
            {{ recordForm.errors.member_count }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="observation-captured">Captured at</label>
          <input
            id="observation-captured"
            v-model="recordForm.captured_at"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            required
            type="datetime-local"
          />
          <p v-if="recordForm.errors.captured_at" class="mt-1 text-sm text-rose-300">
            {{ recordForm.errors.captured_at }}
          </p>
        </div>

        <div v-if="recordForm.corrects_observation_id">
          <label class="block text-sm font-medium" for="correction-reason">
            Correction reason
          </label>
          <textarea
            id="correction-reason"
            v-model="recordForm.correction_reason"
            class="mt-2 min-h-24 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            maxlength="5000"
            placeholder="Manager-private context"
          />
          <p class="mt-1 text-xs text-slate-500">
            Private management detail; excluded from member payloads and event metadata.
          </p>
        </div>

        <div class="md:col-span-2">
          <p v-if="recordDomainError" class="mb-3 text-sm text-rose-300">
            {{ recordDomainError }}
          </p>
          <button
            class="rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
            :disabled="recordForm.processing || !canMutate"
            type="submit"
          >
            {{ recordForm.corrects_observation_id ? 'Record correction' : 'Record observation' }}
          </button>
        </div>
      </form>
    </section>

    <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <div>
        <h2 class="text-xl font-semibold">Historical observations</h2>
        <p class="mt-1 text-sm text-slate-400">
          Up to the latest 250 records are shown, ordered by capture time. Managers also see
          invalidated records and provenance.
        </p>
      </div>

      <div v-if="history.length" class="mt-6 overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-800 text-left text-sm">
          <thead class="text-xs tracking-wide text-slate-400 uppercase">
            <tr>
              <th class="px-3 py-3 font-semibold">Captured</th>
              <th class="px-3 py-3 font-semibold">Observed identity</th>
              <th class="px-3 py-3 font-semibold">Power</th>
              <th class="px-3 py-3 font-semibold">Members</th>
              <th v-if="canManage" class="px-3 py-3 font-semibold">Provenance</th>
              <th v-if="canManage" class="px-3 py-3 font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-for="observation in history" :key="observation.id ?? observation.capturedAt">
              <td class="px-3 py-4 text-slate-300">
                {{ formatDate(observation.capturedAt) }}
                <span
                  v-if="observation.invalidatedAt"
                  class="ml-2 rounded-full bg-rose-950 px-2 py-1 text-xs font-semibold text-rose-300"
                >
                  Invalidated
                </span>
              </td>
              <td class="px-3 py-4">
                <p class="font-medium text-slate-100">{{ observation.observedName }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ observation.observedTag ?? 'No tag' }}</p>
              </td>
              <td class="px-3 py-4 text-slate-300">{{ observation.power ?? 'missing' }}</td>
              <td class="px-3 py-4 text-slate-300">{{ observation.memberCount ?? 'missing' }}</td>
              <td v-if="canManage" class="px-3 py-4 text-slate-300">
                <p>{{ observation.actorName ?? 'Unavailable actor' }} · {{ observation.source }}</p>
                <p v-if="observation.correctsObservationId" class="mt-1 text-xs text-slate-500">
                  Correction of {{ observation.correctsObservationId }}
                </p>
                <p v-if="observation.invalidatedAt" class="mt-1 text-xs text-slate-500">
                  Invalidated by {{ observation.invalidatedByName ?? 'unavailable actor' }} on
                  {{ formatDate(observation.invalidatedAt) }}
                </p>
                <p v-if="observation.invalidationReason" class="mt-1 text-xs text-slate-500">
                  {{ observation.invalidationReason }}
                </p>
              </td>
              <td v-if="canManage" class="px-3 py-4">
                <div v-if="!observation.invalidatedAt && canMutate" class="flex flex-wrap gap-2">
                  <button
                    class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-semibold"
                    type="button"
                    @click="beginCorrection(observation)"
                  >
                    Correct
                  </button>
                  <button
                    class="rounded-lg border border-rose-900 px-3 py-2 text-xs font-semibold text-rose-300"
                    type="button"
                    @click="beginInvalidation(observation)"
                  >
                    Invalidate
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p
        v-else
        class="mt-6 rounded-xl border border-dashed border-slate-700 p-5 text-sm text-slate-400"
      >
        No accepted observations have been recorded yet.
      </p>
    </section>

    <section
      v-if="canManage && invalidateTargetId"
      class="mt-8 rounded-2xl border border-rose-900 bg-slate-900/70 p-6"
    >
      <h2 class="text-xl font-semibold">Invalidate observation</h2>
      <p class="mt-1 text-sm text-slate-400">
        The original row remains historical and is excluded from latest/freshness projections.
      </p>
      <form class="mt-5" @submit.prevent="invalidateObservation">
        <label class="block text-sm font-medium" for="invalidation-reason">Reason</label>
        <textarea
          id="invalidation-reason"
          v-model="invalidateForm.reason"
          class="mt-2 min-h-24 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          maxlength="5000"
          required
        />
        <p v-if="invalidateForm.errors.reason" class="mt-1 text-sm text-rose-300">
          {{ invalidateForm.errors.reason }}
        </p>
        <div class="mt-4 flex flex-wrap gap-3">
          <button
            class="rounded-lg bg-rose-300 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
            :disabled="invalidateForm.processing || !canMutate"
            type="submit"
          >
            Confirm invalidation
          </button>
          <button
            class="rounded-lg border border-slate-700 px-4 py-2 font-semibold"
            type="button"
            @click="cancelInvalidation"
          >
            Cancel
          </button>
        </div>
      </form>
    </section>
  </main>
</template>
