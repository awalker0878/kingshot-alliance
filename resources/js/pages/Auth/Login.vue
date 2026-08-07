<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
  invitationToken: string | null;
}>();

const form = useForm({
  email: '',
  password: '',
  remember: false,
  invitation_token: props.invitationToken ?? '',
});

function submit(): void {
  form.post('/login', {
    onFinish: () => form.reset('password'),
  });
}
</script>

<template>
  <Head title="Sign in" />

  <main class="mx-auto flex min-h-screen max-w-xl items-center px-6 py-16">
    <section class="w-full rounded-2xl border border-slate-800 bg-slate-900/70 p-8">
      <p class="text-sm font-semibold tracking-[0.2em] text-cyan-300 uppercase">
        Kingshot Alliance
      </p>
      <h1 class="mt-3 text-3xl font-bold">Sign in</h1>
      <p class="mt-2 text-sm text-slate-400">Access your alliances through one global account.</p>
      <p
        v-if="props.invitationToken"
        class="mt-5 rounded-lg border border-cyan-700/60 bg-cyan-950/30 p-4 text-sm text-cyan-100"
      >
        Sign in with the invited account to continue accepting your alliance invitation.
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

        <div>
          <div class="flex items-center justify-between gap-3">
            <label class="block text-sm font-medium" for="password">Password</label>
            <Link
              class="text-xs font-semibold text-cyan-300 hover:text-cyan-200"
              href="/forgot-password"
              >Forgot password?</Link
            >
          </div>
          <input
            id="password"
            v-model="form.password"
            autocomplete="current-password"
            class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            required
            type="password"
          />
          <p v-if="form.errors.password" class="mt-1 text-sm text-rose-300">
            {{ form.errors.password }}
          </p>
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-300">
          <input
            v-model="form.remember"
            class="rounded border-slate-600 bg-slate-950"
            type="checkbox"
          />
          Remember me
        </label>

        <button
          class="w-full rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
          :disabled="form.processing"
          type="submit"
        >
          Sign in
        </button>
      </form>

      <p class="mt-6 text-sm text-slate-400">
        Need an account?
        <Link
          class="font-semibold text-cyan-300 hover:text-cyan-200"
          :href="
            props.invitationToken
              ? `/register?invitation=${encodeURIComponent(props.invitationToken)}`
              : '/register'
          "
        >
          Register
        </Link>
      </p>
    </section>
  </main>
</template>
