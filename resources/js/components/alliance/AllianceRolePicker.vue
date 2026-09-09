<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';

import AppButton from '@/components/ui/AppButton.vue';
import FormError from '@/components/ui/FormError.vue';
import { useLocale } from '@/localization';

type RoleOption = { id: string; key: string; name: string; system: boolean };
type RolePage = { items: RoleOption[]; nextCursor: string | null };

const props = defineProps<{ id: string; modelValue?: string | undefined; label: string }>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
const { t } = useLocale();
const search = ref('');
const appliedSearch = ref('');
const items = ref<RoleOption[]>([]);
const selected = ref<RoleOption | null>(null);
const nextCursor = ref<string | null>(null);
const currentCursor = ref<string | null>(null);
const busy = ref(false);
const loaded = ref(false);
const error = ref<string | null>(null);
let activeRequest: AbortController | null = null;

const selectedOffPage = computed(
  () =>
    selected.value?.id === props.modelValue &&
    !items.value.some((role) => role.id === props.modelValue),
);

watch(
  () => props.modelValue,
  (value) => {
    if (!value) selected.value = null;
  },
);
onBeforeUnmount(() => activeRequest?.abort());

async function load(cursor: string | null = null): Promise<void> {
  activeRequest?.abort();
  const controller = new AbortController();
  activeRequest = controller;
  const query = cursor === null ? search.value.trim() : appliedSearch.value;
  const params = new URLSearchParams({ q: query });
  if (cursor) params.set('cursor', cursor);
  busy.value = true;
  error.value = null;
  try {
    const response = await fetch('/alliance/roles/options?' + params.toString(), {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
      signal: controller.signal,
    });
    if (!response.ok) throw new Error('Role lookup failed.');
    const page = (await response.json()) as RolePage;
    if (controller !== activeRequest) return;
    items.value = page.items;
    nextCursor.value = page.nextCursor;
    currentCursor.value = cursor;
    appliedSearch.value = query;
    loaded.value = true;
  } catch {
    if (!controller.signal.aborted && controller === activeRequest) {
      error.value = t('allianceExpansion.roleLookupFailed');
    }
  } finally {
    if (controller === activeRequest) busy.value = false;
  }
}

function choose(event: Event): void {
  const value = (event.target as HTMLSelectElement).value;
  selected.value = items.value.find((role) => role.id === value) ?? null;
  emit('update:modelValue', value);
}
</script>

<template>
  <div class="min-w-0 space-y-2">
    <label :for="id" class="block text-sm font-semibold">{{ label }}</label>
    <select
      :id="id"
      :value="modelValue ?? ''"
      class="ks-input w-full"
      :aria-busy="busy"
      @focus="!loaded && !busy && load()"
      @change="choose"
    >
      <option value="">{{ t('allianceOperations.overview.selectRole') }}</option>
      <option v-if="selectedOffPage && selected" :value="selected.id">{{ selected.name }}</option>
      <option v-for="role in items" :key="role.id" :value="role.id">{{ role.name }}</option>
    </select>
    <label :for="id + '-search'" class="block text-xs text-[var(--ks-muted)]">
      {{ t('allianceExpansion.roleSearch') }}
    </label>
    <div class="flex gap-2">
      <input
        :id="id + '-search'"
        v-model="search"
        type="search"
        maxlength="100"
        class="ks-input min-w-0 flex-1"
        @keydown.enter.prevent="load()"
      />
      <AppButton variant="ghost" :disabled="busy" @click="load()">
        {{ t('allianceExpansion.findRoles') }}
      </AppButton>
    </div>
    <FormError :message="error" />
    <p v-if="loaded && !items.length" class="text-xs text-[var(--ks-muted)]" aria-live="polite">
      {{ t('allianceExpansion.noRoles') }}
    </p>
    <div v-if="currentCursor || nextCursor" class="flex flex-wrap gap-2">
      <AppButton v-if="currentCursor" variant="ghost" :disabled="busy" @click="load()">
        {{ t('common.firstPage') }}
      </AppButton>
      <AppButton v-if="nextCursor" variant="ghost" :disabled="busy" @click="load(nextCursor)">
        {{ t('common.nextPage') }}
      </AppButton>
    </div>
  </div>
</template>
