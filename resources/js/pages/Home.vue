<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

type ApplicationMetadata = {
  name: string;
  environment: string;
  version: string;
  releaseSha: string;
};

defineProps<{
  application: ApplicationMetadata;
}>();
</script>

<template>
  <Head title="Engineering Foundation" />

  <main class="mx-auto flex min-h-screen max-w-6xl items-center px-6 py-16 lg:px-8">
    <section class="w-full">
      <p class="mb-4 text-sm font-semibold tracking-[0.25em] text-cyan-300 uppercase">
        Phase 0
      </p>
      <h1 class="max-w-4xl text-4xl font-bold tracking-tight sm:text-6xl">
        A secure foundation for alliance coordination.
      </h1>
      <p class="mt-6 max-w-3xl text-lg leading-8 text-slate-300">
        Kingshot Alliance is being built as a tenant-safe Laravel modular monolith with
        observable operations, repeatable delivery, and accessible interfaces.
      </p>

      <dl class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
          <dt class="text-sm text-slate-400">Application</dt>
          <dd class="mt-2 font-semibold">{{ application.name }}</dd>
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
          <dt class="text-sm text-slate-400">Environment</dt>
          <dd class="mt-2 font-semibold">{{ application.environment }}</dd>
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
          <dt class="text-sm text-slate-400">Version</dt>
          <dd class="mt-2 font-mono text-sm">{{ application.version }}</dd>
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
          <dt class="text-sm text-slate-400">Release</dt>
          <dd class="mt-2 truncate font-mono text-sm">{{ application.releaseSha }}</dd>
        </div>
      </dl>

      <div class="mt-10 flex flex-wrap gap-3 text-sm">
        <a
          class="rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950 hover:bg-cyan-200"
          href="/up"
        >
          Liveness
        </a>
        <a
          class="rounded-lg border border-slate-700 px-4 py-2 font-semibold hover:border-slate-500"
          href="/health/ready"
        >
          Readiness
        </a>
      </div>
    </section>
  </main>
</template>
