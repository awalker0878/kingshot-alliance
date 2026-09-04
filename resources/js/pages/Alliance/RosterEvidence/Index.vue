<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import AppButton from '@/components/ui/AppButton.vue';
import FormError from '@/components/ui/FormError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type ReviewRow = {
  observed_name: string;
  game_player_id: string;
  observed_rank: string;
  power: number | null;
  roster_entry_id: string;
};

type EvidenceItem = {
  id: string;
  name: string;
  status: string;
  uploadedAt: string | null;
  visualDuplicateEvidenceId: string | null;
  review: {
    id: string;
    status: string;
    capturedAt: string;
    completeRoster: boolean;
    rows: Array<{
      observed_name?: string;
      game_player_id?: string | null;
      observed_rank?: string | null;
      power?: number | null;
      roster_entry_id?: string | null;
    }>;
  } | null;
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string };
  evidence: EvidenceItem[];
  rosterEntries: Array<{ id: string; name: string }>;
}>();

const { t, formatDate } = useLocale();
const uploadForm = useForm<{ evidence: File | null }>({ evidence: null });
const reviewDrafts = reactive(
  Object.fromEntries(
    props.evidence.map((item) => [
      item.id,
      {
        captured_at: item.review?.capturedAt ?? new Date().toISOString().slice(0, 16),
        complete_roster: item.review?.completeRoster ?? false,
        allow_semantic_duplicate: false,
        rows:
          item.review?.rows.map((row) => ({
            observed_name: row.observed_name ?? '',
            game_player_id: row.game_player_id ?? '',
            observed_rank: row.observed_rank ?? '',
            power: row.power ?? null,
            roster_entry_id: row.roster_entry_id ?? '',
          })) ?? [emptyRow()],
      },
    ]),
  ) as Record<
    string,
    {
      captured_at: string;
      complete_roster: boolean;
      allow_semantic_duplicate: boolean;
      rows: ReviewRow[];
    }
  >,
);

function emptyRow(): ReviewRow {
  return {
    observed_name: '',
    game_player_id: '',
    observed_rank: '',
    power: null,
    roster_entry_id: '',
  };
}

function chooseFile(event: Event): void {
  const input = event.target as HTMLInputElement;
  uploadForm.evidence = input.files?.[0] ?? null;
}

function upload(): void {
  uploadForm.post('/alliance/roster/evidence', {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => uploadForm.reset(),
  });
}

function addRow(evidenceId: string): void {
  reviewDrafts[evidenceId]?.rows.push(emptyRow());
}

function removeRow(evidenceId: string, index: number): void {
  const rows = reviewDrafts[evidenceId]?.rows;
  if (!rows || rows.length <= 1) return;
  rows.splice(index, 1);
}

function saveReview(item: EvidenceItem): void {
  const draft = reviewDrafts[item.id];
  if (!draft) return;
  router.post(`/alliance/roster/evidence/${item.id}/review`, draft, { preserveScroll: true });
}

function commitReview(item: EvidenceItem): void {
  if (!item.review || item.review.status !== 'approved') return;
  router.post(`/alliance/roster/evidence/reviews/${item.review.id}/commit`, {}, { preserveScroll: true });
}
</script>

