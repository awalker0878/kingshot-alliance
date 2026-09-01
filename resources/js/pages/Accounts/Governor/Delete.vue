<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';
import { useConfirmAction } from '@/components/ui/useConfirmAction';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

const props = defineProps<{
  user: { name: string; email: string };
  request: null | {
    status: string;
    requestedAt: string;
    eligibleAt: string;
    processedAt: string | null;
    blockedReason: string | null;
  };
  status: string | null;
}>();

const { t, formatDate } = useLocale();
const { dialog, requestConfirmation, cancelConfirmation, confirmAction } = useConfirmAction();

const statusMessage = computed(() => {
  if (props.status === 'account-deletion-requested') return t('accountExperience.deletion.requested');
  if (props.status === 'account-deletion-cancelled') return t('accountExperience.deletion.cancelled');
  return props.status;
});

function requestDeletion(): void {
  requestConfirmation({
    id: 'account-deletion-confirmation',
    title: t('accountExperience.deletion.requestTitle'),
    description: t('accountExperience.deletion.confirm'),
    confirmLabel: t('accountExperience.deletion.requestButton'),
    cancelLabel: t('common.cancel'),
    perform: (finish) => router.post('/profile/delete-account', {}, { onFinish: finish }),
  });
}

function cancelDeletion(): void {
  requestConfirmation({
    id: 'account-deletion-cancel-confirmation',
    title: t('accountExperience.deletion.cancelTitle'),
    description: t('accountExperience.deletion.cancelConfirm'),
    confirmLabel: t('accountExperience.deletion.cancelButton'),
    cancelLabel: t('common.cancel'),
    perform: (finish) => router.delete('/profile/delete-account', { onFinish: finish }),
  });
}

function requestTone(status: string): 'success' | 'warning' | 'danger' | 'info' {
  if (status === 'processed' || status === 'completed') return 'success';
  if (status === 'blocked' || status === 'failed') return 'danger';
  if (status === 'pending' || status === 'requested') return 'warning';
  return 'info';
}
</script>

<template>
  <Head :title="t('accountExperience.deletion.title')" />

  <AppLayout :user="props.user">
    <RoomBanner
      :eyebrow="t('accountExperience.deletion.eyebrow')"
      :title="t('accountExperience.deletion.title')"
      :subtitle="t('accountExperience.deletion.intro')"
      image="/images/kingshot/v4/account-vault.svg"
      compact
    >
      <template #actions>
        <Link href="/profile" class="ks-command-link">{{ t('accountExperience.deletion.backToAccount') }}</Link>
      </template>
    </RoomBanner>

    <p v-if="statusMessage" role="status" class="mt-5 rounded-[var(--ks-radius-md)] border border-emerald-400/25 bg-emerald-500/[.07] px-4 py-3 text-sm text-emerald-100">
      {{ statusMessage }}
    </p>

    <template v-if="props.request">
      <section class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <StatSeal :label="t('accountExperience.deletion.status')" :value="props.request.status" icon="⛨" :tone="props.request.status === 'processed' ? 'teal' : 'stone'" />
        <StatSeal :label="t('accountExperience.deletion.requestedAt')" :value="formatDate(props.request.requestedAt)" icon="◷" />
        <StatSeal :label="t('accountExperience.deletion.eligibleAt')" :value="formatDate(props.request.eligibleAt)" icon="⌛" tone="stone" />
        <StatSeal :label="t('accountExperience.deletion.processedAt')" :value="props.request.processedAt ? formatDate(props.request.processedAt) : t('accountExperience.deletion.notYet')" icon="✓" tone="teal" />
      </section>

      <section class="ks-surface mt-5 p-5 sm:p-6" aria-labelledby="request-heading">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="max-w-3xl">
            <p class="ks-kicker">{{ t('accountExperience.deletion.currentRequest') }}</p>
            <h2 id="request-heading" class="ks-display mt-1 text-2xl font-semibold">{{ props.user.name }}</h2>
            <p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">{{ t('accountExperience.deletion.requestIntro') }}</p>
          </div>
          <span class="ks-status" :data-tone="requestTone(props.request.status)">{{ props.request.status }}</span>
        </div>

        <div v-if="props.request.blockedReason" class="mt-6 rounded-[var(--ks-radius-md)] border border-amber-400/25 bg-amber-500/[.07] p-4">
          <p class="ks-kicker text-amber-200">{{ t('accountExperience.deletion.status') }}</p>
          <p class="mt-2 text-sm leading-6 text-amber-100/90">{{ props.request.blockedReason }}</p>
        </div>

        <AppButton v-if="['pending', 'blocked'].includes(props.request.status)" class="mt-6" variant="ghost" type="button" @click="cancelDeletion">
          {{ t('accountExperience.deletion.cancelButton') }}
        </AppButton>
      </section>
    </template>

    <section v-else class="mt-5 overflow-hidden rounded-[var(--ks-radius-lg)] border border-red-400/25 bg-[linear-gradient(145deg,rgba(80,24,20,.13),rgba(8,13,13,.92))] shadow-[var(--ks-shadow-panel)]" aria-labelledby="deletion-request-heading">
      <div class="border-b border-red-400/15 p-5 sm:p-6">
        <p class="ks-kicker text-[var(--ks-red)]">{{ t('accountExperience.account.dangerTitle') }}</p>
        <h2 id="deletion-request-heading" class="ks-display mt-2 text-3xl font-semibold">{{ t('accountExperience.deletion.requestTitle') }}</h2>
        <p class="mt-3 max-w-3xl text-sm leading-7 text-[var(--ks-text-secondary)]">{{ t('accountExperience.deletion.requestIntro') }}</p>
      </div>
      <div class="grid gap-5 p-5 sm:p-6 lg:grid-cols-[1fr_auto] lg:items-center">
        <div class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-black/15 p-4">
          <p class="ks-kicker">{{ t('accountExperience.account.profileTitle') }}</p>
          <p class="ks-display mt-2 text-xl">{{ props.user.name }}</p>
          <p class="mt-1 text-sm text-[var(--ks-muted)]">{{ props.user.email }}</p>
        </div>
        <AppButton variant="danger" type="button" @click="requestDeletion">{{ t('accountExperience.deletion.requestButton') }}</AppButton>
      </div>
    </section>

    <ConfirmActionDialog v-bind="dialog" @confirm="confirmAction" @cancel="cancelConfirmation" />
  </AppLayout>
</template>
