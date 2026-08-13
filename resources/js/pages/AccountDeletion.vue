<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

import AppLayout from '../layouts/AppLayout.vue';
import { useLocale } from '../localization';

const props = defineProps<{
  user: {
    name: string;
    email: string;
  };
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

const statusMessage = computed(() =>
  props.status === 'account-deletion-requested'
    ? t('accountExperience.deletion.requested')
    : props.status,
);

function requestDeletion(): void {
  if (!window.confirm(t('accountExperience.deletion.confirm'))) {
    return;
  }

  router.post('/profile/delete-account');
}
</script>

<template>
  <Head :title="t('accountExperience.deletion.title')" />

  <AppLayout :user="props.user">
    <header class="mb-8 max-w-3xl">
      <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-red)] uppercase">
        {{ t('accountExperience.deletion.eyebrow') }}
      </p>
      <h1 class="ks-display mt-2 text-3xl font-semibold sm:text-4xl">
        {{ t('accountExperience.deletion.title') }}
      </h1>
      <p class="mt-3 text-sm leading-6 text-[var(--ks-text-muted)] sm:text-base">
        {{ t('accountExperience.deletion.intro') }}
      </p>
      <Link
        href="/profile"
        class="mt-4 inline-flex text-sm font-semibold text-[var(--ks-blue-strong)] transition hover:text-white"
      >
        {{ t('accountExperience.deletion.backToAccount') }}
      </Link>
    </header>

    <p
      v-if="statusMessage"
      role="status"
      class="mb-6 rounded-[var(--ks-radius-md)] border border-emerald-500/25 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200"
    >
      {{ statusMessage }}
    </p>

    <section
      v-if="props.request"
      aria-labelledby="request-heading"
      class="ks-surface max-w-4xl p-5 sm:p-6"
    >
      <div
        class="flex flex-col gap-2 border-b border-[var(--ks-border)] pb-5 sm:flex-row sm:items-center sm:justify-between"
      >
        <h2 id="request-heading" class="ks-display text-2xl font-semibold">
          {{ t('accountExperience.deletion.currentRequest') }}
        </h2>
        <span
          class="w-fit rounded-full border border-[var(--ks-border-strong)] bg-[var(--ks-gold-soft)] px-3 py-1 text-xs font-bold text-[var(--ks-gold-strong)]"
        >
          {{ props.request.status }}
        </span>
      </div>

      <dl class="mt-6 grid gap-4 sm:grid-cols-2">
        <div
          class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-bg)]/65 p-4"
        >
          <dt class="text-xs font-bold tracking-[0.14em] text-[var(--ks-text-muted)] uppercase">
            {{ t('accountExperience.deletion.status') }}
          </dt>
          <dd class="mt-2 font-semibold">{{ props.request.status }}</dd>
        </div>
        <div
          class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-bg)]/65 p-4"
        >
          <dt class="text-xs font-bold tracking-[0.14em] text-[var(--ks-text-muted)] uppercase">
            {{ t('accountExperience.deletion.eligibleAt') }}
          </dt>
          <dd class="mt-2">{{ formatDate(props.request.eligibleAt) }}</dd>
        </div>
        <div
          class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-bg)]/65 p-4"
        >
          <dt class="text-xs font-bold tracking-[0.14em] text-[var(--ks-text-muted)] uppercase">
            {{ t('accountExperience.deletion.requestedAt') }}
          </dt>
          <dd class="mt-2">{{ formatDate(props.request.requestedAt) }}</dd>
        </div>
        <div
          class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-bg)]/65 p-4"
        >
          <dt class="text-xs font-bold tracking-[0.14em] text-[var(--ks-text-muted)] uppercase">
            {{ t('accountExperience.deletion.processedAt') }}
          </dt>
          <dd class="mt-2">
            {{
              props.request.processedAt
                ? formatDate(props.request.processedAt)
                : t('accountExperience.deletion.notYet')
            }}
          </dd>
        </div>
      </dl>

      <p
        v-if="props.request.blockedReason"
        class="mt-5 rounded-[var(--ks-radius-md)] border border-amber-500/25 bg-amber-500/10 px-4 py-3 text-sm leading-6 text-amber-100"
      >
        {{ props.request.blockedReason }}
      </p>
    </section>

    <section
      v-else
      class="max-w-4xl rounded-[var(--ks-radius-lg)] border border-red-500/30 bg-red-500/5 p-5 shadow-[var(--ks-shadow-panel)] sm:p-6"
    >
      <h2 class="ks-display text-2xl font-semibold">
        {{ t('accountExperience.deletion.requestTitle') }}
      </h2>
      <p class="mt-3 max-w-2xl text-sm leading-6 text-[var(--ks-text-muted)]">
        {{ t('accountExperience.deletion.requestIntro') }}
      </p>
      <button
        type="button"
        class="mt-6 rounded-[var(--ks-radius-sm)] border border-red-500/40 bg-red-500/10 px-4 py-2.5 font-semibold text-[var(--ks-red)] transition hover:bg-red-500/15"
        @click="requestDeletion"
      >
        {{ t('accountExperience.deletion.requestButton') }}
      </button>
    </section>
  </AppLayout>
</template>
