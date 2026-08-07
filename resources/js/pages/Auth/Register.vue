<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
  registrationMode: string;
}>();

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
});

function submit(): void {
  form.post('/register', {
    onFinish: () => form.reset('password', 'password_confirmation'),
  });
}
</script>

<template>
  <Head title="Create account" />

  <main class="mx-auto flex min-h-screen max-w-xl items-center px-6 py-16">
    <section class="w-full rounded-2xl border border-slate-800 bg-slate-900/70 p-8">
      <p class="text-sm font-semibold tracking-[0.2em] text-cyan-300 uppercase">Phase 1</p>
      <h1 class="mt-3 text-3xl font-bold">Create your account</h1>
      <p class="mt-2 text-sm text-slate-400">
        One global identity can belong to multiple alliances.
      </p>

      <p
        v-if="props.registrationMode !== 'open'"
        class="mt-6 rounded-lg border border-amber-700/60 bg-amber-950/30 p-4 text-sm text-amber-200"
      >
        Registration is currently invitation-only.
      </p>

      <form v-else class="mt-8 space-y-5" @submit.prevent="submit">
        <div>
          <label class="block text-sm font-medium" for="name">Name</label>
          <input
            id="name"
            v-model="form.name"
            autocomplete="name"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            required
            type="text"
          />
          <p v-if="form.errors.name" class="mt-1 text-sm text-rose-300">{{ form.errors.name }}</p>
        </div>

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
          <label class="block text-sm font-medium" for="timezone">Time zone</label>
          <input
            id="timezone"
            v-model="form.timezone"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            required
            type="text"
          />
          <p v-if="form.errors.timezone" class="mt-1 text-sm text-rose-300">
            {{ form.errors.timezone }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="password">Password</label>
          <input
            id="password"
            v-model="form.password"
            autocomplete="new-password"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            minlength="12"
            required
            type="password"
          />
          <p class="mt-1 text-xs text-slate-500">
            At least 12 characters with mixed case and a number.
          </p>
          <p v-if="form.errors.password" class="mt-1 text-sm text-rose-300">
            {{ form.errors.password }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium" for="password_confirmation"
            >Confirm password</label
          >
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
          Create account
        </button>
      </form>

      <p class="mt-6 text-sm text-slate-400">
        Already have an account?
        <Link class="font-semibold text-cyan-300 hover:text-cyan-200" href="/login">Sign in</Link>
      </p>
    </section>
  </main>
</template>
