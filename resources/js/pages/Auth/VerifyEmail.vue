<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';

import AuthLayout from '../../layouts/AuthLayout.vue';
import { useLocale } from '../../localization';

const props = defineProps<{
  status: string | null;
  email: string;
}>();

const { t } = useLocale();

function resend(): void {
  router.post('/email/verification-notification');
}
</script>

<template>
  <Head :title="t('auth.verification.title')" />

  <AuthLayout>
    <template #headline>{{ t('auth.verification.title') }}</template>
    <template #intro>
      {{ t('authExperience.verification.description', { email: props.email }) }}
    </template>

    <div>
      <h2 class="ks-display text-2xl font-semibold sm:text-3xl">
        {{ t('auth.verification.title') }}
      </h2>
      <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
        {{ t('authExperience.verification.description', { email: props.email }) }}
      </p>

      <p
        v-if="props.status === 'verification-link-sent'"
        class="mt-6 rounded-[var(--ks-radius-sm)] border border-emerald-800 bg-emerald-950/25 p-4 text-sm leading-6 text-emerald-100"
        role="status"
      >
        {{ t('authExperience.verification.sent') }}
      </p>

      <button
        class="mt-7 w-full rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-5 py-3 font-bold text-slate-950 transition hover:bg-[var(--ks-gold-strong)]"
        type="button"
        @click="resend"
      >
        {{ t('auth.verification.resend') }}
      </button>
    </div>
  </AuthLayout>
</template>
