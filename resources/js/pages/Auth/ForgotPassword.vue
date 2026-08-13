<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import AuthLayout from '../../layouts/AuthLayout.vue';
import { useLocale } from '../../localization';

const props = defineProps<{
  status: string | null;
}>();

const { t } = useLocale();

const form = useForm({
  email: '',
});

function submit(): void {
  form.post('/forgot-password');
}
</script>

<template>
  <Head :title="t('auth.password.forgotTitle')" />

  <AuthLayout>
    <template #headline>{{ t('auth.password.forgotTitle') }}</template>
    <template #intro>{{ t('auth.password.forgotDescription') }}</template>

    <div>
      <h2 class="ks-display text-2xl font-semibold sm:text-3xl">
        {{ t('auth.password.forgotTitle') }}
      </h2>
      <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
        {{ t('auth.password.forgotDescription') }}
      </p>

      <p
        v-if="props.status"
        class="mt-6 rounded-[var(--ks-radius-sm)] border border-emerald-800 bg-emerald-950/25 p-4 text-sm leading-6 text-emerald-100"
        role="status"
      >
        {{ props.status }}
      </p>

      <form class="mt-7 space-y-5" @submit.prevent="submit">
        <div>
          <label class="block text-sm font-semibold" for="email">{{ t('auth.login.email') }}</label>
          <input
            id="email"
            v-model="form.email"
            autocomplete="email"
            class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3.5 py-2.5 transition outline-none hover:border-[var(--ks-border-strong)] focus:border-[var(--ks-blue)]"
            required
            type="email"
          />
          <p v-if="form.errors.email" class="mt-2 text-sm text-rose-300" role="alert">
            {{ form.errors.email }}
          </p>
        </div>

        <button
          class="w-full rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-5 py-3 font-bold text-slate-950 transition hover:bg-[var(--ks-gold-strong)] disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="form.processing"
          type="submit"
        >
          {{ t('auth.password.sendResetLink') }}
        </button>
      </form>

      <p class="mt-6 text-sm">
        <Link
          class="font-semibold text-[var(--ks-gold)] transition hover:text-[var(--ks-gold-strong)]"
          href="/login"
        >
          {{ t('authExperience.password.backToSignIn') }}
        </Link>
      </p>
    </div>
  </AuthLayout>
</template>
