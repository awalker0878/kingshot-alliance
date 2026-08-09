<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

type Plan = {
  id: string;
  label: string;
  homeKingdom: string;
  startsOn: string | null;
  endsOn: string | null;
  state: string;
  createdAt: string | null;
};

const props = defineProps<{
  alliance: { id: string; name: string; kingdom: string | null };
  plans: Plan[];
}>();

const createForm = useForm({
  label: '',
  starts_on: '',
  ends_on: '',
});

const transitionForm = useForm<Record<string, string>>({});

function createPlan(): void {
  createForm.post('/alliance/transfers', {
    preserveScroll: true,
    onSuccess: () => createForm.reset(),
  });
}

function transition(plan: Plan, action: 'open' | 'lock' | 'close' | 'cancel'): void {
  if (action === 'cancel' && !window.confirm(`Cancel transfer cycle “${plan.label}”?`)) {
    return;
  }

  transitionForm.post(`/alliance/transfers/${plan.id}/${action}`, {
    preserveScroll: true,
  });
}

function stateLabel(state: string): string {
  return state.charAt(0).toUpperCase() + state.slice(1);
}

function canOpen(plan: Plan): boolean {
  return plan.state === 'draft' && !props.plans.some((candidate) => candidate.state === 'open');
}
</script>

<template>
  <Head :title="`Manage transfers · ${alliance.name}`" />

  <main class="mx-auto min-h-screen max-w-6xl px-6 py-12 text-slate-100 lg:px-8">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <Link
          class="text-sm font-semibold text-cyan-300 hover:text-cyan-200"
          href="/alliance/transfers"
        >
          ← Transfer planning
        </Link>
        <h1 class="mt-3 text-3xl font-bold">Manage transfer cycles</h1>
        <p class="mt-2 text-sm text-slate-400">
          {{ alliance.name }} · Kingdom {{ alliance.kingdom ?? 'not set' }}
        </p>
      </div>
      <Link
        class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold hover:border-slate-500"
        href="/alliance/roster/manage"
      >
        Manage roster
      </Link>
    </header>

    <section class="mt-10 rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <h2 class="text-xl font-semibold">Create transfer cycle</h2>
      <p class="mt-2 text-sm text-slate-400">
        New cycles begin in Draft and capture the alliance's current Kingdom as immutable planning
        context.
      </p>

      <form class="mt-6 grid gap-5 md:grid-cols-3" @submit.prevent="createPlan">
        <div class="md:col-span-3">
          <label class="block text-sm font-medium" for="transfer-label">Cycle label</label>
          <input
            id="transfer-label"
            v-model="createForm.label"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            maxlength="160"
            required
            type="text"
          />
          <p v-if="createForm.errors.label" class="mt-1 text-sm text-rose-300">
            {{ createForm.errors.label }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="transfer-start">Start date</label>
          <input
            id="transfer-start"
            v-model="createForm.starts_on"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            type="date"
          />
          <p v-if="createForm.errors.starts_on" class="mt-1 text-sm text-rose-300">
            {{ createForm.errors.starts_on }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="transfer-end">End date</label>
          <input
            id="transfer-end"
            v-model="createForm.ends_on"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            type="date"
          />
          <p v-if="createForm.errors.ends_on" class="mt-1 text-sm text-rose-300">
            {{ createForm.errors.ends_on }}
          </p>
        </div>

        <div class="flex items-end">
          <button
            class="rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
            :disabled="createForm.processing"
            type="submit"
          >
            Create draft
          </button>
        </div>
      </form>

      <p v-if="createForm.errors.plan" class="mt-4 text-sm text-rose-300" role="alert">
        {{ createForm.errors.plan }}
      </p>
    </section>

    <section class="mt-10">
      <h2 class="text-xl font-semibold">Transfer cycles</h2>
      <p class="mt-2 text-sm text-slate-400">
        The normal lifecycle is Draft → Open → Locked → Closed. Draft, Open, or Locked cycles may be
        cancelled.
      </p>
      <p
        v-if="transitionForm.errors.plan"
        class="mt-3 text-sm text-rose-300"
        role="alert"
      >
        {{ transitionForm.errors.plan }}
      </p>

      <div
        v-if="plans.length"
        class="mt-5 overflow-x-auto rounded-2xl border border-slate-800"
      >
        <table class="min-w-full divide-y divide-slate-800 text-left text-sm">
          <thead class="bg-slate-900/80 text-xs tracking-wide text-slate-400 uppercase">
            <tr>
              <th class="px-4 py-3" scope="col">Cycle</th>
              <th class="px-4 py-3" scope="col">Home kingdom</th>
              <th class="px-4 py-3" scope="col">Dates</th>
              <th class="px-4 py-3" scope="col">State</th>
              <th class="px-4 py-3" scope="col">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800 bg-slate-950/40">
            <tr v-for="plan in plans" :key="plan.id">
              <td class="px-4 py-4 font-semibold">{{ plan.label }}</td>
              <td class="px-4 py-4">{{ plan.homeKingdom }}</td>
              <td class="px-4 py-4 text-slate-300">
                {{ plan.startsOn ?? '—' }} → {{ plan.endsOn ?? '—' }}
              </td>
              <td class="px-4 py-4">{{ stateLabel(plan.state) }}</td>
              <td class="px-4 py-4">
                <div class="flex flex-wrap gap-2">
                  <button
                    v-if="plan.state === 'draft'"
                    class="rounded-lg border border-cyan-800 px-3 py-1.5 font-semibold text-cyan-300 disabled:opacity-50"
                    :disabled="!canOpen(plan)"
                    type="button"
                    @click="transition(plan, 'open')"
                  >
                    Open
                  </button>
                  <button
                    v-if="plan.state === 'open'"
                    class="rounded-lg border border-amber-800 px-3 py-1.5 font-semibold text-amber-300"
                    type="button"
                    @click="transition(plan, 'lock')"
                  >
                    Lock
                  </button>
                  <button
                    v-if="plan.state === 'locked'"
                    class="rounded-lg border border-emerald-800 px-3 py-1.5 font-semibold text-emerald-300"
                    type="button"
                    @click="transition(plan, 'close')"
                  >
                    Close
                  </button>
                  <button
                    v-if="['draft', 'open', 'locked'].includes(plan.state)"
                    class="rounded-lg border border-rose-900 px-3 py-1.5 font-semibold text-rose-300"
                    type="button"
                    @click="transition(plan, 'cancel')"
                  >
                    Cancel
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p
        v-else
        class="mt-5 rounded-xl border border-dashed border-slate-700 p-5 text-sm text-slate-400"
      >
        No transfer cycles have been created yet.
      </p>
    </section>
  </main>
</template>
