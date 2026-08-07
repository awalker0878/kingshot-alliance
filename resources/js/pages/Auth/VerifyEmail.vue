<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';

const props = defineProps<{
  status: string | null;
  email: string;
}>();

function resend(): void {
  router.post('/email/verification-notification');
}
</script>

<template>
  <Head title="Verify email" />

  <main class="mx-auto flex min-h-screen max-w-xl items-center px-6 py-16">
    <section class="w-full rounded-2xl border border-slate-800 bg-slate-900/70 p-8">
      <h1 class="text-3xl font-bold">Verify your email</h1>
      <p class="mt-3 text-sm text-slate-300">
        We sent a verification link to <strong>{{ props.email }}</strong>. Verify the address before performing protected account actions.
      </p>

      <p
        v-if="props.status === 'verification-link-sent'"
        class="mt-5 rounded-lg border border-emerald-800 bg-emerald-950/30 p-4 text-sm text-emerald-100"
      >
        A fresh verification link has been sent.
      </p>

      <button class="mt-8 w-full rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950" type="button" @click="resend">
        Resend verification email
      </button>
    </section>
  </main>
</template>
