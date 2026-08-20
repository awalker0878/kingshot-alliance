<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue';

import AppButton from '@/components/ui/AppButton.vue';

const props = withDefaults(
  defineProps<{
    open: boolean;
    title: string;
    description: string;
    confirmLabel: string;
    cancelLabel: string;
    busy?: boolean;
    busyLabel?: string;
    danger?: boolean;
  }>(),
  { busy: false, busyLabel: '', danger: false },
);

const emit = defineEmits<{
  confirm: [];
  cancel: [];
}>();

const dialog = ref<HTMLDialogElement | null>(null);
const isBusy = computed(() => props.busy === true);
const resolvedBusyLabel = computed(() => props.busyLabel ?? '');

async function syncDialog(open: boolean): Promise<void> {
  await nextTick();
  const element = dialog.value;
  if (!element) return;

  if (open && !element.open) {
    element.showModal();
    element.querySelector<HTMLButtonElement>('[data-confirm-cancel]')?.focus();
  } else if (!open && element.open) {
    element.close();
  }
}

function cancel(event?: Event): void {
  event?.preventDefault();
  if (!isBusy.value) emit('cancel');
}

watch(() => props.open, syncDialog);
onMounted(() => syncDialog(props.open));
</script>

<template>
  <dialog
    ref="dialog"
    class="m-auto w-[min(32rem,calc(100%-2rem))] rounded-[var(--ks-radius-lg)] border border-[var(--ks-border-strong)] bg-[var(--ks-surface)] p-0 text-[var(--ks-text)] shadow-2xl backdrop:bg-black/75 backdrop:backdrop-blur-sm"
    :aria-labelledby="`${$attrs.id ?? 'confirm-action'}-title`"
    :aria-describedby="`${$attrs.id ?? 'confirm-action'}-description`"
    @cancel="cancel"
  >
    <div class="p-5 sm:p-6">
      <h2 :id="`${$attrs.id ?? 'confirm-action'}-title`" class="ks-display text-2xl font-semibold">
        {{ title }}
      </h2>
      <p
        :id="`${$attrs.id ?? 'confirm-action'}-description`"
        class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]"
      >
        {{ description }}
      </p>
      <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <AppButton data-confirm-cancel variant="ghost" :disabled="isBusy" @click="cancel()">
          {{ cancelLabel }}
        </AppButton>
        <AppButton
          :variant="danger ? 'danger' : 'primary'"
          :busy="isBusy"
          :busy-label="resolvedBusyLabel"
          @click="emit('confirm')"
        >
          {{ confirmLabel }}
        </AppButton>
      </div>
    </div>
  </dialog>
</template>
