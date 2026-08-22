<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import AppButton from '@/components/ui/AppButton.vue';
import FormError from '@/components/ui/FormError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type Plan = {
  id: string;
  name: string;
  revision: number;
  can_manage: boolean;
};
type AllianceLayer = {
  key: string;
  alliance_id: string | null;
  external_name: string | null;
  external_tag: string | null;
  display_name: string;
  presentation_color: string;
  sort_order: number;
  visible: boolean;
  locked: boolean;
};
type AllianceOption = { id: string; name: string };

const props = defineProps<{
  user: { name: string; email: string };
  activePlayer: { id: string; name: string; kingdomNumber: number | null };
  plan: Plan;
  alliances: AllianceLayer[];
  allianceOptions: AllianceOption[];
  objectCounts: Record<string, number>;
}>();

const { t, formatNumber } = useLocale();
const selectedLinkedAlliance = ref('');
const form = useForm({
  expected_revision: props.plan.revision,
  alliances: JSON.parse(JSON.stringify(props.alliances)) as AllianceLayer[],
});

const availableLinkedAlliances = computed(() => {
  const used = new Set(
    form.alliances.flatMap((layer) => (layer.alliance_id ? [layer.alliance_id] : [])),
  );
  return props.allianceOptions.filter((alliance) => !used.has(alliance.id));
});

