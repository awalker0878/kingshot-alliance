<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref } from 'vue';

import AppButton from '@/components/ui/AppButton.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useLocale } from '@/localization';

const props = defineProps<{ status: string | null; email: string }>();
const { t } = useLocale();
const resendSeconds = ref(0);
let resendTimer: ReturnType<typeof setInterval> | null = null;

function startCooldown(): void {
  resendSeconds.value = 60;
  if (resendTimer !== null) clearInterval(resendTimer);
  resendTimer = setInterval(() => {
    resendSeconds.value = Math.max(0, resendSeconds.value - 1);
    if (resendSeconds.value === 0 && resendTimer !== null) {
      clearInterval(resendTimer);
      resendTimer = null;
    }
  }, 1000);
}

function resend(): void {
  if (resendSeconds.value > 0) return;
  router.post('/email/verification-notification', {}, { onSuccess: startCooldown });
}

function useAnotherAccount(): void {
  router.delete('/logout');
}

onBeforeUnmount(() => {
  if (resendTimer !== null) clearInterval(resendTimer);
});
</script>

<template>
  <Head :title="t('auth.verification.title')" />

  <AuthLayout>
    <div
      class="mx-auto grid h-16 w-16 place-items-center rounded-full border border-[var(--ks-gold-dark)] bg-[var(--ks-gold-soft)] text-[var(--ks-gold-bright)]"
      aria-hidden="true"
    >
      <svg
        viewBox="0 0 24 24"
        class="h-7 w-7"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
      >
        <path d="M3.75 6.75h16.5v10.5H3.75z" />
        <path d="m4.5 7.5 7.5 5.25 7.5-5.25" />
      </svg>
    </div>
    <p class="ks-kicker mt-5 text-center">{{ t('authExperience.verification.kicker') }}</p>
    <h2 class="ks-display mt-2 text-center text-3xl font-semibold sm:text-4xl">
      {{ t('auth.verification.title') }}
    </h2>
    <p class="mt-3 text-center text-sm leading-6 text-[var(--ks-text-secondary)]">
      {{ t('authExperience.verification.description') }}
    </p>

    <div
      class="mt-5 rounded-[var(--ks-radius-md)] border border-white/10 bg-black/20 px-4 py-3 text-center"
    >
      <span class="break-all text-sm font-semibold text-[var(--ks-ivory)]">{{ props.email }}</span>
    </div>

    <p class="mt-4 text-center text-sm leading-6 text-[var(--ks-text-secondary)]">
      {{ t('authExperience.verification.instructions') }}
    </p>

    <p
      v-if="props.status === 'verification-link-sent'"
      class="mt-5 rounded-[var(--ks-radius-md)] border border-emerald-400/25 bg-emerald-500/[.07] p-4 text-sm leading-6 text-emerald-100"
      aria-live="polite"
      role="status"
    >
      {{ t('authExperience.verification.sent') }}
    </p>

    <div
      class="mt-5 rounded-[var(--ks-radius-md)] border border-white/10 bg-white/[.025] p-4 text-xs leading-5 text-[var(--ks-text-muted)]"
    >
      {{ t('authExperience.verification.securityNote') }}
    </div>

    <AppButton
      class="mt-7 w-full"
      type="button"
      :disabled="resendSeconds > 0"
      @click="resend"
    >
      {{
        resendSeconds > 0
          ? t('authExperience.verification.resendIn', { seconds: resendSeconds })
          : t('auth.verification.resend')
      }}
    </AppButton>

    <button
      class="mt-4 w-full text-center text-sm font-semibold text-[var(--ks-gold-bright)] hover:text-[var(--ks-ivory)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--ks-gold-bright)]"
      type="button"
      @click="useAnotherAccount"
    >
      {{ t('authExperience.verification.useAnotherAccount') }}
    </button>
  </AuthLayout>
</template>
