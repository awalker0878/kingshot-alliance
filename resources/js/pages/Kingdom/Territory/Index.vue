<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import AppButton from '@/components/ui/AppButton.vue';
import FormError from '@/components/ui/FormError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type Plan = {
  id: string;
  scope: 'alliance' | 'kingdom';
  name: string;
  status: string;
  revision: number;
  updated_at: string | null;
  can_manage: boolean;
  map_dataset_id: string;
};
type Dataset = {
  id: string;
  observedAt: string;
  sourceLabel: string;
  sourceUri: string | null;
  confidence: string;
  checksum: string;
};
type AllianceOption = { id: string; name: string; canManage: boolean };

const props = defineProps<{
  user: { name: string; email: string };
  activePlayer: { id: string; name: string; kingdomId: string; kingdomNumber: number | null };
  plans: Plan[];
  mapDatasets: Dataset[];
  allianceOptions: AllianceOption[];
  canManageKingdomPlans: boolean;
}>();

const { t, formatDate } = useLocale();
const defaultAlliance = computed(
  () => props.allianceOptions.find((alliance) => alliance.canManage)?.id ?? null,
);
const form = useForm({
  scope: defaultAlliance.value ? 'alliance' : 'kingdom',
  kingdom_id: props.activePlayer.kingdomId,
  owner_alliance_id: defaultAlliance.value,
  name: '',
  map_dataset_id: props.mapDatasets[0]?.id ?? '',
});

function submit(): void {
  if (form.scope === 'kingdom') form.owner_alliance_id = null;
  form.post('/territory', { preserveScroll: true });
}
</script>

<template>
  <Head :title="t('territory.indexTitle')" />
  <AppLayout :user="user">
    <RoomBanner
      :eyebrow="t('territory.eyebrow')"
      :title="t('territory.indexTitle')"
      :subtitle="t('territory.indexSubtitle', { governor: activePlayer.name })"
      image="/images/kingshot/v4/kingdom-transfer.svg"
    />

    <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.25fr)_minmax(20rem,.75fr)]">
      <section class="ks-surface overflow-hidden" aria-labelledby="plans-heading">
        <div class="border-b border-[var(--ks-border)] p-5">
          <p class="ks-kicker">{{ t('territory.savedPlans') }}</p>
          <h2 id="plans-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('territory.plansHeading') }}
          </h2>
        </div>
        <div v-if="plans.length" class="divide-y divide-[var(--ks-border)]">
          <article v-for="plan in plans" :key="plan.id" class="p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h3 class="ks-display text-xl font-semibold">{{ plan.name }}</h3>
                <p class="mt-1 text-sm text-[var(--ks-muted)]">
                  {{
                    plan.scope === 'alliance'
                      ? t('territory.alliancePlan')
                      : t('territory.kingdomPlan')
                  }}
                  · {{ t('territory.revision', { revision: plan.revision }) }}
                </p>
              </div>
              <span
                class="ks-status"
                :data-tone="plan.status === 'published' ? 'success' : 'info'"
                >{{ t(`territory.status.${plan.status}`) }}</span
              >
            </div>
            <p v-if="plan.updated_at" class="mt-3 text-xs text-[var(--ks-muted)]">
              {{ t('territory.updated', { date: formatDate(plan.updated_at) }) }}
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
              <Link :href="`/territory/${plan.id}`" class="ks-command-link">
                {{ t('territory.editorTitle') }}
              </Link>
              <Link
                :href="`/territory/${plan.id}/reconciliation`"
                class="ks-command-link"
                data-variant="secondary"
              >
                {{ t('territory.reconciliationTitle') }}
              </Link>
              <Link
                v-if="plan.scope === 'kingdom'"
                :href="`/territory/${plan.id}/alliances`"
                class="ks-command-link"
                data-variant="secondary"
              >
                {{ t('territory.layers') }}
              </Link>
            </div>
          </article>
        </div>
        <p v-else class="p-6 text-sm text-[var(--ks-muted)]">{{ t('territory.noPlans') }}</p>
      </section>

      <section class="ks-surface-gold p-5" aria-labelledby="create-plan-heading">
        <p class="ks-kicker">{{ t('territory.newPlan') }}</p>
        <h2 id="create-plan-heading" class="ks-display mt-1 text-2xl font-semibold">
          {{ t('territory.createPlan') }}
        </h2>
        <form class="mt-5 space-y-4" @submit.prevent="submit">
          <label class="block text-sm font-semibold">
            {{ t('territory.scope') }}
            <select v-model="form.scope" class="ks-input mt-2 w-full">
              <option v-if="defaultAlliance" value="alliance">
                {{ t('territory.alliancePlan') }}
              </option>
              <option v-if="canManageKingdomPlans" value="kingdom">
                {{ t('territory.kingdomPlan') }}
              </option>
            </select>
          </label>
          <label v-if="form.scope === 'alliance'" class="block text-sm font-semibold">
            {{ t('territory.alliance') }}
            <select v-model="form.owner_alliance_id" class="ks-input mt-2 w-full">
              <option
                v-for="alliance in allianceOptions.filter((item) => item.canManage)"
                :key="alliance.id"
                :value="alliance.id"
              >
                {{ alliance.name }}
              </option>
            </select>
          </label>
          <label class="block text-sm font-semibold">
            {{ t('territory.planName') }}
            <input v-model="form.name" required maxlength="160" class="ks-input mt-2 w-full" />
            <FormError :message="form.errors.name" />
          </label>
          <label class="block text-sm font-semibold">
            {{ t('territory.mapProfile') }}
            <select v-model="form.map_dataset_id" class="ks-input mt-2 w-full" required>
              <option v-for="dataset in mapDatasets" :key="dataset.id" :value="dataset.id">
                {{ dataset.sourceLabel }} · {{ dataset.observedAt }}
              </option>
            </select>
          </label>
          <p class="text-xs leading-5 text-[var(--ks-muted)]">
            {{ t('territory.mapEvidenceHelp') }}
          </p>
          <AppButton
            type="submit"
            :busy="form.processing"
            :disabled="!form.name || !form.map_dataset_id"
            >{{ t('territory.createPlan') }}</AppButton
          >
        </form>
      </section>
    </div>
  </AppLayout>
</template>
