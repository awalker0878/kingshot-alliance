<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

defineProps<{
  request: null | {
    status: string;
    requestedAt: string;
    eligibleAt: string;
    processedAt: string | null;
    blockedReason: string | null;
  };
  status: string | null;
}>();

function requestDeletion(): void {
  if (!window.confirm('Request account deletion? There is a seven-day cooling-off period and ownership/legal-hold checks apply.')) {
    return;
  }

  router.post('/profile/delete-account');
}
</script>

<template>
  <Head title="Account deletion" />
  <main class="mx-auto max-w-3xl space-y-8 p-6">
    <header class="space-y-2">
      <Link href="/profile" class="text-sm font-semibold">← Account & security</Link>
      <h1 class="text-3xl font-bold">Account deletion</h1>
      <p class="text-sm text-slate-600">
        Deletion uses a seven-day cooling-off period. Active alliance ownership, platform-administrator access, and
        legal holds prevent processing until resolved. Processed accounts are anonymized rather than silently
        removing audit history.
      </p>
      <p v-if="status" role="status" class="rounded border p-3 text-sm">{{ status }}</p>
    </header>

    <section v-if="request" aria-labelledby="request-heading" class="space-y-3 rounded border p-5">
      <h2 id="request-heading" class="text-xl font-semibold">Current request</h2>
      <dl class="grid gap-3 sm:grid-cols-2">
        <div><dt class="text-sm text-slate-500">Status</dt><dd class="font-semibold">{{ request.status }}</dd></div>
        <div><dt class="text-sm text-slate-500">Eligible at</dt><dd>{{ request.eligibleAt }}</dd></div>
        <div><dt class="text-sm text-slate-500">Requested</dt><dd>{{ request.requestedAt }}</dd></div>
        <div><dt class="text-sm text-slate-500">Processed</dt><dd>{{ request.processedAt || 'Not yet' }}</dd></div>
      </dl>
      <p v-if="request.blockedReason" class="rounded border border-amber-400 p-3 text-sm">{{ request.blockedReason }}</p>
    </section>

    <section v-else class="space-y-4 rounded border border-red-400 p-5">
      <h2 class="text-xl font-semibold">Request deletion</h2>
      <p class="text-sm text-slate-600">
        Transfer ownership of any alliance you own first. The request will not erase records subject to legal hold or
        records that must remain pseudonymized for security and audit integrity.
      </p>
      <button type="button" class="rounded border border-red-500 px-4 py-2 font-semibold text-red-700" @click="requestDeletion">
        Request account deletion
      </button>
    </section>
  </main>
</template>
