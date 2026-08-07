<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
  alliance: {
    id: string;
    name: string;
    slug: string;
    kingdom: string | null;
    language: string;
    timezone: string;
  };
  membership: {
    id: string;
    roles: Array<{ key: string; name: string }>;
  };
}>();
</script>

<template>
  <Head :title="alliance.name" />

  <main class="mx-auto min-h-screen max-w-5xl px-6 py-12 lg:px-8">
    <Link class="text-sm font-semibold text-cyan-300 hover:text-cyan-200" href="/dashboard">
      ← Back to dashboard
    </Link>

    <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-8">
      <p class="text-sm font-semibold tracking-[0.2em] text-cyan-300 uppercase">Active alliance</p>
      <h1 class="mt-3 text-4xl font-bold">{{ alliance.name }}</h1>
      <dl class="mt-8 grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-slate-800 p-4">
          <dt class="text-sm text-slate-400">Kingdom</dt>
          <dd class="mt-1 font-semibold">{{ alliance.kingdom || 'Not set' }}</dd>
        </div>
        <div class="rounded-xl border border-slate-800 p-4">
          <dt class="text-sm text-slate-400">Time zone</dt>
          <dd class="mt-1 font-semibold">{{ alliance.timezone }}</dd>
        </div>
        <div class="rounded-xl border border-slate-800 p-4">
          <dt class="text-sm text-slate-400">Language</dt>
          <dd class="mt-1 font-semibold">{{ alliance.language }}</dd>
        </div>
        <div class="rounded-xl border border-slate-800 p-4">
          <dt class="text-sm text-slate-400">Your roles</dt>
          <dd class="mt-1 font-semibold">{{ membership.roles.map((role) => role.name).join(', ') || 'None' }}</dd>
        </div>
      </dl>
    </section>
  </main>
</template>
