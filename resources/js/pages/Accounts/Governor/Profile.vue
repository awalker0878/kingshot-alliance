<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import AppButton from '@/components/ui/AppButton.vue';
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
const sessionsForm = useForm({ password: '' });
const twoFactorForm = useForm({ code: '' });

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
  passwordForm.put('/profile/password', { onFinish: () => passwordForm.reset() });
}
function revokeOtherSessions(): void {
  sessionsForm.delete('/profile/sessions/other', { onFinish: () => sessionsForm.reset() });
}
function beginTwoFactor(): void {
  router.post('/profile/two-factor');
}
function confirmTwoFactor(): void {
  twoFactorForm.post('/profile/two-factor/confirm', { onFinish: () => twoFactorForm.reset() });
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
    <RoomBanner
      :eyebrow="t('accountExperience.account.eyebrow')"
      :title="t('accountExperience.account.title')"
      :subtitle="t('accountExperience.account.intro')"
      image="/images/kingshot/v4/account-vault.svg"
      compact
    >
      <template #actions>
        <Link href="/dashboard" class="ks-command-link">
          {{ t('navigation.dashboard') }}
        </Link>
        <Link
          href="/profile/delete-account"
          class="ks-command-link"
          data-variant="secondary"
        >
          {{ t('accountExperience.account.deleteAccount') }}
        </Link>
      </template>
    </RoomBanner>

    <section class="mt-4 grid gap-3 sm:grid-cols-3">
      <StatSeal
        :label="t('accountExperience.account.emailVerification')"
        :value="props.user.emailVerified ? t('accountExperience.account.verified') : t('accountExperience.account.pending')"
        icon="✉"
        :tone="props.user.emailVerified ? 'teal' : 'stone'"
      />
      <StatSeal
        :label="t('accountExperience.account.twoFactorState')"
        :value="twoFactorState"
        icon="⛨"
        :tone="props.user.twoFactorEnabled ? 'teal' : 'stone'"
      />
      <StatSeal
        :label="t('accountExperience.account.timezone')"
        :value="props.user.timezone"
        icon="◷"
      />
    </section>

    <p
      v-if="statusMessage"
      role="status"
      class="mt-5 rounded-[var(--ks-radius-md)] border border-emerald-400/25 bg-emerald-500/[.07] px-4 py-3 text-sm text-emerald-100"
    >
      {{ statusMessage }}
    </p>

    <div class="mt-5 grid gap-5 2xl:grid-cols-[minmax(0,1.05fr)_minmax(22rem,.95fr)]">
      <section class="ks-surface p-5 sm:p-6" aria-labelledby="platform-profile-heading">
        <p class="ks-kicker">{{ t('accountExperience.account.profileTitle') }}</p>
        <h2 id="platform-profile-heading" class="ks-display mt-1 text-2xl font-semibold">
          {{ props.user.name }}
        </h2>
        <p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('accountExperience.account.profileIntro') }}
        </p>

        <div class="mt-5 rounded-[var(--ks-radius-md)] border border-[rgba(32,178,163,.25)] bg-[var(--ks-teal-soft)] p-4">
          <p class="ks-kicker">{{ t('common.currentPlayer') }}</p>
          <p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
            {{ t('application.dashboard.playerContextIntro') }}
          </p>
        </div>

        <form class="mt-6 grid gap-5 md:grid-cols-2" @submit.prevent="updateProfile">
          <div>
            <label class="block text-sm font-semibold" for="profile-name">{{ t('auth.register.name') }}</label>
            <input id="profile-name" v-model="profileForm.name" class="ks-input mt-2" required type="text" />
            <p v-if="profileForm.errors.name" class="mt-1.5 text-sm text-[var(--ks-red)]">{{ profileForm.errors.name }}</p>
          </div>
          <div>
            <label class="block text-sm font-semibold" for="profile-email">{{ t('auth.login.email') }}</label>
            <input id="profile-email" v-model="profileForm.email" class="ks-input mt-2" required type="email" />
            <p v-if="profileForm.errors.email" class="mt-1.5 text-sm text-[var(--ks-red)]">{{ profileForm.errors.email }}</p>
          </div>
          <div>
            <label class="block text-sm font-semibold" for="profile-timezone">{{ t('accountExperience.account.timezone') }}</label>
            <input id="profile-timezone" v-model="profileForm.timezone" class="ks-input mt-2" required type="text" />
            <p v-if="profileForm.errors.timezone" class="mt-1.5 text-sm text-[var(--ks-red)]">{{ profileForm.errors.timezone }}</p>
          </div>
          <div class="flex items-end">
            <AppButton type="submit" :disabled="profileForm.processing">
              {{ t('accountExperience.account.saveProfile') }}
            </AppButton>
          </div>
        </form>
      </section>

      <section class="ks-surface-gold p-5 sm:p-6" aria-labelledby="two-factor-heading">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="ks-kicker">{{ t('accountExperience.account.twoFactorTitle') }}</p>
            <h2 id="two-factor-heading" class="ks-display mt-1 text-2xl font-semibold">
              {{ twoFactorState }}
            </h2>
          </div>
          <span class="ks-status" :data-tone="props.user.twoFactorEnabled ? 'success' : 'warning'">
            {{ twoFactorState }}
          </span>
        </div>
        <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('accountExperience.account.twoFactorIntro') }}
        </p>

        <AppButton
          v-if="!props.user.twoFactorEnabled && !props.twoFactorSetup"
          class="mt-6"
          type="button"
          @click="beginTwoFactor"
        >
          {{ t('accountExperience.account.startSetup') }}
        </AppButton>

        <div v-if="props.twoFactorSetup" class="mt-6 rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/20 p-4">
          <p class="text-sm font-semibold">{{ t('accountExperience.account.authenticatorSecret') }}</p>
          <code class="mt-2 block break-all text-sm text-[var(--ks-teal-bright)]">{{ props.twoFactorSetup.secret }}</code>
          <p class="mt-4 text-xs text-[var(--ks-muted)]">{{ t('accountExperience.account.provisioningUri') }}</p>
          <code class="mt-1 block break-all text-xs text-[var(--ks-text-secondary)]">{{ props.twoFactorSetup.provisioning_uri }}</code>
          <form class="mt-5 grid gap-3 sm:grid-cols-[1fr_auto]" @submit.prevent="confirmTwoFactor">
            <input
              v-model="twoFactorForm.code"
              autocomplete="one-time-code"
              class="ks-input text-center font-mono tracking-[.24em]"
              inputmode="numeric"
              maxlength="6"
              pattern="\d{6}"
              :placeholder="t('accountExperience.account.authenticationCode')"
              required
              type="text"
            />
            <AppButton type="submit">{{ t('accountExperience.account.confirm') }}</AppButton>
          </form>
          <p v-if="twoFactorForm.errors.code" class="mt-2 text-sm text-[var(--ks-red)]">{{ twoFactorForm.errors.code }}</p>
        </div>

        <div v-if="props.twoFactorRecoveryCodes" class="mt-6 rounded-[var(--ks-radius-md)] border border-amber-400/25 bg-amber-500/[.07] p-4">
          <p class="font-semibold text-amber-100">{{ t('accountExperience.account.saveRecoveryCodes') }}</p>
          <p class="mt-1 text-sm text-amber-100/75">{{ t('accountExperience.account.recoveryIntro') }}</p>
          <ul class="mt-4 grid gap-2 font-mono text-sm sm:grid-cols-2">
            <li v-for="code in props.twoFactorRecoveryCodes" :key="code" class="rounded border border-amber-400/15 bg-black/20 px-2 py-1.5">
              {{ code }}
            </li>
          </ul>
        </div>

        <div v-if="props.user.twoFactorEnabled" class="mt-6 flex flex-wrap gap-3">
          <AppButton variant="ghost" type="button" @click="regenerateRecoveryCodes">
            {{ t('accountExperience.account.regenerateRecoveryCodes') }}
          </AppButton>
          <AppButton variant="danger" type="button" @click="disableTwoFactor">
            {{ t('accountExperience.account.disableTwoFactor') }}
          </AppButton>
        </div>
      </section>
    </div>

    <div class="mt-5 grid gap-5 xl:grid-cols-2">
      <section class="ks-surface p-5 sm:p-6" aria-labelledby="password-heading">
        <p class="ks-kicker">{{ t('accountExperience.account.passwordTitle') }}</p>
        <h2 id="password-heading" class="ks-display mt-1 text-xl font-semibold">
          {{ t('accountExperience.account.updatePassword') }}
        </h2>
        <p class="mt-2 text-sm leading-6 text-[var(--ks-muted)]">{{ t('accountExperience.account.passwordIntro') }}</p>
        <form class="mt-5 grid gap-4" @submit.prevent="updatePassword">
          <div>
            <label class="text-sm font-semibold" for="current-password">{{ t('accountExperience.account.currentPassword') }}</label>
            <input id="current-password" v-model="passwordForm.current_password" autocomplete="current-password" class="ks-input mt-2" required type="password" />
            <p v-if="passwordForm.errors.current_password" class="mt-1.5 text-sm text-[var(--ks-red)]">{{ passwordForm.errors.current_password }}</p>
          </div>
          <div>
            <label class="text-sm font-semibold" for="new-password">{{ t('accountExperience.account.newPassword') }}</label>
            <input id="new-password" v-model="passwordForm.password" autocomplete="new-password" class="ks-input mt-2" required type="password" />
            <p v-if="passwordForm.errors.password" class="mt-1.5 text-sm text-[var(--ks-red)]">{{ passwordForm.errors.password }}</p>
          </div>
          <div>
            <label class="text-sm font-semibold" for="new-password-confirmation">{{ t('accountExperience.account.confirmNewPassword') }}</label>
            <input id="new-password-confirmation" v-model="passwordForm.password_confirmation" autocomplete="new-password" class="ks-input mt-2" required type="password" />
          </div>
          <AppButton class="w-fit" type="submit" :disabled="passwordForm.processing">
            {{ t('accountExperience.account.updatePassword') }}
          </AppButton>
        </form>
      </section>

      <section class="ks-surface p-5 sm:p-6" aria-labelledby="sessions-heading">
        <p class="ks-kicker">{{ t('accountExperience.account.sessionsTitle') }}</p>
        <h2 id="sessions-heading" class="ks-display mt-1 text-xl font-semibold">
          {{ t('accountExperience.account.signOutOthers') }}
        </h2>
        <p class="mt-2 text-sm leading-6 text-[var(--ks-muted)]">{{ t('accountExperience.account.sessionsIntro') }}</p>
        <form class="mt-5 grid gap-4" @submit.prevent="revokeOtherSessions">
          <div>
            <label class="text-sm font-semibold" for="sessions-password">{{ t('accountExperience.account.currentPassword') }}</label>
            <input id="sessions-password" v-model="sessionsForm.password" autocomplete="current-password" class="ks-input mt-2" required type="password" />
            <p v-if="sessionsForm.errors.password" class="mt-1.5 text-sm text-[var(--ks-red)]">{{ sessionsForm.errors.password }}</p>
          </div>
          <AppButton class="w-fit" variant="danger" type="submit" :disabled="sessionsForm.processing">
            {{ t('accountExperience.account.signOutOthers') }}
          </AppButton>
        </form>
      </section>
    </div>

    <section class="mt-5 rounded-[var(--ks-radius-lg)] border border-red-400/20 bg-red-500/[.035] p-5 sm:p-6">
      <p class="ks-kicker text-[var(--ks-red)]">{{ t('accountExperience.account.dangerTitle') }}</p>
      <div class="mt-2 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="ks-display text-2xl font-semibold">{{ t('accountExperience.account.deleteAccount') }}</h2>
          <p class="mt-1 text-sm text-[var(--ks-muted)]">{{ t('accountExperience.deletion.intro') }}</p>
        </div>
        <Link href="/profile/delete-account" class="ks-command-link" data-variant="secondary">
          {{ t('accountExperience.account.deleteAccount') }}
        </Link>
      </div>
    </section>
  </AppLayout>
</template>
