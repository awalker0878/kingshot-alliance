<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import AppButton from '@/components/ui/AppButton.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useLocale } from '@/localization';

const props = defineProps<{ token: string; email: string }>();
const { t } = useLocale();
const showPassword = ref(false);
const form = useForm({
  token: props.token,
  email: props.email,
  password: '',
  password_confirmation: '',
});

const passwordRules = computed(() => [
  { key: 'length', met: form.password.length >= 12, label: t('authExperience.password.requirementLength') },
  { key: 'upper', met: /[A-Z]/.test(form.password), label: t('authExperience.password.requirementUpper') },
  { key: 'lower', met: /[a-z]/.test(form.password), label: t('authExperience.password.requirementLower') },
  { key: 'number', met: /\d/.test(form.password), label: t('authExperience.password.requirementNumber') },
]);

function submit(): void {
  form.post('/reset-password', {
    onFinish: () => form.reset('password', 'password_confirmation'),
  });
}
</script>

<template>
  <Head :title="t('auth.password.resetTitle')" />

  <AuthLayout>
    <p class="ks-kicker">{{ t('authExperience.password.resetKicker') }}</p>
    <h2 class="ks-display mt-2 text-3xl font-semibold sm:text-4xl">
      {{ t('auth.password.resetTitle') }}
    </h2>
    <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
      {{ t('authExperience.password.resetIntro') }}
    </p>

    <div class="mt-5 rounded-[var(--ks-radius-md)] border border-white/10 bg-black/20 p-4">
      <p class="text-xs font-semibold uppercase tracking-[.14em] text-[var(--ks-text-muted)]">
        {{ t('authExperience.password.accountEmail') }}
      </p>
      <p class="mt-1 break-all text-sm font-semibold text-[var(--ks-ivory)]">{{ props.email }}</p>
    </div>

    <p
      v-if="form.hasErrors"
      class="mt-5 rounded-[var(--ks-radius-md)] border border-[var(--ks-red)]/40 bg-[var(--ks-red)]/10 p-4 text-sm text-[var(--ks-ivory)]"
      role="alert"
    >
      {{ t('authExperience.password.validationSummary') }}
    </p>

    <form class="mt-7 space-y-5" @submit.prevent="submit">
      <div>
        <div class="flex items-center justify-between gap-3">
          <label class="block text-sm font-semibold" for="password">{{
            t('authExperience.password.newPassword')
          }}</label>
          <button
            class="text-xs font-semibold text-[var(--ks-gold-bright)] hover:text-[var(--ks-ivory)]"
            type="button"
            @click="showPassword = !showPassword"
          >
            {{
              showPassword
                ? t('authExperience.password.hidePassword')
                : t('authExperience.password.showPassword')
            }}
          </button>
        </div>
        <input
          id="password"
          v-model="form.password"
          autocomplete="new-password"
          class="ks-input mt-2"
          required
          :type="showPassword ? 'text' : 'password'"
        />
        <p v-if="form.errors.password" class="mt-2 text-sm text-[var(--ks-red)]" role="alert">
          {{ form.errors.password }}
        </p>
      </div>

      <div class="rounded-[var(--ks-radius-md)] border border-white/10 bg-white/[.025] p-4">
        <p class="text-xs font-semibold uppercase tracking-[.14em] text-[var(--ks-text-muted)]">
          {{ t('authExperience.password.requirements') }}
        </p>
        <ul class="mt-3 grid gap-2 text-sm" aria-live="polite">
          <li v-for="rule in passwordRules" :key="rule.key" class="flex items-center gap-2">
            <span aria-hidden="true" :class="rule.met ? 'text-emerald-300' : 'text-[var(--ks-text-muted)]'">
              {{ rule.met ? '✓' : '•' }}
            </span>
            <span :class="rule.met ? 'text-[var(--ks-ivory)]' : 'text-[var(--ks-text-secondary)]'">{{ rule.label }}</span>
          </li>
        </ul>
      </div>

      <div>
        <label class="block text-sm font-semibold" for="password_confirmation">{{
          t('authExperience.password.confirmNewPassword')
        }}</label>
        <input
          id="password_confirmation"
          v-model="form.password_confirmation"
          autocomplete="new-password"
          class="ks-input mt-2"
          required
          :type="showPassword ? 'text' : 'password'"
        />
      </div>
      <AppButton class="w-full" type="submit" :disabled="form.processing">
        {{ t('auth.password.resetSubmit') }}
      </AppButton>
    </form>
  </AuthLayout>
</template>
