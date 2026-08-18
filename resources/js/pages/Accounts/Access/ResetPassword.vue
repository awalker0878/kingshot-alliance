<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import AppButton from '@/components/ui/AppButton.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useLocale } from '@/localization';

const props = defineProps<{ token: string; email: string }>();
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
    <p class="ks-kicker">{{ t('common.appName') }}</p>
    <h2 class="ks-display mt-2 text-3xl font-semibold sm:text-4xl">
      {{ t('auth.password.resetTitle') }}
    </h2>
    <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
      {{ t('authExperience.password.resetIntro') }}
    </p>

    <form class="mt-7 space-y-5" @submit.prevent="submit">
      <div>
        <label class="block text-sm font-semibold" for="email">{{ t('auth.login.email') }}</label>
        <input id="email" v-model="form.email" autocomplete="email" class="ks-input mt-2" required type="email" />
        <p v-if="form.errors.email" class="mt-2 text-sm text-[var(--ks-red)]" role="alert">{{ form.errors.email }}</p>
      </div>
      <div>
        <label class="block text-sm font-semibold" for="password">{{ t('authExperience.password.newPassword') }}</label>
        <input id="password" v-model="form.password" autocomplete="new-password" class="ks-input mt-2" required type="password" />
        <p v-if="form.errors.password" class="mt-2 text-sm text-[var(--ks-red)]" role="alert">{{ form.errors.password }}</p>
      </div>
      <div>
        <label class="block text-sm font-semibold" for="password_confirmation">{{ t('authExperience.password.confirmNewPassword') }}</label>
        <input id="password_confirmation" v-model="form.password_confirmation" autocomplete="new-password" class="ks-input mt-2" required type="password" />
      </div>
      <AppButton class="w-full" type="submit" :disabled="form.processing">
        {{ t('auth.password.resetSubmit') }}
      </AppButton>
    </form>
  </AuthLayout>
</template>
