<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

const props = defineProps<{
  user: {
    name: string;
    email: string;
    emailVerified: boolean;
    timezone: string;
    twoFactorEnabled: boolean;
    twoFactorPending: boolean;
  };
  twoFactorSetup: { secret: string; provisioning_uri: string } | null;
  twoFactorRecoveryCodes: string[] | null;
  status: string | null;
}>();

const { t } = useLocale();

const profileForm = useForm({
  name: props.user.name,
  email: props.user.email,
  timezone: props.user.timezone,
});

const passwordForm = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const sessionsForm = useForm({
  password: '',
});

const twoFactorForm = useForm({
  code: '',
});

const statusMessage = computed(() => {
  switch (props.status) {
    case 'password-updated':
      return t('accountExperience.account.passwordUpdated');
    case 'other-sessions-revoked':
      return t('accountExperience.account.sessionsRevoked');
    case 'two-factor-disabled':
      return t('accountExperience.account.twoFactorDisabled');
    default:
      return props.status;
  }
});

const twoFactorState = computed(() => {
  if (props.user.twoFactorEnabled) return t('accountExperience.account.enabled');
  if (props.user.twoFactorPending) return t('accountExperience.account.setupPending');
  return t('accountExperience.account.notEnabled');
});

function updateProfile(): void {
  profileForm.patch('/profile');
}

function updatePassword(): void {
  passwordForm.put('/profile/password', {
    onFinish: () => passwordForm.reset(),
  });
}

function revokeOtherSessions(): void {
  sessionsForm.delete('/profile/sessions/other', {
    onFinish: () => sessionsForm.reset(),
  });
}

function beginTwoFactor(): void {
  router.post('/profile/two-factor');
}

function confirmTwoFactor(): void {
  twoFactorForm.post('/profile/two-factor/confirm', {
    onFinish: () => twoFactorForm.reset(),
  });
}

function regenerateRecoveryCodes(): void {
  router.post('/profile/two-factor/recovery-codes');
}

function disableTwoFactor(): void {
  router.delete('/profile/two-factor');
}
</script>

