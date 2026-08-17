<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import AuthLayout from '@/layouts/AuthLayout.vue';
import { useLocale } from '@/localization';

const props = defineProps<{
  token: string;
  email: string;
}>();

const { t } = useLocale();

const form = useForm({
  token: props.token,
  email: props.email,
  password: '',
  password_confirmation: '',
});

function submit(): void {
  form.post('/reset-password', {
    onFinish: () => form.reset('password', 'password_confirmation'),
  });
}
</script>

<template>
  <Head :title="t('auth.password.resetTitle')" />

  <AuthLayout>
    <template #headline>{{ t('auth.password.resetTitle') }}</template>
    <template #intro>{{ t('authExperience.password.resetIntro') }}</template>

    <div>
      <h2 class="ks-display text-2xl font-semibold sm:text-3xl">
        {{ t('auth.password.resetTitle') }}
      </h2>
      <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
        {{ t('authExperience.password.resetIntro') }}
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

        <div>
          <label class="block text-sm font-semibold" for="password">
            {{ t('authExperience.password.newPassword') }}
          </label>
          <input
            id="password"
            v-model="form.password"
            autocomplete="new-password"
            class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3.5 py-2.5 transition outline-none hover:border-[var(--ks-border-strong)] focus:border-[var(--ks-blue)]"
            required
            type="password"
          />
          <p v-if="form.errors.password" class="mt-2 text-sm text-rose-300" role="alert">
            {{ form.errors.password }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-semibold" for="password_confirmation">
            {{ t('authExperience.password.confirmNewPassword') }}
          </label>
          <input
            id="password_confirmation"
            v-model="form.password_confirmation"
            autocomplete="new-password"
            class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3.5 py-2.5 transition outline-none hover:border-[var(--ks-border-strong)] focus:border-[var(--ks-blue)]"
            required
            type="password"
          />
        </div>

        <button
          class="w-full rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-5 py-3 font-bold text-[var(--ks-ink)] transition hover:bg-[var(--ks-gold-strong)] disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="form.processing"
          type="submit"
        >
          {{ t('auth.password.resetSubmit') }}
        </button>
      </form>
    </div>
  </AuthLayout>
</template>
