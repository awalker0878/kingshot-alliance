<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppButton from '@/components/ui/AppButton.vue';
import { useLocale } from '@/localization';

type Choice = { id: string; name: string };
const props = defineProps<{
  id: string;
  kind: 'categories' | 'media';
  modelValue: string;
  label: string;
  emptyLabel: string;
}>();
const emit = defineEmits<{ 'update:modelValue': [id: string] }>();
const { t } = useLocale();
const search = ref('');
const appliedSearch = ref('');
const items = ref<Choice[]>([]);
const selected = ref<Choice | null>(null);
const next = ref<string | null>(null);
const cursor = ref<string | null>(null);
const busy = ref(false);
const failed = ref(false);
const loaded = ref(false);
let active: AbortController | null = null;
const offPage = computed(
  () => props.modelValue && !items.value.some((item) => item.id === props.modelValue),
);
onBeforeUnmount(() => active?.abort());
watch(
  () => props.modelValue,
  (value) => {
    if (value) void load();
    else selected.value = null;
  },
  { immediate: true },
);
async function load(after: string | null = null): Promise<void> {
  active?.abort();
  const request = new AbortController();
  active = request;
  const chosen = props.modelValue;
  const q = after === null ? search.value.trim() : appliedSearch.value;
  const params = new URLSearchParams({ q });
  if (after) params.set('cursor', after);
  if (chosen) params.set('selected', chosen);
  busy.value = true;
  failed.value = false;
  try {
    const response = await fetch(`/alliance/content/manage/options/${props.kind}?${params}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
      signal: request.signal,
    });
    if (!response.ok) throw new Error('Content options could not be loaded.');
    const data = (await response.json()) as {
      page: { items: Choice[]; nextCursor: string | null };
      selected: Choice | null;
    };
    if (request !== active) return;
    items.value = data.page.items;
    next.value = data.page.nextCursor;
    cursor.value = after;
    if (props.modelValue === chosen) selected.value = data.selected;
    appliedSearch.value = q;
    loaded.value = true;
  } catch {
    if (request === active && !request.signal.aborted) {
      failed.value = true;
      items.value = [];
      next.value = null;
    }
  } finally {
    if (request === active) busy.value = false;
  }
}
function choose(event: Event): void {
  const id = (event.target as HTMLSelectElement).value;
  selected.value =
    items.value.find((item) => item.id === id) ??
    (selected.value?.id === id ? selected.value : null);
  emit('update:modelValue', id);
}
</script>
<template>
  <div class="space-y-2">
    <label :for="id" class="block text-sm">{{ label }}</label>
    <select
      :id="id"
      :value="modelValue"
      class="ks-input w-full"
      :aria-busy="busy"
      @focus="!loaded && !busy && load()"
      @change="choose"
    >
      <option value="">{{ emptyLabel }}</option>
      <option v-if="offPage" :value="modelValue">
        {{ selected?.name ?? t('contentExperience.selectedUnavailable') }}
      </option>
      <option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</option>
    </select>
    <label :for="id + '-search'" class="block text-xs text-[var(--ks-muted)]">{{
      t('contentExperience.search')
    }}</label>
    <div class="flex gap-2">
      <input
        :id="id + '-search'"
        v-model="search"
        class="ks-input min-w-0 flex-1"
        type="search"
        maxlength="160"
        @keydown.enter.prevent.stop="load()"
      />
      <AppButton type="button" variant="ghost" :disabled="busy" @click="load()">{{
        t('contentExperience.search')
      }}</AppButton>
    </div>
    <p v-if="failed" role="alert" class="text-sm">{{ t('contentExperience.collectionFailed') }}</p>
    <div v-if="cursor || next" class="flex gap-2">
      <AppButton v-if="cursor" type="button" variant="ghost" :disabled="busy" @click="load()">{{
        t('common.firstPage')
      }}</AppButton>
      <AppButton v-if="next" type="button" variant="ghost" :disabled="busy" @click="load(next)">{{
        t('common.nextPage')
      }}</AppButton>
    </div>
  </div>
</template>
