<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
  password: '',
});

function submit(): void {
  form.post('/confirm-password', {
    onFinish: () => form.reset('password'),
  });
}
</script>

<template>
  <Head title="Confirm password" />

  <main class="mx-auto flex min-h-screen max-w-xl items-center px-6 py-16">
    <section class="w-full rounded-2xl border border-slate-800 bg-slate-900/70 p-8">
      <h1 class="text-3xl font-bold">Confirm your password</h1>
      <p class="mt-2 text-sm text-slate-400">This action changes alliance access or permissions, so your password must be reconfirmed.</p>

      <form class="mt-8 space-y-5" @submit.prevent="submit">
        <div>
          <label class="block text-sm font-medium" for="password">Password</label>
          <input
            id="password"
            v-model="form.password"
            autocomplete="current-password"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            required
            type="password"
          />
          <p v-if="form.errors.password" class="mt-1 text-sm text-rose-300">{{ form.errors.password }}</p>
        </div>

        <button
          class="w-full rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
          :disabled="form.processing"
          type="submit"
        >
          Confirm password
        </button>
      </form>
    </section>
  </main>
</template>
