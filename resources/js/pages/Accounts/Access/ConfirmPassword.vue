<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { usePasskeyVerify } from '@laravel/passkeys/vue';

import AppButton from '@/components/ui/AppButton.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useLocale } from '@/localization';

const props = defineProps<{
  methods: { password: boolean; google: boolean; passkey: boolean };
  googleAuthEnabled: boolean;
}>();

const { t } = useLocale();
const form = useForm({ password: '' });
const { verify: verifyPasskey, isLoading: passkeyLoading, error: passkeyError, isSupported: passkeySupported } =
  usePasskeyVerify({
    routes: { options: '/passkeys/confirm/options', submit: '/passkeys/confirm' },
    onSuccess: (response) => {
      if (response.redirect) router.visit(response.redirect);
    },
  });

function submit(): void {
  form.post('/confirm-password', { onFinish: () => form.reset('password') });
}
</script>

<template>
  <Head :title="t('authExperience.confirm.title')" />
  <AuthLayout>
    <p class="ks-kicker">{{ t('common.appName') }}</p>
    <h2 class="ks-display mt-2 text-3xl font-semibold sm:text-4xl">{{ t('authExperience.confirm.title') }}</h2>
    <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">{{ t('authExperience.confirm.description') }}</p>

    <div class="mt-7 grid gap-4">
      <AppButton v-if="props.methods.passkey && passkeySupported" type="button" :disabled="passkeyLoading" @click="verifyPasskey">
        {{ passkeyLoading ? t('authExperience.passkeys.authenticating') : t('authExperience.confirm.usePasskey') }}
      </AppButton>
      <p v-if="passkeyError" class="text-sm text-[var(--ks-red)]" role="alert">{{ passkeyError }}</p>

      <a v-if="props.methods.google && props.googleAuthEnabled" href="/auth/google/reauthenticate" class="ks-command-link justify-center">{{ t('authExperience.confirm.useGoogle') }}</a>

      <form v-if="props.methods.password" class="space-y-4" @submit.prevent="submit">
        <label class="block text-sm font-semibold" for="confirm-password">{{ t('auth.login.password') }}</label>
        <input id="confirm-password" v-model="form.password" autocomplete="current-password" class="ks-input" required type="password" />
        <p v-if="form.errors.password" class="text-sm text-[var(--ks-red)]" role="alert">{{ form.errors.password }}</p>
        <AppButton class="w-full" type="submit" :disabled="form.processing">{{ t('authExperience.confirm.usePassword') }}</AppButton>
      </form>
    </div>
  </AuthLayout>
</template>
