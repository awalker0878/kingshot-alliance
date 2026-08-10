<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

type CurrentDiplomacy = {
  exists: boolean;
  state: string;
  effectiveAt: string | null;
  reviewAt: string | null;
  expiresAt: string | null;
  needsReview: boolean;
  terms: string | null;
  rationale: string | null;
  lastActorName: string | null;
};

type DiplomacyTransition = {
  id: string;
  fromState: string;
  toState: string;
  effectiveAt: string;
  reviewAt: string | null;
  expiresAt: string | null;
  terms: string | null;
  rationale: string | null;
  actorName: string | null;
  recordedAt: string;
};

const props = defineProps<{
  alliance: {
    id: string;
    name: string;
    kingdom: string | null;
  };
  tracking: {
    id: string;
    name: string;
    tag: string | null;
    state: string;
    kingdom: string;
    contextCurrent: boolean;
  };
  states: string[];
  current: CurrentDiplomacy;
  historyLimit: number;
  history: DiplomacyTransition[];
}>();

function toLocalInput(value: string | null, fallbackNow = false): string {
  if (value === null && !fallbackNow) return '';

  const date = value === null ? new Date() : new Date(value);
  const local = new Date(date.getTime() - date.getTimezoneOffset() * 60_000);
  return local.toISOString().slice(0, 16);
}

function toIso(value: string): string {
  return new Date(value).toISOString();
}

function toNullableIso(value: string): string | null {
  return value.trim() === '' ? null : toIso(value);
}

function stateLabel(value: string): string {
  if (value === 'nap') return 'NAP';
  return value.charAt(0).toUpperCase() + value.slice(1).replaceAll('_', ' ');
}

function formatDate(value: string | null): string {
  if (value === null) return 'Not set';
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value));
}

const form = useForm({
  state: props.current.state,
  effective_at: toLocalInput(props.current.effectiveAt, true),
  review_at: toLocalInput(props.current.reviewAt),
  expires_at: toLocalInput(props.current.expiresAt),
  terms: props.current.terms ?? '',
  rationale: props.current.rationale ?? '',
});

function diplomacyError(): string | undefined {
  return (form.errors as Record<string, string | undefined>).diplomacy;
}

function submitTransition(): void {
  form
    .transform((data) => ({
      ...data,
      effective_at: toIso(data.effective_at),
      review_at: toNullableIso(data.review_at),
      expires_at: toNullableIso(data.expires_at),
      terms: data.terms.trim() === '' ? null : data.terms,
      rationale: data.rationale.trim() === '' ? null : data.rationale,
    }))
    .post(`/alliance/kingdom-alliances/${props.tracking.id}/diplomacy/transitions`, {
      preserveScroll: true,
    });
}
</script>

