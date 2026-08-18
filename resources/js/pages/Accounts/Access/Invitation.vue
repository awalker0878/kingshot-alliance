<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

import AppButton from '@/components/ui/AppButton.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useLocale } from '@/localization';

const props = defineProps<{
  invitation: {
    token: string;
    email: string;
    expiresAt: string | null;
    alliance: { id: string; name: string };
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
    <div
      class="mx-auto grid h-16 w-14 place-items-center border border-[var(--ks-gold-dark)] bg-[linear-gradient(160deg,#126b64,#092d2a)] font-[var(--ks-font-display)] text-2xl text-[var(--ks-gold-bright)] [clip-path:polygon(50%_0,95%_16%,86%_77%,50%_100%,14%_77%,5%_16%)]"
      aria-hidden="true"
    >
      ✉
    </div>
    <p class="ks-kicker mt-5 text-center">{{ t('auth.invitation.title') }}</p>
    <h2 class="ks-display mt-2 text-center text-3xl font-semibold sm:text-4xl">
      {{ t('authExperience.invitation.join', { alliance: invitation.alliance.name }) }}
    </h2>

    <div class="mt-6 rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4 text-center">
      <p class="text-sm leading-6 text-[var(--ks-text-secondary)]">
        {{ t('authExperience.invitation.forEmail', { email: invitation.email }) }}
      </p>
      <p v-if="invitation.expiresAt" class="mt-2 text-xs text-[var(--ks-muted)]">
        {{ t('authExperience.invitation.expires', { date: invitationExpiry() }) }}
      </p>
    </div>

    <div v-if="authenticated" class="mt-7">
      <div
        v-if="authenticatedEmail?.toLowerCase() !== invitation.email.toLowerCase()"
        class="rounded-[var(--ks-radius-md)] border border-red-400/30 bg-red-500/[.07] p-4 text-sm leading-6 text-red-100"
        role="alert"
      >
        {{ t('authExperience.invitation.wrongAccount', { email: authenticatedEmail ?? '' }) }}
      </div>
      <AppButton v-else class="w-full" type="button" @click="accept">
        {{ t('auth.invitation.accept') }}
      </AppButton>
    </div>

    <div v-else class="mt-7 grid gap-3">
      <Link class="ks-command-link w-full" :href="`/register?invitation=${encodeURIComponent(invitation.token)}`">
        {{ t('authExperience.invitation.createAndJoin') }}
      </Link>
      <Link class="ks-command-link w-full" data-variant="secondary" :href="`/login?invitation=${encodeURIComponent(invitation.token)}`">
        {{ t('authExperience.invitation.signInAccept') }}
      </Link>
    </div>
  </AuthLayout>
</template>
