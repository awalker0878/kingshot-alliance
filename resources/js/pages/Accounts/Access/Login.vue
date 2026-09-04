<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { usePasskeyVerify } from '@laravel/passkeys/vue';
import { computed, unref } from 'vue';

import AppButton from '@/components/ui/AppButton.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useLocale } from '@/localization';

const props = defineProps<{
  invitationToken: string | null;
  googleAuthEnabled: boolean;
  passkeyAuthEnabled: boolean;
}>();
const { t } = useLocale();

const form = useForm({
  email: '',
  password: '',
  remember: false,
  invitation_token: props.invitationToken ?? '',
});

const {
  verify: verifyPasskey,
  isLoading: passkeyLoading,
  error: passkeyError,
  isSupported: passkeySupported,
} = usePasskeyVerify({
  autofill: props.passkeyAuthEnabled,
  remember: () => form.remember,
  onSuccess: (response) => {
    if (response.redirect) router.visit(response.redirect);
  },
});

const passkeyAvailable = computed(
  () => props.passkeyAuthEnabled && Boolean(unref(passkeySupported)),
);
const alternativeSignInAvailable = computed(
  () => passkeyAvailable.value || props.googleAuthEnabled,
);

const googleAuthUrl = props.invitationToken
  ? `/auth/google?intent=login&invitation=${encodeURIComponent(props.invitationToken)}`
  : '/auth/google?intent=login';

function submit(): void {
  form.post('/login', { onFinish: () => form.reset('password') });
}
</script>

<template>
  <Head :title="t('auth.login.title')" />

  <AuthLayout>
    <div class="flex items-start gap-4">
      <div
        class="grid h-14 w-12 shrink-0 place-items-center border border-[var(--ks-gold-dark)] bg-[linear-gradient(160deg,#126b64,#092d2a)] text-xl font-[var(--ks-font-display)] text-[var(--ks-gold-bright)] [clip-path:polygon(50%_0,95%_16%,86%_77%,50%_100%,14%_77%,5%_16%)]"
        aria-hidden="true"
      >
        ♛
      </div>
      <div>
        <p class="ks-kicker">{{ t('common.appName') }}</p>
        <h2 class="ks-display mt-1 text-3xl font-semibold sm:text-4xl">
          {{ t('auth.login.title') }}
        </h2>
        <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('authExperience.login.intro') }}
        </p>
      </div>
    </div>

    <div
      v-if="props.invitationToken"
      class="mt-6 rounded-[var(--ks-radius-md)] border border-[rgba(32,178,163,.3)] bg-[var(--ks-teal-soft)] p-4 text-sm leading-6 text-[var(--ks-text-secondary)]"
    >
      {{ t('authExperience.login.invitationNotice') }}
    </div>

    <div v-if="alternativeSignInAvailable" class="mt-7 grid gap-3">
      <AppButton
        v-if="passkeyAvailable"
        type="button"
        :disabled="passkeyLoading"
        @click="verifyPasskey"
      >
        {{
          passkeyLoading
            ? t('authExperience.passkeys.authenticating')
            : t('authExperience.passkeys.signIn')
        }}
      </AppButton>
      <p
        v-if="passkeyAvailable && passkeyError"
        class="text-sm text-[var(--ks-red)]"
        role="alert"
      >
        {{ passkeyError }}
      </p>

      <a
        v-if="props.googleAuthEnabled"
        :href="googleAuthUrl"
        class="flex w-full items-center justify-center gap-3 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-white px-4 py-3 text-sm font-semibold text-slate-900 transition hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--ks-gold-bright)]"
      >
        {{ t('authExperience.social.continueWithGoogle') }}
      </a>
    </div>

    <div v-if="alternativeSignInAvailable" class="my-6 flex items-center gap-3" aria-hidden="true">
      <span class="h-px flex-1 bg-[var(--ks-border)]" />
      <span class="text-xs tracking-[0.16em] text-[var(--ks-muted)] uppercase">{{
        t('authExperience.social.or')
      }}</span>
      <span class="h-px flex-1 bg-[var(--ks-border)]" />
    </div>

    <form
      :class="alternativeSignInAvailable ? 'space-y-5' : 'mt-7 space-y-5'"
      @submit.prevent="submit"
    >
      <div>
        <label class="block text-sm font-semibold" for="email">{{ t('auth.login.email') }}</label>
        <input
          id="email"
          v-model="form.email"
          :autocomplete="props.passkeyAuthEnabled ? 'email webauthn' : 'email'"
          class="ks-input mt-2"
          required
          type="email"
        />
        <p v-if="form.errors.email" class="mt-2 text-sm text-[var(--ks-red)]" role="alert">
          {{ form.errors.email }}
        </p>
      </div>
      <div>
        <div class="flex items-center justify-between gap-3">
          <label class="block text-sm font-semibold" for="password">{{
            t('auth.login.password')
          }}</label>
          <Link
            class="text-xs font-semibold text-[var(--ks-teal-bright)] transition hover:text-[var(--ks-gold-bright)]"
            href="/forgot-password"
            >{{ t('auth.login.forgotPassword') }}</Link
          >
        </div>
        <input
          id="password"
          v-model="form.password"
          autocomplete="current-password"
          class="ks-input mt-2"
          required
          type="password"
        />
        <p v-if="form.errors.password" class="mt-2 text-sm text-[var(--ks-red)]" role="alert">
          {{ form.errors.password }}
        </p>
      </div>
      <label
        class="flex cursor-pointer items-center gap-3 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/15 px-3 py-2.5 text-sm text-[var(--ks-text-secondary)]"
      >
        <input
          v-model="form.remember"
          class="h-4 w-4 accent-[var(--ks-teal-bright)]"
          type="checkbox"
        />
        {{ t('auth.login.remember') }}
      </label>
      <AppButton class="w-full" type="submit" :disabled="form.processing">{{
        t('auth.login.submit')
      }}</AppButton>
    </form>

    <div class="ks-divider my-6" />
    <p class="text-center text-sm text-[var(--ks-muted)]">
      {{ t('authExperience.login.needAccount') }}
      <Link
        class="font-semibold text-[var(--ks-gold-bright)] transition hover:text-[var(--ks-ivory)]"
        :href="
          props.invitationToken
            ? `/register?invitation=${encodeURIComponent(props.invitationToken)}`
            : '/register'
        "
        >{{ t('authExperience.login.register') }}</Link
      >
    </p>
  </AuthLayout>
</template>