<template>
  <Head :title="`${t('allianceExpansion.rosterEvidenceTitle')} · ${alliance.name}`" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <RoomBanner
      :eyebrow="t('allianceExpansion.rosterEvidenceEyebrow')"
      :title="t('allianceExpansion.rosterEvidenceTitle')"
      :subtitle="t('allianceExpansion.rosterEvidenceSubtitle')"
      image="/images/kingshot/v4/roster-hall.svg"
      compact
    >
      <template #actions>
        <Link href="/alliance/roster/reconciliation" class="ks-command-link" data-variant="secondary">
          {{ t('allianceExpansion.navReconciliation') }}
        </Link>
        <Link href="/alliance/history" class="ks-command-link" data-variant="secondary">
          {{ t('allianceExpansion.navHistory') }}
        </Link>
      </template>
    </RoomBanner>

    <form class="ks-surface mt-6 p-5" @submit.prevent="upload">
      <label class="text-sm font-semibold" for="alliance-roster-evidence-file">
        {{ t('allianceExpansion.screenshotFile') }}
      </label>
      <div class="mt-2 flex flex-wrap items-center gap-3">
        <input
          id="alliance-roster-evidence-file"
          type="file"
          accept="image/jpeg,image/png,image/webp"
          @change="chooseFile"
        />
        <AppButton type="submit" :disabled="!uploadForm.evidence || uploadForm.processing">
          {{ t('allianceExpansion.uploadScreenshot') }}
        </AppButton>
      </div>
      <FormError :message="uploadForm.errors.evidence" />
    </form>

    <section class="mt-6" aria-labelledby="recent-roster-evidence">
      <h2 id="recent-roster-evidence" class="ks-display text-2xl font-semibold">
        {{ t('allianceExpansion.recentEvidence') }}
      </h2>

      <div v-if="evidence.length" class="mt-4 space-y-6">
        <article v-for="item in evidence" :key="item.id" class="ks-surface p-5">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h3 class="font-semibold">{{ item.name }}</h3>
              <p class="mt-1 text-sm text-[var(--ks-muted)]">
                {{ t('allianceExpansion.evidenceStatus') }}: {{ item.status }}
              </p>
            </div>
            <p class="text-sm text-[var(--ks-muted)]">
              {{
                item.uploadedAt
                  ? `${t('allianceExpansion.uploadedAt')}: ${formatDate(item.uploadedAt)}`
                  : t('common.none')
              }}
            </p>
          </div>

          <p v-if="item.visualDuplicateEvidenceId" class="mt-3 text-sm text-amber-300">
            {{ t('allianceExpansion.visualDuplicate') }}
          </p>

          <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <div>
              <label class="text-sm font-semibold" :for="`captured-${item.id}`">
                {{ t('allianceExpansion.capturedAt') }}
              </label>
              <input
                :id="`captured-${item.id}`"
                v-model="reviewDrafts[item.id]!.captured_at"
                type="datetime-local"
                class="ks-input mt-2"
              />
            </div>
            <div class="space-y-3 pt-7">
              <label class="flex items-start gap-2 text-sm">
                <input v-model="reviewDrafts[item.id]!.complete_roster" type="checkbox" />
                <span>
                  <strong class="block">{{ t('allianceExpansion.completeRoster') }}</strong>
                  <span class="mt-1 block text-[var(--ks-muted)]">
                    {{ t('allianceExpansion.completeRosterHelp') }}
                  </span>
                </span>
              </label>
              <label class="flex items-start gap-2 text-sm">
                <input
                  v-model="reviewDrafts[item.id]!.allow_semantic_duplicate"
                  type="checkbox"
                />
                <span>{{ t('allianceExpansion.allowSemanticDuplicate') }}</span>
              </label>
            </div>
          </div>

          <div class="mt-5 overflow-x-auto">
            <table class="w-full min-w-[58rem] text-sm">
              <thead>
                <tr class="border-b border-[var(--ks-border)] text-left text-[var(--ks-muted)]">
                  <th class="p-2">{{ t('allianceExpansion.observedName') }}</th>
                  <th class="p-2">{{ t('allianceExpansion.gamePlayerId') }}</th>
                  <th class="p-2">{{ t('allianceExpansion.observedRank') }}</th>
                  <th class="p-2">{{ t('allianceExpansion.power') }}</th>
                  <th class="p-2">{{ t('allianceExpansion.rosterEntry') }}</th>
                  <th class="p-2"><span class="sr-only">{{ t('common.close') }}</span></th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(row, index) in reviewDrafts[item.id]!.rows"
                  :key="index"
                  class="border-b border-[var(--ks-border)] last:border-0"
                >
                  <td class="p-2">
                    <input v-model="row.observed_name" class="ks-input" maxlength="160" />
                  </td>
                  <td class="p-2">
                    <input v-model="row.game_player_id" class="ks-input" maxlength="100" />
                  </td>
                  <td class="p-2">
                    <select v-model="row.observed_rank" class="ks-input">
                      <option value="">{{ t('common.none') }}</option>
                      <option v-for="rank in ['r1', 'r2', 'r3', 'r4', 'r5']" :key="rank" :value="rank">
                        {{ rank }}
                      </option>
                    </select>
                  </td>
                  <td class="p-2">
                    <input v-model="row.power" type="number" min="0" class="ks-input" />
                  </td>
                  <td class="p-2">
                    <select v-model="row.roster_entry_id" class="ks-input">
                      <option value="">{{ t('allianceExpansion.noLinkedRosterEntry') }}</option>
                      <option v-for="entry in rosterEntries" :key="entry.id" :value="entry.id">
                        {{ entry.name }}
                      </option>
                    </select>
                  </td>
                  <td class="p-2 text-right">
                    <button
                      type="button"
                      class="text-sm text-red-300"
                      :disabled="reviewDrafts[item.id]!.rows.length <= 1"
                      @click="removeRow(item.id, index)"
                    >
                      {{ t('allianceExpansion.removeRow') }}
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="mt-4 flex flex-wrap gap-2">
            <AppButton variant="secondary" @click="addRow(item.id)">
              {{ t('allianceExpansion.addRow') }}
            </AppButton>
            <AppButton variant="secondary" @click="saveReview(item)">
              {{ t('allianceExpansion.saveReview') }}
            </AppButton>
            <AppButton
              v-if="item.review?.status === 'approved'"
              @click="commitReview(item)"
            >
              {{ t('allianceExpansion.commitReview') }}
            </AppButton>
          </div>
        </article>
      </div>
      <div v-else class="ks-fantasy-empty mt-4">{{ t('allianceExpansion.noEvidence') }}</div>
    </section>
  </AppLayout>
</template>
