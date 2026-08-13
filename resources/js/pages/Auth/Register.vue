<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import AuthLayout from '../../layouts/AuthLayout.vue';
import { useLocale } from '../../localization';

const props = defineProps<{
  registrationMode: string;
  invitationToken: string | null;
  invitedEmail: string | null;
  invitedAllianceName: string | null;
}>();

const { t } = useLocale();

const form = useForm({
  name: '',
  email: props.invitedEmail ?? '',
  password: '',
  password_confirmation: '',
  timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
  invitation_token: props.invitationToken ?? '',
});

function submit(): void {
  form.post('/register', {
    onFinish: () => form.reset('password', 'password_confirmation'),
  });
}
</script>

<template>
  <Head :title="t('auth.register.title')" />

  <AuthLayout>
    <template #headline>{{ t('auth.register.title') }}</template>
    <template #intro>{{ t('authExperience.register.intro') }}</template>

    <div>
      <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
        {{ t('common.appName') }}
      </p>
      <h2 class="ks-display mt-2 text-2xl font-semibold sm:text-3xl">
        {{ t('auth.register.title') }}
      </h2>

      <div
        v-if="props.invitationToken"
        class="mt-6 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border-strong)] bg-[rgba(40,86,144,0.14)] p-4 text-sm leading-6 text-[var(--ks-text-secondary)]"
      >
        {{
          t('authExperience.register.invitationNotice', {
            alliance: props.invitedAllianceName ?? '',
            email: props.invitedEmail ?? '',
          })
        }}
      </div>

      <div
        v-else-if="props.registrationMode !== 'open'"
        class="mt-6 rounded-[var(--ks-radius-sm)] border border-amber-700/60 bg-amber-950/25 p-4 text-sm leading-6 text-amber-100"
      >
        {{ t('authExperience.register.invitationOnly') }}
      </div>

      <form
        v-if="props.registrationMode === 'open' || props.invitationToken"
        class="mt-7 space-y-5"
        @submit.prevent="submit"
      >
        <div class="grid gap-5 sm:grid-cols-2">
          <div>
            <label class="block text-sm font-semibold" for="name">{{
              t('auth.register.name')
            }}</label>
            <input
              id="name"
              v-model="form.name"
              autocomplete="name"
              class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3.5 py-2.5 transition outline-none hover:border-[var(--ks-border-strong)] focus:border-[var(--ks-blue)]"
              required
              type="text"
            />
            <p v-if="form.errors.name" class="mt-2 text-sm text-rose-300" role="alert">
              {{ form.errors.name }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-semibold" for="timezone">
              {{ t('authExperience.register.timezone') }}
            </label>
            <input
              id="timezone"
              v-model="form.timezone"
              class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3.5 py-2.5 transition outline-none hover:border-[var(--ks-border-strong)] focus:border-[var(--ks-blue)]"
              required
              type="text"
            />
            <p v-if="form.errors.timezone" class="mt-2 text-sm text-rose-300" role="alert">
              {{ form.errors.timezone }}
            </p>
          </div>
        </div>

        <div>
          <label class="block text-sm font-semibold" for="email">{{
            t('auth.register.email')
          }}</label>
          <input
            id="email"
            v-model="form.email"
            autocomplete="email"
            class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3.5 py-2.5 transition outline-none hover:border-[var(--ks-border-strong)] focus:border-[var(--ks-blue)] disabled:cursor-not-allowed disabled:opacity-70"
            :disabled="Boolean(props.invitationToken)"
            required
            type="email"
          />
          <input v-if="props.invitationToken" :value="form.email" name="email" type="hidden" />
          <p v-if="form.errors.email" class="mt-2 text-sm text-rose-300" role="alert">
            {{ form.errors.email }}
          </p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
          <div>
            <label class="block text-sm font-semibold" for="password">
              {{ t('auth.register.password') }}
            </label>
            <input
              id="password"
              v-model="form.password"
              autocomplete="new-password"
              class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3.5 py-2.5 transition outline-none hover:border-[var(--ks-border-strong)] focus:border-[var(--ks-blue)]"
              minlength="12"
              required
              type="password"
            />
            <p class="mt-2 text-xs leading-5 text-[var(--ks-text-muted)]">
              {{ t('authExperience.register.passwordHint') }}
            </p>
            <p v-if="form.errors.password" class="mt-2 text-sm text-rose-300" role="alert">
              {{ form.errors.password }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-semibold" for="password_confirmation">
              {{ t('auth.register.passwordConfirmation') }}
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
        </div>

        <button
          class="w-full rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-5 py-3 font-bold text-slate-950 transition hover:bg-[var(--ks-gold-strong)] disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="form.processing"
          type="submit"
        >
          {{ t('auth.register.submit') }}
        </button>
      </form>

      <p class="mt-6 text-sm text-[var(--ks-text-muted)]">
        {{ t('authExperience.register.existingAccount') }}
        <Link
          class="font-semibold text-[var(--ks-gold)] transition hover:text-[var(--ks-gold-strong)]"
          href="/login"
        >
          {{ t('common.signIn') }}
        </Link>
      </p>
    </div>
  </AuthLayout>
</template>
