<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import AuthLayout from '../../layouts/AuthLayout.vue';
import { useLocale } from '../../localization';

const { t } = useLocale();

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
  <Head :title="t('auth.password.confirmTitle')" />

  <AuthLayout>
    <template #headline>{{ t('auth.password.confirmTitle') }}</template>
    <template #intro>{{ t('authExperience.password.confirmDescription') }}</template>

    <div>
      <h2 class="ks-display text-2xl font-semibold sm:text-3xl">
        {{ t('auth.password.confirmTitle') }}
      </h2>
      <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
        {{ t('authExperience.password.confirmDescription') }}
      </p>

      <form class="mt-7 space-y-5" @submit.prevent="submit">
        <div>
          <label class="block text-sm font-semibold" for="password">
            {{ t('auth.login.password') }}
          </label>
          <input
            id="password"
            v-model="form.password"
            autocomplete="current-password"
            class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3.5 py-2.5 transition outline-none hover:border-[var(--ks-border-strong)] focus:border-[var(--ks-blue)]"
            required
            type="password"
          />
          <p v-if="form.errors.password" class="mt-2 text-sm text-rose-300" role="alert">
            {{ form.errors.password }}
          </p>
        </div>

        <button
          class="w-full rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-5 py-3 font-bold text-slate-950 transition hover:bg-[var(--ks-gold-strong)] disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="form.processing"
          type="submit"
        >
          {{ t('auth.password.confirmTitle') }}
        </button>
      </form>
    </div>
  </AuthLayout>
</template>
