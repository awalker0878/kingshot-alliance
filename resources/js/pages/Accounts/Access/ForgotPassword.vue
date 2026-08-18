<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import AppButton from '@/components/ui/AppButton.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useLocale } from '@/localization';

const props = defineProps<{ status: string | null }>();
const { t } = useLocale();
const form = useForm({ email: '' });

function submit(): void {
  form.post('/forgot-password');
}
</script>

<template>
  <Head :title="t('auth.password.forgotTitle')" />

  <AuthLayout>
    <p class="ks-kicker">{{ t('common.appName') }}</p>
    <h2 class="ks-display mt-2 text-3xl font-semibold sm:text-4xl">
      {{ t('auth.password.forgotTitle') }}
    </h2>
    <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
      {{ t('auth.password.forgotDescription') }}
    </p>

    <p
      v-if="props.status"
      class="mt-6 rounded-[var(--ks-radius-md)] border border-emerald-400/25 bg-emerald-500/[.07] p-4 text-sm leading-6 text-emerald-100"
      role="status"
    >
      {{ props.status }}
    </p>

    <form class="mt-7 space-y-5" @submit.prevent="submit">
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
      <AppButton class="w-full" type="submit" :disabled="form.processing">
        {{ t('auth.password.sendResetLink') }}
      </AppButton>
    </form>

    <div class="ks-divider my-6" />
    <Link
      class="block text-center text-sm font-semibold text-[var(--ks-gold-bright)] hover:text-[var(--ks-ivory)]"
      href="/login"
    >
      {{ t('authExperience.password.backToSignIn') }}
    </Link>
  </AuthLayout>
</template>
