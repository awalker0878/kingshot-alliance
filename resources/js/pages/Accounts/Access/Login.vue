<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import AppButton from '@/components/ui/AppButton.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useLocale } from '@/localization';

const props = defineProps<{
  invitationToken: string | null;
  googleAuthEnabled: boolean;
}>();
const { t } = useLocale();

const form = useForm({
  email: '',
  password: '',
  remember: false,
  invitation_token: props.invitationToken ?? '',
});

const googleAuthUrl = props.invitationToken
  ? `/auth/google?invitation=${encodeURIComponent(props.invitationToken)}`
  : '/auth/google';

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

    <a
      v-if="props.googleAuthEnabled"
      :href="googleAuthUrl"
      class="mt-7 flex w-full items-center justify-center gap-3 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-white px-4 py-3 text-sm font-semibold text-slate-900 transition hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--ks-gold-bright)]"
    >
      <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24">
        <path
          fill="#4285F4"
          d="M21.6 12.23c0-.71-.06-1.39-.18-2.05H12v3.88h5.38a4.6 4.6 0 0 1-2 3.02v2.51h3.24c1.9-1.75 2.98-4.33 2.98-7.36Z"
        />
        <path
          fill="#34A853"
          d="M12 22c2.7 0 4.97-.9 6.62-2.41l-3.24-2.51c-.9.6-2.05.96-3.38.96-2.61 0-4.82-1.76-5.61-4.13H3.04v2.59A10 10 0 0 0 12 22Z"
        />
        <path
          fill="#FBBC05"
          d="M6.39 13.91A6.02 6.02 0 0 1 6.08 12c0-.66.11-1.31.31-1.91V7.5H3.04A10 10 0 0 0 2 12c0 1.61.38 3.14 1.04 4.5l3.35-2.59Z"
        />
        <path
          fill="#EA4335"
          d="M12 5.96c1.47 0 2.79.51 3.83 1.5l2.87-2.88A9.63 9.63 0 0 0 12 2 10 10 0 0 0 3.04 7.5l3.35 2.59C7.18 7.72 9.39 5.96 12 5.96Z"
        />
      </svg>
      Continue with Google
    </a>

    <div v-if="props.googleAuthEnabled" class="my-6 flex items-center gap-3" aria-hidden="true">
      <span class="h-px flex-1 bg-[var(--ks-border)]" />
      <span class="text-xs tracking-[0.16em] text-[var(--ks-muted)] uppercase">or</span>
      <span class="h-px flex-1 bg-[var(--ks-border)]" />
    </div>

    <form
      :class="props.googleAuthEnabled ? 'space-y-5' : 'mt-7 space-y-5'"
      @submit.prevent="submit"
    >
      <div>
        <label class="block text-sm font-semibold" for="email">{{ t('auth.login.email') }}</label>
        <input
          id="email"
          v-model="form.email"
          autocomplete="email"
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
          >
            {{ t('auth.login.forgotPassword') }}
          </Link>
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

      <AppButton class="w-full" type="submit" :disabled="form.processing">
        {{ t('auth.login.submit') }}
      </AppButton>
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
      >
        {{ t('authExperience.login.register') }}
      </Link>
    </p>
  </AuthLayout>
</template>
