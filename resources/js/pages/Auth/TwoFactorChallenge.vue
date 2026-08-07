<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
  code: '',
  recovery_code: '',
});

function submitCode(): void {
  form.recovery_code = '';
  form.post('/two-factor-challenge', {
    onFinish: () => form.reset('code'),
  });
}

function submitRecoveryCode(): void {
  form.code = '';
  form.post('/two-factor-challenge', {
    onFinish: () => form.reset('recovery_code'),
  });
}
</script>

<template>
  <Head title="Two-factor authentication" />

  <main class="mx-auto flex min-h-screen max-w-xl items-center px-6 py-16">
    <section class="w-full rounded-2xl border border-slate-800 bg-slate-900/70 p-8">
      <p class="text-sm font-semibold tracking-[0.2em] text-cyan-300 uppercase">Security check</p>
      <h1 class="mt-3 text-3xl font-bold">Two-factor authentication</h1>
      <p class="mt-2 text-sm text-slate-400">
        Enter the current six-digit code from your authenticator app.
      </p>

      <form class="mt-8 space-y-4" @submit.prevent="submitCode">
        <label class="block text-sm font-medium" for="two-factor-code">Authentication code</label>
        <input
          id="two-factor-code"
          v-model="form.code"
          autocomplete="one-time-code"
          class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 font-mono tracking-[0.25em]"
          inputmode="numeric"
          maxlength="6"
          pattern="\d{6}"
          type="text"
        />
        <p v-if="form.errors.code" class="text-sm text-rose-300">{{ form.errors.code }}</p>
        <button
          class="w-full rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950"
          type="submit"
        >
          Verify code
        </button>
      </form>

      <div class="my-8 border-t border-slate-800" />

      <form class="space-y-4" @submit.prevent="submitRecoveryCode">
        <label class="block text-sm font-medium" for="recovery-code">Recovery code</label>
        <input
          id="recovery-code"
          v-model="form.recovery_code"
          autocomplete="off"
          class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 font-mono"
          placeholder="xxxx-xxxx-xxxx-xxxx"
          type="text"
        />
        <button
          class="w-full rounded-lg border border-slate-700 px-4 py-2 font-semibold"
          type="submit"
        >
          Use recovery code
        </button>
      </form>
    </section>
  </main>
</template>