<template>
  <Head :title="t('accountExperience.account.title')" />

  <AppLayout :user="{ name: props.user.name, email: props.user.email }">
    <header class="mb-8 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
      <div class="max-w-3xl">
        <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('accountExperience.account.eyebrow') }}
        </p>
        <h1 class="ks-display mt-2 text-3xl font-semibold sm:text-4xl">
          {{ t('accountExperience.account.title') }}
        </h1>
        <p class="mt-3 text-sm leading-6 text-[var(--ks-text-muted)] sm:text-base">
          {{ t('accountExperience.account.intro') }}
        </p>
      </div>
      <Link
        href="/profile/delete-account"
        class="inline-flex w-fit items-center justify-center rounded-[var(--ks-radius-sm)] border border-red-500/35 bg-red-500/5 px-4 py-2.5 text-sm font-semibold text-[var(--ks-red)] transition hover:bg-red-500/10"
      >
        {{ t('accountExperience.account.deleteAccount') }}
      </Link>
    </header>

    <p
      v-if="statusMessage"
      role="status"
      class="mb-6 rounded-[var(--ks-radius-md)] border border-emerald-500/25 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200"
    >
      {{ statusMessage }}
    </p>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(22rem,0.85fr)]">
      <section class="ks-surface p-5 sm:p-6">
        <div
          class="flex flex-col gap-2 border-b border-[var(--ks-border)] pb-5 sm:flex-row sm:items-start sm:justify-between"
        >
          <div>
            <h2 class="ks-display text-2xl font-semibold">
              {{ t('accountExperience.account.profileTitle') }}
            </h2>
            <p class="mt-1 text-sm text-[var(--ks-text-muted)]">
              {{ t('accountExperience.account.profileIntro') }}
            </p>
          </div>
          <div class="grid gap-1 text-xs text-[var(--ks-text-muted)] sm:text-end">
            <span>
              {{ t('accountExperience.account.emailVerification') }}:
              <strong class="text-[var(--ks-text-secondary)]">
                {{
                  props.user.emailVerified
                    ? t('accountExperience.account.verified')
                    : t('accountExperience.account.pending')
                }}
              </strong>
            </span>
            <span>
              {{ t('accountExperience.account.twoFactorState') }}:
              <strong class="text-[var(--ks-text-secondary)]">{{ twoFactorState }}</strong>
            </span>
          </div>
        </div>

        <form class="mt-6 grid gap-5 md:grid-cols-2" @submit.prevent="updateProfile">
          <div>
            <label class="block text-sm font-medium" for="profile-name">{{
              t('auth.register.name')
            }}</label>
            <input
              id="profile-name"
              v-model="profileForm.name"
              class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 transition focus:border-[var(--ks-blue)]"
              required
              type="text"
            />
            <p v-if="profileForm.errors.name" class="mt-1.5 text-sm text-[var(--ks-red)]">
              {{ profileForm.errors.name }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium" for="profile-email">{{
              t('auth.login.email')
            }}</label>
            <input
              id="profile-email"
              v-model="profileForm.email"
              class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 transition focus:border-[var(--ks-blue)]"
              required
              type="email"
            />
            <p v-if="profileForm.errors.email" class="mt-1.5 text-sm text-[var(--ks-red)]">
              {{ profileForm.errors.email }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium" for="profile-timezone">
              {{ t('accountExperience.account.timezone') }}
            </label>
            <input
              id="profile-timezone"
              v-model="profileForm.timezone"
              class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 transition focus:border-[var(--ks-blue)]"
              required
              type="text"
            />
            <p v-if="profileForm.errors.timezone" class="mt-1.5 text-sm text-[var(--ks-red)]">
              {{ profileForm.errors.timezone }}
            </p>
          </div>

          <div class="flex items-end">
            <button
              class="rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-4 py-2.5 font-bold text-[var(--ks-ink)] transition hover:bg-[var(--ks-gold-strong)] disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="profileForm.processing"
              type="submit"
            >
              {{ t('accountExperience.account.saveProfile') }}
            </button>
          </div>
        </form>
      </section>

      <section class="ks-surface-gold p-5 sm:p-6">
        <h2 class="ks-display text-2xl font-semibold">
          {{ t('accountExperience.account.twoFactorTitle') }}
        </h2>
        <p class="mt-2 text-sm leading-6 text-[var(--ks-text-muted)]">
          {{ t('accountExperience.account.twoFactorIntro') }}
        </p>

        <button
          v-if="!props.user.twoFactorEnabled && !props.twoFactorSetup"
          class="mt-6 rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-4 py-2.5 font-bold text-[var(--ks-ink)] transition hover:bg-[var(--ks-gold-strong)]"
          type="button"
          @click="beginTwoFactor"
        >
          {{ t('accountExperience.account.startSetup') }}
        </button>

        <div
          v-if="props.twoFactorSetup"
          class="mt-6 rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-bg)]/70 p-4"
        >
          <p class="text-sm font-semibold">
            {{ t('accountExperience.account.authenticatorSecret') }}
          </p>
          <p class="mt-2 font-mono text-sm break-all text-[var(--ks-blue-strong)]">
            {{ props.twoFactorSetup.secret }}
          </p>
          <p class="mt-4 text-xs text-[var(--ks-text-muted)]">
            {{ t('accountExperience.account.provisioningUri') }}
          </p>
          <p class="mt-1 font-mono text-xs break-all text-[var(--ks-text-secondary)]">
            {{ props.twoFactorSetup.provisioning_uri }}
          </p>

          <form class="mt-5 flex flex-col gap-3 sm:flex-row" @submit.prevent="confirmTwoFactor">
            <div class="flex-1">
              <label class="sr-only" for="profile-two-factor-code">{{
                t('accountExperience.account.authenticationCode')
              }}</label>
              <input
                id="profile-two-factor-code"
                v-model="twoFactorForm.code"
                autocomplete="one-time-code"
                class="w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5 font-mono transition focus:border-[var(--ks-blue)]"
                inputmode="numeric"
                maxlength="6"
                pattern="\d{6}"
                :placeholder="t('accountExperience.account.authenticationCode')"
                required
                type="text"
              />
            </div>
            <button
              class="rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-4 py-2.5 font-bold text-[var(--ks-ink)]"
              type="submit"
            >
              {{ t('accountExperience.account.confirm') }}
            </button>
          </form>
          <p v-if="twoFactorForm.errors.code" class="mt-2 text-sm text-[var(--ks-red)]">
            {{ twoFactorForm.errors.code }}
          </p>
        </div>

        <div
          v-if="props.twoFactorRecoveryCodes"
          class="mt-6 rounded-[var(--ks-radius-md)] border border-amber-500/25 bg-amber-500/10 p-4"
        >
          <p class="font-semibold text-amber-100">
            {{ t('accountExperience.account.saveRecoveryCodes') }}
          </p>
          <p class="mt-1 text-sm text-amber-100/75">
            {{ t('accountExperience.account.recoveryIntro') }}
          </p>
          <ul class="mt-4 grid gap-2 font-mono text-sm sm:grid-cols-2">
            <li
              v-for="code in props.twoFactorRecoveryCodes"
              :key="code"
              class="rounded border border-amber-500/15 bg-black/15 px-2 py-1.5"
            >
              {{ code }}
            </li>
          </ul>
        </div>

        <div v-if="props.user.twoFactorEnabled" class="mt-6 flex flex-wrap gap-3">
          <button
            class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-4 py-2.5 text-sm font-semibold transition hover:bg-[var(--ks-surface-2)]"
            type="button"
            @click="regenerateRecoveryCodes"
          >
            {{ t('accountExperience.account.regenerateRecoveryCodes') }}
          </button>
          <button
            class="rounded-[var(--ks-radius-sm)] border border-red-500/35 px-4 py-2.5 text-sm font-semibold text-[var(--ks-red)] transition hover:bg-red-500/10"
            type="button"
            @click="disableTwoFactor"
          >
            {{ t('accountExperience.account.disableTwoFactor') }}
          </button>
        </div>
      </section>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
      <section class="ks-surface p-5 sm:p-6">
        <h2 class="ks-display text-2xl font-semibold">
          {{ t('accountExperience.account.passwordTitle') }}
        </h2>
        <p class="mt-2 text-sm leading-6 text-[var(--ks-text-muted)]">
          {{ t('accountExperience.account.passwordIntro') }}
        </p>

        <form class="mt-6 grid gap-5" @submit.prevent="updatePassword">
          <div>
            <label class="block text-sm font-medium" for="current-password">{{
              t('accountExperience.account.currentPassword')
            }}</label>
            <input
              id="current-password"
              v-model="passwordForm.current_password"
              autocomplete="current-password"
              class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5"
              required
              type="password"
            />
            <p
              v-if="passwordForm.errors.current_password"
              class="mt-1.5 text-sm text-[var(--ks-red)]"
            >
              {{ passwordForm.errors.current_password }}
            </p>
          </div>
          <div>
            <label class="block text-sm font-medium" for="new-password">{{
              t('accountExperience.account.newPassword')
            }}</label>
            <input
              id="new-password"
              v-model="passwordForm.password"
              autocomplete="new-password"
              class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5"
              required
              type="password"
            />
            <p v-if="passwordForm.errors.password" class="mt-1.5 text-sm text-[var(--ks-red)]">
              {{ passwordForm.errors.password }}
            </p>
          </div>
          <div>
            <label class="block text-sm font-medium" for="new-password-confirmation">{{
              t('accountExperience.account.confirmNewPassword')
            }}</label>
            <input
              id="new-password-confirmation"
              v-model="passwordForm.password_confirmation"
              autocomplete="new-password"
              class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5"
              required
              type="password"
            />
          </div>
          <button
            class="w-fit rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-4 py-2.5 font-bold text-[var(--ks-ink)] disabled:opacity-60"
            :disabled="passwordForm.processing"
            type="submit"
          >
            {{ t('accountExperience.account.updatePassword') }}
          </button>
        </form>
      </section>

      <section class="ks-surface p-5 sm:p-6">
        <h2 class="ks-display text-2xl font-semibold">
          {{ t('accountExperience.account.sessionsTitle') }}
        </h2>
        <p class="mt-2 text-sm leading-6 text-[var(--ks-text-muted)]">
          {{ t('accountExperience.account.sessionsIntro') }}
        </p>
        <form class="mt-6 grid gap-4" @submit.prevent="revokeOtherSessions">
          <div>
            <label class="block text-sm font-medium" for="sessions-password">{{
              t('accountExperience.account.currentPassword')
            }}</label>
            <input
              id="sessions-password"
              v-model="sessionsForm.password"
              autocomplete="current-password"
              class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2.5"
              required
              type="password"
            />
            <p v-if="sessionsForm.errors.password" class="mt-1.5 text-sm text-[var(--ks-red)]">
              {{ sessionsForm.errors.password }}
            </p>
          </div>
          <button
            class="w-fit rounded-[var(--ks-radius-sm)] border border-red-500/35 px-4 py-2.5 font-semibold text-[var(--ks-red)] disabled:opacity-60"
            :disabled="sessionsForm.processing"
            type="submit"
          >
            {{ t('accountExperience.account.signOutOthers') }}
          </button>
        </form>
      </section>
    </div>

    <section
      class="mt-6 rounded-[var(--ks-radius-lg)] border border-red-500/25 bg-red-500/5 p-5 sm:p-6"
    >
      <p class="text-xs font-bold tracking-[0.18em] text-[var(--ks-red)] uppercase">
        {{ t('accountExperience.account.dangerTitle') }}
      </p>
      <div class="mt-2 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="ks-display text-2xl font-semibold">
            {{ t('accountExperience.account.deleteAccount') }}
          </h2>
          <p class="mt-1 text-sm text-[var(--ks-text-muted)]">
            {{ t('accountExperience.deletion.intro') }}
          </p>
        </div>
        <Link
          href="/profile/delete-account"
          class="inline-flex w-fit rounded-[var(--ks-radius-sm)] border border-red-500/35 px-4 py-2.5 text-sm font-semibold text-[var(--ks-red)] transition hover:bg-red-500/10"
        >
          {{ t('accountExperience.account.deleteAccount') }}
        </Link>
      </div>
    </section>
  </AppLayout>
</template>
