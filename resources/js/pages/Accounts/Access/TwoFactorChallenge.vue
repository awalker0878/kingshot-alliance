<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import AppButton from '@/components/ui/AppButton.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useLocale } from '@/localization';

const { t } = useLocale();
const form = useForm({ code: '', recovery_code: '' });

function submitCode(): void {
  form.recovery_code = '';
  form.post('/two-factor-challenge', { onFinish: () => form.reset('code') });
}

function submitRecoveryCode(): void {
  form.code = '';
  form.post('/two-factor-challenge', { onFinish: () => form.reset('recovery_code') });
}
</script>

<template>
  <Head :title="t('auth.twoFactor.title')" />

  <AuthLayout>
    <div
      class="mx-auto grid h-16 w-16 place-items-center rounded-full border border-[var(--ks-gold-dark)] bg-[var(--ks-gold-soft)] text-2xl text-[var(--ks-gold-bright)]"
      aria-hidden="true"
    >
      ⛨
    </div>
    <p class="ks-kicker mt-5 text-center">{{ t('authExperience.twoFactor.kicker') }}</p>
    <h2 class="ks-display mt-2 text-center text-3xl font-semibold sm:text-4xl">
      {{ t('auth.twoFactor.title') }}
    </h2>
    <p class="mt-3 text-center text-sm leading-6 text-[var(--ks-text-secondary)]">
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
        class="ks-input text-center font-mono text-lg tracking-[0.32em]"
        inputmode="numeric"
        maxlength="6"
        pattern="\d{6}"
        type="text"
      />
      <p v-if="form.errors.code" class="text-sm text-[var(--ks-red)]" role="alert">
        {{ form.errors.code }}
      </p>
      <AppButton class="w-full" type="submit" :disabled="form.processing">
        {{ t('authExperience.twoFactor.verifyCode') }}
      </AppButton>
    </form>

    <div class="ks-divider my-7" aria-hidden="true" />

    <form class="space-y-4" @submit.prevent="submitRecoveryCode">
      <label class="block text-sm font-semibold" for="recovery-code">
        {{ t('auth.twoFactor.recoveryCode') }}
      </label>
      <input
        id="recovery-code"
        v-model="form.recovery_code"
        autocomplete="off"
        class="ks-input font-mono"
        type="text"
      />
      <p v-if="form.errors.recovery_code" class="text-sm text-[var(--ks-red)]" role="alert">
        {{ form.errors.recovery_code }}
      </p>
      <AppButton class="w-full" type="submit" variant="ghost" :disabled="form.processing">
        {{ t('authExperience.twoFactor.useRecoveryCode') }}
      </AppButton>
    </form>
  </AuthLayout>
</template>
