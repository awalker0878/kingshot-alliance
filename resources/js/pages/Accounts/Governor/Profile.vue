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
    pendingEmail: string | null;
    pendingEmailRequestedAt: string | null;
    timezone: string;
    authenticationType: 'password' | 'google';
    passwordAuthentication: boolean;
    googleAuthentication: boolean;
    providerEmail: string | null;
    twoFactorEnabled: boolean;
    twoFactorPending: boolean;
    recoveryCodeCount: number;
  };
  sessions: Array<{
    id: string;
    browser: string | null;
    platform: string | null;
    device: string | null;
    firstSeenAt: string;
    lastSeenAt: string;
    current: boolean;
  }>;
  securityActivity: Array<{
    id: string;
    event: string;
    labelKey: string;
    occurredAt: string;
  }>;
  twoFactorSetup: { secret: string; provisioning_uri: string } | null;
  twoFactorRecoveryCodes: string[] | null;
}>();

const { t, formatDate } = useLocale();

const profileForm = useForm({
  name: props.user.name,
  timezone: props.user.timezone,
});
const emailForm = useForm({ email: props.user.pendingEmail ?? '' });
const passwordForm = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
});
const twoFactorForm = useForm({ code: '' });

const twoFactorState = computed(() => {
  if (props.user.twoFactorEnabled) return t('accountExperience.account.enabled');
  if (props.user.twoFactorPending) return t('accountExperience.account.setupPending');
  return t('accountExperience.account.notEnabled');
});

const signInMethod = computed(() =>
  props.user.googleAuthentication
    ? t('accountExperience.account.googleSignIn')
    : t('accountExperience.account.passwordSignIn'),
);

function updateProfile(): void {
  profileForm.patch('/profile');
}

function requestEmailChange(): void {
  emailForm.patch('/profile/security/email');
}

function updatePassword(): void {
  passwordForm.put('/profile/password', { onFinish: () => passwordForm.reset() });
}

function revokeSession(sessionId: string): void {
  router.delete(`/profile/security/sessions/${sessionId}`);
}

