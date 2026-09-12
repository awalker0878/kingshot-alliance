<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useLocale } from '@/localization';
import { RecoveryChoiceLoader } from './recoveryChoices';
import type { RecoveryChoice, RecoveryChoiceKind, RecoveryChoiceView } from './recoveryChoices';

const props = defineProps<{
  id: string;
  kind: RecoveryChoiceKind;
  scope: string;
  kingdomId?: string;
  modelValue: string;
  selectedName?: string | undefined;
  label: string;
  emptyLabel: string;
  disabled?: boolean;
  required?: boolean;
}>();
const emit = defineEmits<{ 'update:modelValue': [id: string] }>();
const { t } = useLocale();
const search = ref('');
const chosen = ref<RecoveryChoice | null>(null);
const view = ref<RecoveryChoiceView>({
  result: null,
  busy: false,
  failed: false,
  search: '',
  cursor: null,
});
const loader = new RecoveryChoiceLoader(
  (url, init) => fetch(url, init),
  (next) => {
    view.value = next;
  },
);
const selected = computed(() =>
  [chosen.value, view.value.result?.selected].find((item) => item?.id === props.modelValue),
);
const offPage = computed(
  () =>
    props.modelValue && !view.value.result?.page.items.some((item) => item.id === props.modelValue),
);
watch(
  () => [props.scope, props.kind, props.kingdomId] as const,
  () => {
    search.value = '';
    chosen.value = null;
    loader.reset();
  },
);
onBeforeUnmount(() => loader.reset());

function load(cursor: string | null = null): void {
  if (props.disabled) return;
  void loader.load({
    scope: props.scope,
    kind: props.kind,
    kingdomId: props.kingdomId ?? null,
    search: cursor === null ? search.value.trim() : view.value.search,
    cursor,
    selectedId: props.modelValue || null,
  });
}
function choose(event: Event): void {
  const id = (event.target as HTMLSelectElement).value;
  chosen.value =
    view.value.result?.page.items.find((item) => item.id === id) ??
    (selected.value?.id === id ? selected.value : null);
  emit('update:modelValue', id);
}
</script>

<template>
  <div class="space-y-2" data-testid="recovery-choice-picker">
    <label :for="id" class="block text-sm">{{ label }}</label>
    <select
      :id="id"
      :value="modelValue"
      class="ks-input w-full"
      :disabled="disabled"
      :required="required"
      :aria-busy="view.busy"
      @focus="!view.result && !view.busy && load()"
      @change="choose"
    >
      <option value="">{{ emptyLabel }}</option>
      <option v-if="offPage" :value="modelValue">
        {{ selected?.name ?? selectedName ?? t('platformAdmin.choiceUnavailable') }}
      </option>
      <option v-for="item in view.result?.page.items ?? []" :key="item.id" :value="item.id">
        {{ item.name }}
      </option>
    </select>
    <div class="flex gap-2">
      <input
        :id="id + '-search'"
        v-model="search"
        class="ks-input min-w-0 flex-1"
        type="search"
        maxlength="160"
        :disabled="disabled"
        :aria-label="t('platformAdmin.searchChoices')"
        @keydown.enter.prevent.stop="load()"
      />
      <button
        type="button"
        class="ks-command-link"
        :disabled="disabled || view.busy"
        @click="load()"
      >
        {{ t('platformAdmin.searchChoices') }}
      </button>
    </div>
    <p v-if="view.result" class="text-xs text-[var(--ks-muted)]" aria-live="polite">
      {{
        t('platformAdmin.choicePageSummary', {
          count: view.result.page.items.length,
          total: view.result.total,
        })
      }}
    </p>
    <div v-if="view.cursor || view.result?.page.hasMore" class="flex gap-2">
      <button
        v-if="view.cursor"
        type="button"
        class="ks-command-link"
        :disabled="disabled || view.busy"
        @click="load()"
      >
        {{ t('common.firstPage') }}
      </button>
      <button
        v-if="view.result?.page.hasMore"
        type="button"
        class="ks-command-link"
        :disabled="disabled || view.busy"
        @click="load(view.result.page.nextCursor)"
      >
        {{ t('common.nextPage') }}
      </button>
    </div>
    <div v-if="view.failed" role="alert" class="text-sm">
      <p>{{ t('platformAdmin.choicesFailed') }}</p>
      <button
        type="button"
        class="ks-command-link"
        :disabled="disabled || view.busy"
        @click="loader.retry()"
      >
        {{ t('platformAdmin.retryChoices') }}
      </button>
    </div>
  </div>
</template>
