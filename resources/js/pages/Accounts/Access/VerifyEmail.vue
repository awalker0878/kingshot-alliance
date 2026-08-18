<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';

import AppButton from '@/components/ui/AppButton.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useLocale } from '@/localization';

const props = defineProps<{ status: string | null; email: string }>();
const { t } = useLocale();

function resend(): void {
  router.post('/email/verification-notification');
}
</script>

<template>
  <Head :title="t('auth.verification.title')" />

  <AuthLayout>
    <div
      class="mx-auto grid h-16 w-16 place-items-center rounded-full border border-[var(--ks-gold-dark)] bg-[var(--ks-gold-soft)] text-2xl font-[var(--ks-font-display)] text-[var(--ks-gold-bright)]"
      aria-hidden="true"
    >
      ✉
    </div>
    <p class="ks-kicker mt-5 text-center">{{ t('common.appName') }}</p>
    <h2 class="ks-display mt-2 text-center text-3xl font-semibold sm:text-4xl">
      {{ t('auth.verification.title') }}
    </h2>
    <p class="mt-3 text-center text-sm leading-6 text-[var(--ks-text-secondary)]">
      {{ t('authExperience.verification.description', { email: props.email }) }}
    </p>

    <p
      v-if="props.status === 'verification-link-sent'"
      class="mt-6 rounded-[var(--ks-radius-md)] border border-emerald-400/25 bg-emerald-500/[.07] p-4 text-sm leading-6 text-emerald-100"
      role="status"
    >
      {{ t('authExperience.verification.sent') }}
    </p>

    <AppButton class="mt-7 w-full" type="button" @click="resend">
      {{ t('auth.verification.resend') }}
    </AppButton>
  </AuthLayout>
</template>
