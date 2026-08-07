<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
  token: string;
  email: string;
}>();

const form = useForm({
  token: props.token,
  email: props.email,
  password: '',
  password_confirmation: '',
});

function submit(): void {
  form.post('/reset-password', {
    onFinish: () => form.reset('password', 'password_confirmation'),
  });
}
</script>

<template>
  <Head title="Choose new password" />

  <main class="mx-auto flex min-h-screen max-w-xl items-center px-6 py-16">
    <section class="w-full rounded-2xl border border-slate-800 bg-slate-900/70 p-8">
      <h1 class="text-3xl font-bold">Choose a new password</h1>
      <p class="mt-2 text-sm text-slate-400">Resetting your password revokes personal access tokens.</p>

      <form class="mt-8 space-y-5" @submit.prevent="submit">
        <div>
          <label class="block text-sm font-medium" for="email">Email</label>
          <input
            id="email"
            v-model="form.email"
            autocomplete="email"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            required
            type="email"
          />
          <p v-if="form.errors.email" class="mt-1 text-sm text-rose-300">{{ form.errors.email }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="password">New password</label>
          <input
            id="password"
            v-model="form.password"
            autocomplete="new-password"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            required
            type="password"
          />
          <p v-if="form.errors.password" class="mt-1 text-sm text-rose-300">{{ form.errors.password }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="password_confirmation">Confirm new password</label>
          <input
            id="password_confirmation"
            v-model="form.password_confirmation"
            autocomplete="new-password"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            required
            type="password"
          />
        </div>

        <button
          class="w-full rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
          :disabled="form.processing"
          type="submit"
        >
          Reset password
        </button>
      </form>
    </section>
  </main>
</template>
