<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import AuthLayout from '../../layouts/AuthLayout.vue';
import { useLocale } from '../../localization';

const props = defineProps<{
  invitationToken: string | null;
}>();

const { t } = useLocale();

const form = useForm({
  email: '',
  password: '',
  remember: false,
  invitation_token: props.invitationToken ?? '',
});

function submit(): void {
  form.post('/login', {
    onFinish: () => form.reset('password'),
  });
}
</script>

<template>
  <Head :title="t('auth.login.title')" />

  <AuthLayout>
    <template #headline>{{ t('auth.login.title') }}</template>
    <template #intro>{{ t('authExperience.login.intro') }}</template>

    <div>
      <div class="flex items-start justify-between gap-4">
        <div>
          <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
            {{ t('common.appName') }}
          </p>
          <h2 class="ks-display mt-2 text-2xl font-semibold sm:text-3xl">
            {{ t('auth.login.title') }}
          </h2>
        </div>
      </div>

      <div
        v-if="props.invitationToken"
        class="mt-6 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border-strong)] bg-[rgba(40,86,144,0.14)] p-4 text-sm leading-6 text-[var(--ks-text-secondary)]"
      >
        {{ t('authExperience.login.invitationNotice') }}
      </div>

      <form class="mt-7 space-y-5" @submit.prevent="submit">
        <div>
          <label class="block text-sm font-semibold" for="email">{{ t('auth.login.email') }}</label>
          <input
            id="email"
            v-model="form.email"
            autocomplete="email"
            class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3.5 py-2.5 outline-none transition hover:border-[var(--ks-border-strong)] focus:border-[var(--ks-blue)]"
            required
            type="email"
          />
          <p v-if="form.errors.email" class="mt-2 text-sm text-rose-300" role="alert">
            {{ form.errors.email }}
          </p>
        </div>

        <div>
          <div class="flex items-center justify-between gap-3">
            <label class="block text-sm font-semibold" for="password">
              {{ t('auth.login.password') }}
            </label>
            <Link
              class="text-xs font-semibold text-[var(--ks-blue-light)] transition hover:text-[var(--ks-gold-strong)]"
              href="/forgot-password"
            >
              {{ t('auth.login.forgotPassword') }}
            </Link>
          </div>
          <input
            id="password"
            v-model="form.password"
            autocomplete="current-password"
            class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3.5 py-2.5 outline-none transition hover:border-[var(--ks-border-strong)] focus:border-[var(--ks-blue)]"
            required
            type="password"
          />
          <p v-if="form.errors.password" class="mt-2 text-sm text-rose-300" role="alert">
            {{ form.errors.password }}
          </p>
        </div>

        <label class="flex cursor-pointer items-center gap-3 text-sm text-[var(--ks-text-secondary)]">
          <input
            v-model="form.remember"
            class="h-4 w-4 rounded border-[var(--ks-border)] bg-[var(--ks-bg)] text-[var(--ks-gold)] focus:ring-[var(--ks-blue)]"
            type="checkbox"
          />
          {{ t('auth.login.remember') }}
        </label>

        <button
          class="w-full rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-5 py-3 font-bold text-slate-950 transition hover:bg-[var(--ks-gold-strong)] disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="form.processing"
          type="submit"
        >
          {{ t('auth.login.submit') }}
        </button>
      </form>

      <p class="mt-6 text-sm text-[var(--ks-text-muted)]">
        {{ t('authExperience.login.needAccount') }}
        <Link
          class="font-semibold text-[var(--ks-gold)] transition hover:text-[var(--ks-gold-strong)]"
          :href="
            props.invitationToken
              ? `/register?invitation=${encodeURIComponent(props.invitationToken)}`
              : '/register'
          "
        >
          {{ t('authExperience.login.register') }}
        </Link>
      </p>
    </div>
  </AuthLayout>
</template>