<template>
  <Head :title="`Diplomacy · ${tracking.name}`" />

  <main class="mx-auto min-h-screen max-w-6xl px-6 py-12 lg:px-8">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="text-sm font-semibold tracking-[0.2em] text-cyan-300 uppercase">
          Kingdom diplomacy
        </p>
        <h1 class="mt-2 text-3xl font-bold">{{ tracking.name }} diplomacy</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-400">
          {{ alliance.name }} · Kingdom {{ tracking.kingdom }}. Diplomacy is explicitly maintained
          by authorized managers. Observations, power changes, review dates, and expiry dates never
          change relationship state automatically.
        </p>
      </div>
      <div class="flex flex-wrap gap-3">
        <Link
          class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200"
          :href="`/alliance/kingdom-alliances/${tracking.id}/history`"
        >
          Observation history
        </Link>
        <Link
          class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200"
          href="/alliance/kingdom-alliances/manage"
        >
          Tracking workspace
        </Link>
      </div>
    </header>

    <section class="mt-10 grid gap-6 lg:grid-cols-3">
      <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 lg:col-span-1">
        <h2 class="text-xl font-semibold">Current relationship</h2>
        <dl class="mt-5 space-y-4 text-sm">
          <div>
            <dt class="text-slate-500">State</dt>
            <dd class="mt-1 font-semibold text-slate-100">{{ stateLabel(current.state) }}</dd>
          </div>
          <div>
            <dt class="text-slate-500">Effective</dt>
            <dd class="mt-1 text-slate-300">{{ formatDate(current.effectiveAt) }}</dd>
          </div>
          <div>
            <dt class="text-slate-500">Review</dt>
            <dd class="mt-1 text-slate-300">{{ formatDate(current.reviewAt) }}</dd>
          </div>
          <div>
            <dt class="text-slate-500">Expiry</dt>
            <dd class="mt-1 text-slate-300">{{ formatDate(current.expiresAt) }}</dd>
          </div>
          <div>
            <dt class="text-slate-500">Review status</dt>
            <dd class="mt-1">
              <span
                v-if="current.needsReview"
                class="rounded-full bg-amber-950 px-2 py-1 text-xs font-semibold text-amber-300"
              >
                Human review due
              </span>
              <span v-else class="text-slate-300">No review due</span>
            </dd>
          </div>
          <div>
            <dt class="text-slate-500">Last changed by</dt>
            <dd class="mt-1 text-slate-300">
              {{ current.lastActorName ?? 'No explicit transition yet' }}
            </dd>
          </div>
        </dl>
      </div>

      <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 lg:col-span-2">
        <h2 class="text-xl font-semibold">Record explicit relationship state</h2>
        <p class="mt-1 text-sm text-slate-400">
          Repeating the exact current state and metadata is idempotent. Changing state or metadata
          appends a new transition and preserves the prior record.
        </p>

        <div
          v-if="tracking.state !== 'active' || !tracking.contextCurrent"
          class="mt-5 rounded-xl border border-amber-900 bg-amber-950/30 p-4 text-sm text-amber-200"
        >
          This tracking record is read-only because it is archived or belongs to historical Kingdom
          context. Existing diplomacy history remains available below.
        </div>

        <form class="mt-6 grid gap-5 md:grid-cols-2" @submit.prevent="submitTransition">
          <div>
            <label class="block text-sm font-medium" for="diplomacy-state"
              >Relationship state</label
            >
            <select
              id="diplomacy-state"
              v-model="form.state"
              class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              :disabled="tracking.state !== 'active' || !tracking.contextCurrent"
            >
              <option v-for="state in states" :key="state" :value="state">
                {{ stateLabel(state) }}
              </option>
            </select>
            <p v-if="form.errors.state" class="mt-1 text-sm text-rose-300">
              {{ form.errors.state }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium" for="diplomacy-effective"
              >Effective time</label
            >
            <input
              id="diplomacy-effective"
              v-model="form.effective_at"
              class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              :disabled="tracking.state !== 'active' || !tracking.contextCurrent"
              required
              type="datetime-local"
            />
            <p v-if="form.errors.effective_at" class="mt-1 text-sm text-rose-300">
              {{ form.errors.effective_at }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium" for="diplomacy-review">Review time</label>
            <input
              id="diplomacy-review"
              v-model="form.review_at"
              class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              :disabled="tracking.state !== 'active' || !tracking.contextCurrent"
              type="datetime-local"
            />
            <p class="mt-1 text-xs text-slate-500">
              Advisory only; reaching this time never changes state.
            </p>
            <p v-if="form.errors.review_at" class="mt-1 text-sm text-rose-300">
              {{ form.errors.review_at }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium" for="diplomacy-expiry">Expiry time</label>
            <input
              id="diplomacy-expiry"
              v-model="form.expires_at"
              class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              :disabled="tracking.state !== 'active' || !tracking.contextCurrent"
              type="datetime-local"
            />
            <p class="mt-1 text-xs text-slate-500">
              Advisory only; expiry creates a review indicator.
            </p>
            <p v-if="form.errors.expires_at" class="mt-1 text-sm text-rose-300">
              {{ form.errors.expires_at }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium" for="diplomacy-terms">Private terms</label>
            <textarea
              id="diplomacy-terms"
              v-model="form.terms"
              class="mt-2 min-h-28 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              :disabled="tracking.state !== 'active' || !tracking.contextCurrent"
              maxlength="5000"
            />
            <p class="mt-1 text-xs text-slate-500">
              Manager-private; excluded from audit/outbox payloads.
            </p>
            <p v-if="form.errors.terms" class="mt-1 text-sm text-rose-300">
              {{ form.errors.terms }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium" for="diplomacy-rationale"
              >Private rationale</label
            >
            <textarea
              id="diplomacy-rationale"
              v-model="form.rationale"
              class="mt-2 min-h-28 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
              :disabled="tracking.state !== 'active' || !tracking.contextCurrent"
              maxlength="5000"
            />
            <p class="mt-1 text-xs text-slate-500">
              Manager-private explanation for the explicit decision.
            </p>
            <p v-if="form.errors.rationale" class="mt-1 text-sm text-rose-300">
              {{ form.errors.rationale }}
            </p>
          </div>

          <div class="md:col-span-2">
            <p v-if="diplomacyError()" class="mb-3 text-sm text-rose-300">
              {{ diplomacyError() }}
            </p>
            <button
              class="rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
              :disabled="form.processing || tracking.state !== 'active' || !tracking.contextCurrent"
              type="submit"
            >
              Record transition
            </button>
          </div>
        </form>
      </div>
    </section>

    <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 class="text-xl font-semibold">Transition history</h2>
          <p class="mt-1 text-sm text-slate-400">
            Append-oriented manager history. State, dates, terms, rationale, actor, and recorded
            time are preserved for each material change.
          </p>
        </div>
        <p class="text-sm text-slate-500">Up to {{ historyLimit }} most recent transitions</p>
      </div>

      <div v-if="history.length" class="mt-6 overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-800 text-left text-sm">
          <thead class="text-xs tracking-wide text-slate-400 uppercase">
            <tr>
              <th class="px-3 py-3 font-semibold">Transition</th>
              <th class="px-3 py-3 font-semibold">Effective / review</th>
              <th class="px-3 py-3 font-semibold">Private context</th>
              <th class="px-3 py-3 font-semibold">Attribution</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-for="transition in history" :key="transition.id">
              <td class="px-3 py-4 text-slate-200">
                {{ stateLabel(transition.fromState) }} → {{ stateLabel(transition.toState) }}
              </td>
              <td class="px-3 py-4 text-slate-300">
                <p>Effective {{ formatDate(transition.effectiveAt) }}</p>
                <p class="mt-1 text-xs text-slate-500">
                  Review {{ formatDate(transition.reviewAt) }}
                </p>
                <p class="mt-1 text-xs text-slate-500">
                  Expiry {{ formatDate(transition.expiresAt) }}
                </p>
              </td>
              <td class="px-3 py-4 text-slate-300">
                <p class="whitespace-pre-wrap">{{ transition.terms ?? 'No terms recorded' }}</p>
                <p class="mt-2 text-xs whitespace-pre-wrap text-slate-500">
                  {{ transition.rationale ?? 'No rationale recorded' }}
                </p>
              </td>
              <td class="px-3 py-4 text-slate-300">
                <p>{{ transition.actorName ?? 'Former/deleted user' }}</p>
                <p class="mt-1 text-xs text-slate-500">
                  Recorded {{ formatDate(transition.recordedAt) }}
                </p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p
        v-else
        class="mt-6 rounded-xl border border-dashed border-slate-700 p-5 text-sm text-slate-400"
      >
        No explicit diplomacy transitions have been recorded yet. The member-safe default is
        Unknown.
      </p>
    </section>
  </main>
</template>
