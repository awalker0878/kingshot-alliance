<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
  status: string | null;
}>();

const form = useForm({
  email: '',
});

function submit(): void {
  form.post('/forgot-password');
}
</script>

<template>
  <Head title="Reset password" />

  <main class="mx-auto flex min-h-screen max-w-xl items-center px-6 py-16">
    <section class="w-full rounded-2xl border border-slate-800 bg-slate-900/70 p-8">
      <h1 class="text-3xl font-bold">Reset your password</h1>
      <p class="mt-2 text-sm text-slate-400">
        Enter your account email. If it exists, a reset link will be sent.
      </p>

      <p
        v-if="props.status"
        class="mt-5 rounded-lg border border-emerald-800 bg-emerald-950/30 p-4 text-sm text-emerald-100"
      >
        {{ props.status }}
      </p>

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

        <button
          class="w-full rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
          :disabled="form.processing"
          type="submit"
        >
          Send reset link
        </button>
      </form>

      <p class="mt-6 text-sm text-slate-400">
        <Link class="font-semibold text-cyan-300 hover:text-cyan-200" href="/login"
          >Back to sign in</Link
        >
      </p>
    </section>
  </main>
</template>
