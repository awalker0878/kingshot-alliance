<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import AppButton from '@/components/ui/AppButton.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useLocale } from '@/localization';

const { t } = useLocale();
const form = useForm({ password: '' });

function submit(): void {
  form.post('/confirm-password', { onFinish: () => form.reset('password') });
}
</script>

<template>
  <Head :title="t('auth.password.confirmTitle')" />

  <AuthLayout>
    <p class="ks-kicker">{{ t('common.appName') }}</p>
    <h2 class="ks-display mt-2 text-3xl font-semibold sm:text-4xl">
      {{ t('auth.password.confirmTitle') }}
    </h2>
    <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
      {{ t('authExperience.password.confirmDescription') }}
    </p>

    <form class="mt-7 space-y-5" @submit.prevent="submit">
      <div>
        <label class="block text-sm font-semibold" for="password">{{
          t('auth.login.password')
        }}</label>
        <input
          id="password"
          v-model="form.password"
          autocomplete="current-password"
          class="ks-input mt-2"
          required
          type="password"
        />
        <p v-if="form.errors.password" class="mt-2 text-sm text-[var(--ks-red)]" role="alert">
          {{ form.errors.password }}
        </p>
      </div>
      <AppButton class="w-full" type="submit" :disabled="form.processing">
        {{ t('auth.password.confirmTitle') }}
      </AppButton>
    </form>
  </AuthLayout>
</template>
