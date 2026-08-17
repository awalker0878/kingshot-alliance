<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

import AuthLayout from '@/layouts/AuthLayout.vue';
import { useLocale } from '@/localization';

const props = defineProps<{
  invitation: {
    token: string;
    email: string;
    expiresAt: string | null;
    alliance: {
      id: string;
      name: string;
    };
  };
  authenticated: boolean;
  authenticatedEmail: string | null;
}>();

const { t, formatDate } = useLocale();

function accept(): void {
  router.post(`/invitations/${props.invitation.token}/accept`);
}

function invitationExpiry(): string {
  return props.invitation.expiresAt
    ? formatDate(props.invitation.expiresAt, { dateStyle: 'medium', timeStyle: 'short' })
    : '';
}
</script>

<template>
  <Head :title="t('auth.invitation.title')" />

  <AuthLayout>
    <template #headline>
      {{ t('authExperience.invitation.join', { alliance: invitation.alliance.name }) }}
    </template>
    <template #intro>{{ t('auth.invitation.title') }}</template>

    <div>
      <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
        {{ t('auth.invitation.title') }}
      </p>
      <h2 class="ks-display mt-2 text-2xl font-semibold sm:text-3xl">
        {{ t('authExperience.invitation.join', { alliance: invitation.alliance.name }) }}
      </h2>

      <div
        class="mt-6 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[rgba(8,17,31,0.58)] p-4"
      >
        <p class="text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('authExperience.invitation.forEmail', { email: invitation.email }) }}
        </p>
        <p v-if="invitation.expiresAt" class="mt-2 text-xs text-[var(--ks-text-muted)]">
          {{ t('authExperience.invitation.expires', { date: invitationExpiry() }) }}
        </p>
      </div>

      <div v-if="authenticated" class="mt-7">
        <div
          v-if="authenticatedEmail?.toLowerCase() !== invitation.email.toLowerCase()"
          class="rounded-[var(--ks-radius-sm)] border border-rose-800 bg-rose-950/25 p-4 text-sm leading-6 text-rose-100"
          role="alert"
        >
          {{ t('authExperience.invitation.wrongAccount', { email: authenticatedEmail ?? '' }) }}
        </div>
        <button
          v-else
          class="w-full rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-5 py-3 font-bold text-[var(--ks-ink)] transition hover:bg-[var(--ks-gold-strong)]"
          type="button"
          @click="accept"
        >
          {{ t('auth.invitation.accept') }}
        </button>
      </div>

      <div v-else class="mt-7 grid gap-3">
        <Link
          class="rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-5 py-3 text-center font-bold text-[var(--ks-ink)] transition hover:bg-[var(--ks-gold-strong)]"
          :href="`/register?invitation=${encodeURIComponent(invitation.token)}`"
        >
          {{ t('authExperience.invitation.createAndJoin') }}
        </Link>
        <Link
          class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[rgba(8,17,31,0.48)] px-5 py-3 text-center font-semibold transition hover:border-[var(--ks-border-strong)] hover:bg-[var(--ks-surface-1)]"
          :href="`/login?invitation=${encodeURIComponent(invitation.token)}`"
        >
          {{ t('authExperience.invitation.signInAccept') }}
        </Link>
      </div>
    </div>
  </AuthLayout>
</template>
