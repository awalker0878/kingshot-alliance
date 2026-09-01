<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import AppButton from '@/components/ui/AppButton.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useLocale } from '@/localization';

const props = defineProps<{ status: string | null }>();
const { t } = useLocale();
const form = useForm({ email: '' });

function submit(): void {
  form.post('/forgot-password');
}
</script>

<template>
  <Head :title="t('auth.password.forgotTitle')" />

  <AuthLayout>
    <p class="ks-kicker">{{ t('authExperience.password.recoveryKicker') }}</p>

    <template v-if="props.status">
      <h2 class="ks-display mt-2 text-3xl font-semibold sm:text-4xl">
        {{ t('authExperience.password.checkInboxHeading') }}
      </h2>
      <div
        class="mt-6 rounded-[var(--ks-radius-lg)] border border-[var(--ks-gold-dark)] bg-[var(--ks-gold-soft)] p-5"
        aria-live="polite"
        role="status"
      >
        <p class="ks-kicker">{{ t('authExperience.password.checkInboxKicker') }}</p>
        <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('authExperience.password.checkInboxDescription') }}
        </p>
      </div>
    </template>

    <template v-else>
      <h2 class="ks-display mt-2 text-3xl font-semibold sm:text-4xl">
        {{ t('auth.password.forgotTitle') }}
      </h2>
      <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
        {{ t('auth.password.forgotDescription') }}
      </p>
      <p class="mt-2 text-xs leading-5 text-[var(--ks-text-muted)]">
        {{ t('authExperience.password.recoveryNeutral') }}
      </p>

      <form class="mt-7 space-y-5" @submit.prevent="submit">
        <div>
          <label class="block text-sm font-semibold" for="email">{{ t('auth.login.email') }}</label>
          <input
            id="email"
            v-model="form.email"
            autocomplete="email"
            class="ks-input mt-2"
            required
            type="email"
          />
          <p v-if="form.errors.email" class="mt-2 text-sm text-[var(--ks-red)]" role="alert">
            {{ form.errors.email }}
          </p>
        </div>
        <AppButton class="w-full" type="submit" :disabled="form.processing">
          {{ t('auth.password.sendResetLink') }}
        </AppButton>
      </form>
    </template>

    <div class="ks-divider my-6" />
    <Link
      class="block text-center text-sm font-semibold text-[var(--ks-gold-bright)] hover:text-[var(--ks-ivory)]"
      href="/login"
    >
      {{ t('authExperience.password.backToSignIn') }}
    </Link>
  </AuthLayout>
</template>