function layerKey(prefix: string): string {
  return `${prefix}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;
}

function addLinkedAlliance(): void {
  if (!props.plan.can_manage || !selectedLinkedAlliance.value) return;
  const option = props.allianceOptions.find((alliance) => alliance.id === selectedLinkedAlliance.value);
  if (!option || form.alliances.some((layer) => layer.alliance_id === option.id)) return;
  form.alliances.push({
    key: layerKey('alliance'),
    alliance_id: option.id,
    external_name: null,
    external_tag: null,
    display_name: option.name,
    presentation_color: '#4da3ff',
    sort_order: form.alliances.length,
    visible: true,
    locked: false,
  });
  selectedLinkedAlliance.value = '';
}

function addExternalAlliance(): void {
  if (!props.plan.can_manage) return;
  const name = t('territory.externalAlliance');
  form.alliances.push({
    key: layerKey('external'),
    alliance_id: null,
    external_name: name,
    external_tag: null,
    display_name: name,
    presentation_color: '#c4874e',
    sort_order: form.alliances.length,
    visible: true,
    locked: false,
  });
}

function removeLayer(index: number): void {
  const layer = form.alliances[index];
  if (!layer || form.alliances.length <= 1 || (props.objectCounts[layer.key] ?? 0) > 0) return;
  form.alliances.splice(index, 1);
  form.alliances.forEach((item, sortOrder) => {
    item.sort_order = sortOrder;
  });
}

function submit(): void {
  form.expected_revision = props.plan.revision;
  form.alliances.forEach((layer, index) => {
    layer.sort_order = index;
    if (layer.external_name !== null) {
      layer.external_name = layer.external_name.trim();
      if (!layer.display_name.trim()) layer.display_name = layer.external_name;
    }
  });
  form.put(`/territory/${props.plan.id}/alliances`, { preserveScroll: true });
}
</script>

<template>
  <Head :title="`${plan.name} · ${t('territory.layers')}`" />
  <AppLayout :user="user">
    <RoomBanner
      :eyebrow="t('territory.eyebrow')"
      :title="t('territory.layers')"
      :subtitle="plan.name"
      image="/images/kingshot/v4/kingdom-transfer.svg"
    >
      <template #actions>
        <Link :href="`/territory/${plan.id}`" class="ks-command-link" data-variant="secondary">
          {{ t('territory.editorTitle') }}
        </Link>
        <Link href="/territory" class="ks-command-link" data-variant="secondary">
          {{ t('territory.backToPlans') }}
        </Link>
      </template>
    </RoomBanner>

    <form class="mt-5 space-y-5" @submit.prevent="submit">
      <section class="ks-surface p-5" :aria-label="t('territory.layers')">
        <div class="flex flex-wrap items-end gap-3">
          <label class="min-w-64 flex-1 text-sm font-semibold">
            {{ t('territory.alliance') }}
            <select
              v-model="selectedLinkedAlliance"
              class="ks-input mt-2 w-full"
              :disabled="!plan.can_manage || !availableLinkedAlliances.length"
            >
              <option value="">{{ t('territory.alliance') }}</option>
              <option
                v-for="alliance in availableLinkedAlliances"
                :key="alliance.id"
                :value="alliance.id"
              >
                {{ alliance.name }}
              </option>
            </select>
          </label>
          <AppButton
            type="button"
            :disabled="!plan.can_manage || !selectedLinkedAlliance"
            @click="addLinkedAlliance"
          >
            + {{ t('territory.alliance') }}
          </AppButton>
          <AppButton type="button" :disabled="!plan.can_manage" @click="addExternalAlliance">
            {{ t('territory.addAlliance') }}
          </AppButton>
        </div>
        <FormError class="mt-3" :message="form.errors.alliances" />
      </section>

      <section class="grid gap-4 lg:grid-cols-2" :aria-label="t('territory.layers')">
        <article
          v-for="(layer, index) in form.alliances"
          :key="layer.key"
          class="ks-surface p-5"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="ks-kicker">{{ layer.alliance_id ? t('territory.alliance') : t('territory.externalAlliance') }}</p>
              <h2 class="ks-display mt-1 text-xl font-semibold">{{ layer.display_name }}</h2>
              <p class="mt-1 text-xs text-[var(--ks-muted)]">
                {{ t('territory.placedObjects', { count: formatNumber(objectCounts[layer.key] ?? 0) }) }}
              </p>
            </div>
            <button
              v-if="plan.can_manage"
              type="button"
              class="ks-command-link"
              data-variant="danger"
              :disabled="form.alliances.length <= 1 || (objectCounts[layer.key] ?? 0) > 0"
              :aria-label="`${t('territory.deleteSelected')}: ${layer.display_name}`"
              @click="removeLayer(index)"
            >
              {{ t('territory.deleteSelected') }}
            </button>
          </div>

          <label v-if="layer.external_name !== null" class="mt-4 block text-sm font-semibold">
            {{ t('territory.externalAlliance') }}
            <input
              v-model="layer.external_name"
              class="ks-input mt-2 w-full"
              maxlength="160"
              :disabled="!plan.can_manage"
              required
            />
          </label>
          <label class="mt-4 block text-sm font-semibold">
            {{ t('territory.alliance') }}
            <input
              v-model="layer.display_name"
              class="ks-input mt-2 w-full"
              maxlength="160"
              :disabled="!plan.can_manage"
              required
            />
          </label>
          <label class="mt-4 block text-sm font-semibold">
            {{ t('territory.layerColor', { alliance: layer.display_name }) }}
            <input
              v-model="layer.presentation_color"
              type="color"
              class="mt-2 block h-10 w-20"
              :disabled="!plan.can_manage"
            />
          </label>
          <div class="mt-4 flex flex-wrap gap-4 text-sm">
            <label class="flex items-center gap-2">
              <input v-model="layer.visible" type="checkbox" :disabled="!plan.can_manage" />
              {{ layer.display_name }}
            </label>
            <label class="flex items-center gap-2">
              <input v-model="layer.locked" type="checkbox" :disabled="!plan.can_manage" />
              {{ t('territory.lockLayer') }}
            </label>
          </div>
        </article>
      </section>

      <div class="flex flex-wrap gap-3">
        <AppButton
          type="submit"
          :busy="form.processing"
          :disabled="!plan.can_manage || !form.alliances.length"
        >
          {{ t('territory.save') }}
        </AppButton>
        <Link :href="`/territory/${plan.id}`" class="ks-command-link" data-variant="secondary">
          {{ t('territory.editorTitle') }}
        </Link>
      </div>
    </form>
  </AppLayout>
</template>
