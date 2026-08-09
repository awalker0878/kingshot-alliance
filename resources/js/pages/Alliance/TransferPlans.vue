<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

type Plan = {
  id: string;
  label: string;
  homeKingdom: string;
  startsOn: string | null;
  endsOn: string | null;
  state: string;
  createdAt: string | null;
};

defineProps<{
  alliance: { id: string; name: string; kingdom: string | null };
  canManage: boolean;
  plan: Plan | null;
}>();

function stateLabel(state: string): string {
  return state.charAt(0).toUpperCase() + state.slice(1);
}
</script>

<template>
  <Head :title="`Transfers · ${alliance.name}`" />

  <main class="mx-auto min-h-screen max-w-5xl px-6 py-12 text-slate-100 lg:px-8">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <Link class="text-sm font-semibold text-cyan-300 hover:text-cyan-200" href="/alliance">
          ← Alliance
        </Link>
        <h1 class="mt-3 text-3xl font-bold">Transfer planning</h1>
        <p class="mt-2 text-sm text-slate-400">
          {{ alliance.name }} · Kingdom {{ alliance.kingdom ?? 'not set' }}
        </p>
      </div>
      <div class="flex flex-wrap gap-3">
        <Link
          class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold hover:border-slate-500"
          href="/alliance/roster"
        >
          Roster
        </Link>
        <Link
          v-if="canManage"
          class="rounded-lg bg-cyan-300 px-4 py-2 text-sm font-semibold text-slate-950"
          href="/alliance/transfers/manage"
        >
          Manage transfer cycles
        </Link>
      </div>
    </header>

    <section class="mt-10 rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <h2 class="text-xl font-semibold">Current cycle</h2>

      <div v-if="plan" class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div class="sm:col-span-2">
          <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Cycle</p>
          <p class="mt-1 text-lg font-semibold">{{ plan.label }}</p>
        </div>
        <div>
          <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">State</p>
          <p class="mt-1 font-semibold">{{ stateLabel(plan.state) }}</p>
        </div>
        <div>
          <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Home kingdom</p>
          <p class="mt-1 font-semibold">{{ plan.homeKingdom }}</p>
        </div>
        <div>
          <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Starts</p>
          <p class="mt-1">{{ plan.startsOn ?? 'Not specified' }}</p>
        </div>
        <div>
          <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Ends</p>
          <p class="mt-1">{{ plan.endsOn ?? 'Not specified' }}</p>
        </div>
      </div>

      <p v-else class="mt-4 text-sm text-slate-400">
        There is no current transfer cycle for this alliance.
      </p>
    </section>
  </main>
</template>