function revokeOtherSessions(): void {
  router.delete('/profile/security/sessions');
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
        <Link href="/dashboard" class="ks-command-link">{{ t('navigation.dashboard') }}</Link>
        <Link href="/profile/connections" class="ks-command-link" data-variant="secondary">
          {{ t('accountExperience.connections.title') }}
        </Link>
      </template>
    </RoomBanner>

    <nav
      class="ks-surface mt-4 flex flex-wrap gap-2 p-2"
      :aria-label="t('accountExperience.account.settingsNavigation')"
    >
      <a class="ks-command-link" href="#profile">{{
        t('accountExperience.account.profileTitle')
      }}</a>
      <a class="ks-command-link" href="#security">{{
        t('accountExperience.account.securityTitle')
      }}</a>
      <a class="ks-command-link" href="#sessions">{{
        t('accountExperience.account.sessionsTitle')
      }}</a>
      <a class="ks-command-link" href="#account">{{
        t('accountExperience.account.accountTitle')
      }}</a>
    </nav>

    <section class="mt-4 grid gap-3 sm:grid-cols-3">
      <StatSeal
        :label="t('accountExperience.account.signInMethod')"
        :value="signInMethod"
        icon="⛨"
        tone="teal"
      />
      <StatSeal
        :label="t('accountExperience.account.emailVerification')"
        :value="
          props.user.emailVerified
            ? t('accountExperience.account.verified')
            : t('accountExperience.account.pending')
        "
        icon="✉"
        :tone="props.user.emailVerified ? 'teal' : 'stone'"
      />
      <StatSeal
        :label="t('accountExperience.account.twoFactorState')"
        :value="twoFactorState"
        icon="⌁"
        :tone="props.user.twoFactorEnabled ? 'teal' : 'stone'"
      />
    </section>

    <section
      id="profile"
      class="ks-surface mt-5 scroll-mt-4 p-5 sm:p-6"
      aria-labelledby="profile-heading"
    >
      <p class="ks-kicker">{{ t('accountExperience.account.profileTitle') }}</p>
      <h2 id="profile-heading" class="ks-display mt-1 text-2xl font-semibold">
        {{ props.user.name }}
      </h2>
      <p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
        {{ t('accountExperience.account.profileIntro') }}
      </p>

      <form class="mt-6 grid gap-5 md:grid-cols-2" @submit.prevent="updateProfile">
        <div>
          <label class="block text-sm font-semibold" for="profile-name">{{
            t('auth.register.name')
          }}</label>
          <input
            id="profile-name"
            v-model="profileForm.name"
            class="ks-input mt-2"
            required
            type="text"
          />
          <p
            v-if="profileForm.errors.name"
            class="mt-1.5 text-sm text-[var(--ks-red)]"
            role="alert"
          >
            {{ profileForm.errors.name }}
          </p>
        </div>
        <div>
          <label class="block text-sm font-semibold" for="profile-timezone">{{
            t('accountExperience.account.timezone')
          }}</label>
          <input
            id="profile-timezone"
            v-model="profileForm.timezone"
            class="ks-input mt-2"
            required
            type="text"
          />
          <p
            v-if="profileForm.errors.timezone"
            class="mt-1.5 text-sm text-[var(--ks-red)]"
            role="alert"
          >
            {{ profileForm.errors.timezone }}
          </p>
        </div>
        <div class="md:col-span-2">
          <p class="text-sm font-semibold">{{ t('auth.login.email') }}</p>
          <p class="mt-2 text-sm break-all text-[var(--ks-text-secondary)]">
            {{ props.user.email }}
          </p>
        </div>
        <div class="md:col-span-2">
          <AppButton type="submit" :disabled="profileForm.processing">{{
            t('accountExperience.account.saveProfile')
          }}</AppButton>
        </div>
      </form>
    </section>

    <section id="security" class="mt-5 grid scroll-mt-4 gap-5 xl:grid-cols-2" :aria-label="t('accountExperience.account.securityTitle')">
      <div class="ks-surface-gold p-5 sm:p-6">
        <p class="ks-kicker">{{ t('accountExperience.account.signInMethod') }}</p>
        <h2 class="ks-display mt-1 text-2xl font-semibold">{{ signInMethod }}</h2>
        <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{
            props.user.googleAuthentication
              ? t('accountExperience.account.googleSignInIntro')
              : t('accountExperience.account.passwordSignInIntro')
          }}
        </p>
        <p v-if="props.user.providerEmail" class="mt-3 text-sm break-all text-[var(--ks-muted)]">
          {{ t('accountExperience.account.providerEmail') }}: {{ props.user.providerEmail }}
        </p>

        <div class="mt-6 border-t border-[var(--ks-border)] pt-5">
          <p class="ks-kicker">{{ t('accountExperience.account.emailAddress') }}</p>
          <p class="mt-2 font-semibold break-all">{{ props.user.email }}</p>
          <div
            v-if="props.user.pendingEmail"
            class="mt-4 rounded-[var(--ks-radius-md)] border border-amber-400/25 bg-amber-500/[.07] p-4"
          >
            <p class="text-sm font-semibold text-amber-100">
              {{ t('accountExperience.account.pendingEmail') }}
            </p>
            <p class="mt-1 text-sm break-all text-amber-100/80">{{ props.user.pendingEmail }}</p>
            <p v-if="props.user.pendingEmailRequestedAt" class="mt-1 text-xs text-amber-100/65">
              {{ formatDate(props.user.pendingEmailRequestedAt) }}
            </p>
          </div>
          <form
            v-if="props.user.passwordAuthentication"
            class="mt-5 grid gap-3"
            @submit.prevent="requestEmailChange"
          >
            <label class="text-sm font-semibold" for="new-email">{{
              t('accountExperience.account.newEmail')
            }}</label>
            <input
              id="new-email"
              v-model="emailForm.email"
              class="ks-input"
              required
              type="email"
              autocomplete="email"
            />
            <p v-if="emailForm.errors.email" class="text-sm text-[var(--ks-red)]" role="alert">
              {{ emailForm.errors.email }}
            </p>
            <p class="text-xs leading-5 text-[var(--ks-muted)]">
              {{ t('accountExperience.account.emailChangeIntro') }}
            </p>
            <AppButton class="w-fit" type="submit" :disabled="emailForm.processing">{{
              t('accountExperience.account.requestEmailChange')
            }}</AppButton>
          </form>
          <p v-else class="mt-5 text-sm leading-6 text-[var(--ks-muted)]">
            {{ t('accountExperience.account.googleEmailManaged') }}
          </p>
        </div>
      </div>

      <div class="ks-surface p-5 sm:p-6">
        <p class="ks-kicker">{{ t('accountExperience.account.twoFactorTitle') }}</p>
        <h2 class="ks-display mt-1 text-2xl font-semibold">{{ twoFactorState }}</h2>
        <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('accountExperience.account.twoFactorIntro') }}
        </p>
        <p v-if="props.user.twoFactorEnabled" class="mt-2 text-xs text-[var(--ks-muted)]">
          {{ t('accountExperience.account.recoveryCodesRemaining') }}:
          {{ props.user.recoveryCodeCount }}
        </p>

        <AppButton
          v-if="!props.user.twoFactorEnabled && !props.twoFactorSetup"
          class="mt-6"
          type="button"
          @click="beginTwoFactor"
        >
          {{ t('accountExperience.account.startSetup') }}
        </AppButton>

        <div
          v-if="props.twoFactorSetup"
          class="mt-6 rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/20 p-4"
        >
          <p class="text-sm font-semibold">
            {{ t('accountExperience.account.authenticatorSecret') }}
          </p>
          <code class="mt-2 block text-sm break-all text-[var(--ks-teal-bright)]">{{
            props.twoFactorSetup.secret
          }}</code>
          <p class="mt-4 text-xs text-[var(--ks-muted)]">
            {{ t('accountExperience.account.provisioningUri') }}
          </p>
          <code class="mt-1 block text-xs break-all text-[var(--ks-text-secondary)]">{{
            props.twoFactorSetup.provisioning_uri
          }}</code>
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
          <p
            v-if="twoFactorForm.errors.code"
            class="mt-2 text-sm text-[var(--ks-red)]"
            role="alert"
          >
            {{ twoFactorForm.errors.code }}
          </p>
        </div>

        <div
          v-if="props.twoFactorRecoveryCodes"
          class="mt-6 rounded-[var(--ks-radius-md)] border border-amber-400/25 bg-amber-500/[.07] p-4"
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
              class="rounded border border-amber-400/15 bg-black/20 px-2 py-1.5"
            >
              {{ code }}
            </li>
          </ul>
        </div>

        <div v-if="props.user.twoFactorEnabled" class="mt-6 flex flex-wrap gap-3">
          <AppButton variant="ghost" type="button" @click="regenerateRecoveryCodes">{{
            t('accountExperience.account.regenerateRecoveryCodes')
          }}</AppButton>
          <AppButton variant="danger" type="button" @click="disableTwoFactor">{{
            t('accountExperience.account.disableTwoFactor')
          }}</AppButton>
        </div>
      </div>

      <div v-if="props.user.passwordAuthentication" class="ks-surface p-5 sm:p-6 xl:col-span-2">
        <p class="ks-kicker">{{ t('accountExperience.account.passwordTitle') }}</p>
        <h2 class="ks-display mt-1 text-xl font-semibold">
          {{ t('accountExperience.account.updatePassword') }}
        </h2>
        <p class="mt-2 text-sm leading-6 text-[var(--ks-muted)]">
          {{ t('accountExperience.account.passwordIntro') }}
        </p>
        <form class="mt-5 grid gap-4 lg:grid-cols-3" @submit.prevent="updatePassword">
          <div>
            <label class="text-sm font-semibold" for="current-password">{{
              t('accountExperience.account.currentPassword')
            }}</label>
            <input
              id="current-password"
              v-model="passwordForm.current_password"
              autocomplete="current-password"
              class="ks-input mt-2"
              required
              type="password"
            />
            <p
              v-if="passwordForm.errors.current_password"
              class="mt-1.5 text-sm text-[var(--ks-red)]"
              role="alert"
            >
              {{ passwordForm.errors.current_password }}
            </p>
          </div>
          <div>
            <label class="text-sm font-semibold" for="new-password">{{
              t('accountExperience.account.newPassword')
            }}</label>
            <input
              id="new-password"
              v-model="passwordForm.password"
              autocomplete="new-password"
              class="ks-input mt-2"
              required
              type="password"
            />
            <p
              v-if="passwordForm.errors.password"
              class="mt-1.5 text-sm text-[var(--ks-red)]"
              role="alert"
            >
              {{ passwordForm.errors.password }}
            </p>
          </div>
          <div>
            <label class="text-sm font-semibold" for="new-password-confirmation">{{
              t('accountExperience.account.confirmNewPassword')
            }}</label>
            <input
              id="new-password-confirmation"
              v-model="passwordForm.password_confirmation"
              autocomplete="new-password"
              class="ks-input mt-2"
              required
              type="password"
            />
          </div>
          <AppButton
            class="w-fit lg:col-span-3"
            type="submit"
            :disabled="passwordForm.processing"
            >{{ t('accountExperience.account.updatePassword') }}</AppButton
          >
        </form>
      </div>
    </section>

    <section id="sessions" class="mt-5 grid scroll-mt-4 gap-5 xl:grid-cols-2" :aria-label="t('accountExperience.account.sessionsTitle')">
      <div class="ks-surface p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p class="ks-kicker">{{ t('accountExperience.account.sessionsTitle') }}</p>
            <h2 class="ks-display mt-1 text-xl font-semibold">
              {{ t('accountExperience.account.activeSessions') }}
            </h2>
          </div>
          <AppButton
            v-if="props.sessions.some((session) => !session.current)"
            variant="danger"
            type="button"
            @click="revokeOtherSessions"
          >
            {{ t('accountExperience.account.signOutOthers') }}
          </AppButton>
        </div>
        <p class="mt-2 text-sm leading-6 text-[var(--ks-muted)]">
          {{ t('accountExperience.account.sessionsIntro') }}
        </p>
        <ul class="mt-5 grid gap-3">
          <li
            v-for="session in props.sessions"
            :key="session.id"
            class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4"
          >
            <div class="flex flex-wrap items-start justify-between gap-4">
              <div>
                <p class="font-semibold">
                  {{ session.browser ?? t('accountExperience.account.unknownBrowser') }} ·
                  {{ session.platform ?? t('accountExperience.account.unknownPlatform') }}
                </p>
                <p class="mt-1 text-sm text-[var(--ks-muted)]">
                  {{ session.device ?? t('accountExperience.account.unknownDevice') }}
                </p>
                <p class="mt-2 text-xs text-[var(--ks-muted)]">
                  {{ t('accountExperience.account.lastSeen') }}:
                  {{ formatDate(session.lastSeenAt) }}
                </p>
              </div>
              <div class="flex items-center gap-2">
                <span v-if="session.current" class="ks-status" data-tone="success">{{
                  t('accountExperience.account.currentSession')
                }}</span>
                <AppButton
                  v-else
                  variant="danger"
                  type="button"
                  @click="revokeSession(session.id)"
                  >{{ t('accountExperience.account.signOutSession') }}</AppButton
                >
              </div>
            </div>
          </li>
        </ul>
      </div>

      <div class="ks-surface p-5 sm:p-6">
        <p class="ks-kicker">{{ t('accountExperience.account.securityActivity') }}</p>
        <h2 class="ks-display mt-1 text-xl font-semibold">
          {{ t('accountExperience.account.recentSecurityActivity') }}
        </h2>
        <p class="mt-2 text-sm leading-6 text-[var(--ks-muted)]">
          {{ t('accountExperience.account.securityActivityIntro') }}
        </p>
        <ul v-if="props.securityActivity.length" class="mt-5 grid gap-3">
          <li
            v-for="activity in props.securityActivity"
            :key="activity.id"
            class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 px-4 py-3"
          >
            <p class="font-semibold">{{ t(activity.labelKey) }}</p>
            <p class="mt-1 text-xs text-[var(--ks-muted)]">{{ formatDate(activity.occurredAt) }}</p>
          </li>
        </ul>
        <p v-else class="mt-5 text-sm text-[var(--ks-muted)]">
          {{ t('accountExperience.account.noSecurityActivity') }}
        </p>
      </div>
    </section>

    <section
      id="account"
      class="mt-5 scroll-mt-4 rounded-[var(--ks-radius-lg)] border border-red-400/20 bg-red-500/[.035] p-5 sm:p-6"
    >
      <p class="ks-kicker text-[var(--ks-red)]">
        {{ t('accountExperience.account.accountTitle') }}
      </p>
      <div class="mt-2 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="ks-display text-2xl font-semibold">
            {{ t('accountExperience.account.deleteAccount') }}
          </h2>
          <p class="mt-1 text-sm text-[var(--ks-muted)]">
            {{ t('accountExperience.deletion.intro') }}
          </p>
        </div>
        <Link href="/profile/delete-account" class="ks-command-link" data-variant="secondary">{{
          t('accountExperience.account.deleteAccount')
        }}</Link>
      </div>
    </section>
  </AppLayout>
</template>
