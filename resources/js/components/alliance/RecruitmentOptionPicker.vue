<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import AppButton from '@/components/ui/AppButton.vue';
import FormError from '@/components/ui/FormError.vue';
import { useLocale } from '@/localization';

type Choice = { id: string; name: string; rank: string | null; claimed: boolean | null };
type ChoiceResult = {
  page: { items: Choice[]; nextCursor: string | null; pageSize: number };
  selected: Choice | null;
};

const props = defineProps<{ id: string; endpoint: string; modelValue: string; label: string }>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
const { t, formatNumber } = useLocale();
const search = ref('');
const appliedSearch = ref('');
const items = ref<Choice[]>([]);
const selected = ref<Choice | null>(null);
const nextCursor = ref<string | null>(null);
const currentCursor = ref<string | null>(null);
const busy = ref(false);
const loaded = ref(false);
const pageSize = ref(25);
const error = ref<string | null>(null);
let activeRequest: AbortController | null = null;

const selectedOffPage = computed(
  () =>
    selected.value?.id === props.modelValue &&
    !items.value.some((item) => item.id === props.modelValue),
);
onBeforeUnmount(() => activeRequest?.abort());
onMounted(() => {
  if (props.modelValue) void load();
});

async function load(cursor: string | null = null): Promise<void> {
  activeRequest?.abort();
  const controller = new AbortController();
  activeRequest = controller;
  const query = cursor === null ? search.value.trim() : appliedSearch.value;
  const params = new URLSearchParams({ q: query });
  if (cursor) params.set('cursor', cursor);
  const selectedId = props.modelValue;
  if (selectedId) params.set('selected', selectedId);
  busy.value = true;
  error.value = null;
  try {
    const response = await fetch(`${props.endpoint}?${params}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
      signal: controller.signal,
    });
    if (!response.ok) throw new Error('Recruitment choice lookup failed.');
    const result = (await response.json()) as ChoiceResult;
    if (controller !== activeRequest) return;
    items.value = result.page.items;
    if (props.modelValue === selectedId) {
      selected.value = result.selected;
      if (selectedId && !result.selected) emit('update:modelValue', '');
    }
    pageSize.value = result.page.pageSize;
    nextCursor.value = result.page.nextCursor;
    currentCursor.value = cursor;
    appliedSearch.value = query;
    loaded.value = true;
  } catch {
    if (!controller.signal.aborted && controller === activeRequest)
      error.value = t('recruitment.choiceLookupFailed');
  } finally {
    if (controller === activeRequest) busy.value = false;
  }
}

function choiceName(choice: Choice): string {
  if (choice.rank) return `${choice.name} · ${choice.rank.toUpperCase()}`;
  return choice.claimed ? `${choice.name} · ${t('recruitment.claimed')}` : choice.name;
}

function choose(event: Event): void {
  const value = (event.target as HTMLSelectElement).value;
  selected.value =
    items.value.find((item) => item.id === value) ??
    (selected.value?.id === value ? selected.value : null);
  emit('update:modelValue', value);
}
</script>

<template>
  <div class="min-w-0 space-y-2">
    <label :for="id" class="block text-sm font-semibold">{{ label }}</label>
    <select
      :id="id"
      :value="modelValue"
      class="ks-input w-full"
      :aria-busy="busy"
      @focus="!loaded && !busy && load()"
      @change="choose"
    >
      <option value="">{{ label }}</option>
      <option v-if="selectedOffPage && selected" :value="selected.id">
        {{ choiceName(selected) }}
      </option>
      <option v-for="item in items" :key="item.id" :value="item.id">{{ choiceName(item) }}</option>
    </select>
    <label :for="id + '-search'" class="block text-xs text-[var(--ks-muted)]">{{
      t('recruitment.searchChoices')
    }}</label>
    <div class="flex gap-2">
      <input
        :id="id + '-search'"
        v-model="search"
        type="search"
        maxlength="160"
        class="ks-input min-w-0 flex-1"
        @keydown.enter.prevent="load()"
      />
      <AppButton variant="ghost" :disabled="busy" @click="load()">{{
        t('recruitment.findChoices')
      }}</AppButton>
    </div>
    <FormError :message="error" />
    <p v-if="loaded && !items.length" class="text-xs text-[var(--ks-muted)]" aria-live="polite">
      {{ t('recruitment.noChoices') }}
    </p>
    <p v-if="loaded && items.length" class="text-xs text-[var(--ks-muted)]" aria-live="polite">
      {{
        t('recruitment.historyItemsOnPage', {
          count: formatNumber(items.length),
          pageSize: formatNumber(pageSize),
        })
      }}
    </p>
    <div v-if="currentCursor || nextCursor" class="flex flex-wrap gap-2">
      <AppButton v-if="currentCursor" variant="ghost" :disabled="busy" @click="load()">{{
        t('common.firstPage')
      }}</AppButton>
      <AppButton v-if="nextCursor" variant="ghost" :disabled="busy" @click="load(nextCursor)">{{
        t('common.nextPage')
      }}</AppButton>
    </div>
  </div>
</template>
