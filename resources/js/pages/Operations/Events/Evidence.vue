<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import AppButton from '@/components/ui/AppButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type EvidenceItem = {
  id: string;
  originalName: string;
  mimeType: string;
  sizeBytes: number;
  width: number;
  height: number;
  sha256Prefix: string;
  status: string;
  kind: string;
  receivedAt: string | null;
};

const props = defineProps<{
  user: { name: string; email: string };
  userTimezone: string;
  workspace: { occurrenceId: string; allianceId: string; evidence: EvidenceItem[] };
}>();

const { t, formatDate, formatNumber } = useLocale();
const form = useForm<{ evidence: File | null }>({ evidence: null });

function selectFile(event: Event): void {
  const target = event.target as HTMLInputElement;
  form.evidence = target.files?.[0] ?? null;
}

function upload(): void {
  if (!form.evidence) return;
  form.post(`/events/${props.workspace.occurrenceId}/screenshot-intake`, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => form.reset('evidence'),
  });
}

function bytes(value: number): string {
  if (value < 1024) return `${formatNumber(value)} B`;
  return `${formatNumber(value / 1024, { maximumFractionDigits: 1 })} KB`;
}
</script>

<template>
  <Head :title="t('evidence.title')" />
  <AppLayout :user="user">
    <div class="mx-auto max-w-6xl space-y-5">
      <header class="ks-surface-gold p-5 sm:p-6">
        <p class="ks-kicker">{{ t('evidence.eyebrow') }}</p>
        <div class="mt-1 flex flex-wrap items-start justify-between gap-4">
          <div>
            <h1 class="ks-display text-3xl font-semibold">{{ t('evidence.title') }}</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--ks-muted)]">
              {{ t('evidence.subtitle') }}
            </p>
          </div>
          <Link :href="`/events/${workspace.occurrenceId}`" class="ks-command-link">← {{ t('evidence.back') }}</Link>
        </div>
      </header>

      <section class="ks-surface p-5 sm:p-6" aria-labelledby="evidence-upload-heading">
        <h2 id="evidence-upload-heading" class="ks-display text-2xl font-semibold">{{ t('evidence.uploadTitle') }}</h2>
        <p class="mt-2 text-sm text-[var(--ks-muted)]">{{ t('evidence.uploadHelp') }}</p>
        <form class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end" @submit.prevent="upload">
          <label class="block text-sm">
            <span>{{ t('evidence.chooseFile') }}</span>
            <input class="ks-input mt-1.5" type="file" accept="image/jpeg,image/png,image/webp" required @change="selectFile" />
          </label>
          <AppButton type="submit" :disabled="!form.evidence || form.processing">
            {{ form.processing ? t('evidence.uploading') : t('evidence.upload') }}
          </AppButton>
          <p v-if="form.errors.evidence" class="text-sm text-red-300 sm:col-span-2" role="alert">{{ form.errors.evidence }}</p>
        </form>
      </section>

      <section class="ks-surface p-5 sm:p-6" aria-labelledby="evidence-existing-heading">
        <h2 id="evidence-existing-heading" class="ks-display text-2xl font-semibold">{{ t('evidence.existingTitle') }}</h2>
        <p v-if="!workspace.evidence.length" class="mt-3 text-sm text-[var(--ks-muted)]">{{ t('evidence.empty') }}</p>
        <div v-else class="mt-4 overflow-x-auto">
          <table class="w-full min-w-[44rem] text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-[var(--ks-muted)]">
              <tr>
                <th class="pb-2 pe-4">{{ t('evidence.originalName') }}</th>
                <th class="pb-2 pe-4">{{ t('evidence.status') }}</th>
                <th class="pb-2 pe-4">{{ t('evidence.received') }}</th>
                <th class="pb-2">{{ t('evidence.security') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in workspace.evidence" :key="item.id" class="border-t border-[var(--ks-border)] align-top">
                <td class="py-3 pe-4">
                  <strong>{{ item.originalName }}</strong>
                  <div class="mt-1 text-xs text-[var(--ks-muted)]">{{ item.width }}×{{ item.height }} · {{ bytes(item.sizeBytes) }}</div>
                </td>
                <td class="py-3 pe-4"><span class="ks-status" data-tone="info">{{ item.status.replaceAll('_', ' ') }}</span></td>
                <td class="py-3 pe-4">{{ item.receivedAt ? formatDate(item.receivedAt) : '—' }}</td>
                <td class="py-3 font-mono text-xs">SHA-256 {{ item.sha256Prefix }}…</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </AppLayout>
</template>
