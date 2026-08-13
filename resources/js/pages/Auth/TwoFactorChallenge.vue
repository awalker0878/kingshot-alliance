<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import AuthLayout from '../../layouts/AuthLayout.vue';
import { useLocale } from '../../localization';

const { t } = useLocale();

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
  <Head :title="t('auth.twoFactor.title')" />

  <AuthLayout>
    <template #headline>{{ t('auth.twoFactor.title') }}</template>
    <template #intro>{{ t('authExperience.twoFactor.description') }}</template>

    <div>
      <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
        {{ t('authExperience.twoFactor.kicker') }}
      </p>
      <h2 class="ks-display mt-2 text-2xl font-semibold sm:text-3xl">
        {{ t('auth.twoFactor.title') }}
      </h2>
      <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
        {{ t('authExperience.twoFactor.description') }}
      </p>

      <form class="mt-7 space-y-4" @submit.prevent="submitCode">
        <label class="block text-sm font-semibold" for="two-factor-code">
          {{ t('auth.twoFactor.code') }}
        </label>
        <input
          id="two-factor-code"
          v-model="form.code"
          autocomplete="one-time-code"
          class="w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3.5 py-3 text-center font-mono text-lg tracking-[0.32em] transition outline-none hover:border-[var(--ks-border-strong)] focus:border-[var(--ks-blue)]"
          inputmode="numeric"
          maxlength="6"
          pattern="\d{6}"
          type="text"
        />
        <p v-if="form.errors.code" class="text-sm text-rose-300" role="alert">
          {{ form.errors.code }}
        </p>
        <button
          class="w-full rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-5 py-3 font-bold text-slate-950 transition hover:bg-[var(--ks-gold-strong)] disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="form.processing"
          type="submit"
        >
          {{ t('authExperience.twoFactor.verifyCode') }}
        </button>
      </form>

      <div class="my-7 flex items-center gap-3" aria-hidden="true">
        <div class="h-px flex-1 bg-[var(--ks-border)]" />
        <div class="h-1.5 w-1.5 rotate-45 border border-[var(--ks-border-strong)]" />
        <div class="h-px flex-1 bg-[var(--ks-border)]" />
      </div>

      <form class="space-y-4" @submit.prevent="submitRecoveryCode">
        <label class="block text-sm font-semibold" for="recovery-code">
          {{ t('auth.twoFactor.recoveryCode') }}
        </label>
        <input
          id="recovery-code"
          v-model="form.recovery_code"
          autocomplete="off"
          class="w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3.5 py-2.5 font-mono transition outline-none hover:border-[var(--ks-border-strong)] focus:border-[var(--ks-blue)]"
          placeholder="xxxx-xxxx-xxxx-xxxx"
          type="text"
        />
        <p v-if="form.errors.recovery_code" class="text-sm text-rose-300" role="alert">
          {{ form.errors.recovery_code }}
        </p>
        <button
          class="w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[rgba(8,17,31,0.48)] px-5 py-3 font-semibold transition hover:border-[var(--ks-border-strong)] hover:bg-[var(--ks-surface-1)] disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="form.processing"
          type="submit"
        >
          {{ t('authExperience.twoFactor.useRecoveryCode') }}
        </button>
      </form>
    </div>
  </AuthLayout>
</template>
