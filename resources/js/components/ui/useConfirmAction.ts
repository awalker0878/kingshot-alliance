import { computed, ref, shallowRef } from 'vue';

export type ConfirmationFinish = () => void;

export type ConfirmationRequest = {
  id: string;
  title: string;
  description: string;
  confirmLabel: string;
  cancelLabel: string;
  busyLabel?: string;
  danger?: boolean;
  perform: (finish: ConfirmationFinish) => void;
};

const emptyConfirmation = {
  id: 'confirm-action',
  open: false,
  title: '',
  description: '',
  confirmLabel: '',
  cancelLabel: '',
  busy: false,
  busyLabel: '',
  danger: false,
};

export function useConfirmAction() {
  const pending = shallowRef<ConfirmationRequest | null>(null);
  const busy = ref(false);

  const dialog = computed(() => {
    const request = pending.value;
    if (request === null) return emptyConfirmation;

    return {
      id: request.id,
      open: true,
      title: request.title,
      description: request.description,
      confirmLabel: request.confirmLabel,
      cancelLabel: request.cancelLabel,
      busy: busy.value,
      busyLabel: request.busyLabel ?? request.confirmLabel,
      danger: request.danger ?? true,
    };
  });

  function requestConfirmation(request: ConfirmationRequest): void {
    if (busy.value) return;
    pending.value = request;
  }

  function cancelConfirmation(): void {
    if (busy.value) return;
    pending.value = null;
  }

  function confirmAction(): void {
    const request = pending.value;
    if (request === null || busy.value) return;

    busy.value = true;
    let finished = false;

    const finish = (): void => {
      if (finished) return;
      finished = true;
      busy.value = false;
      if (pending.value === request) pending.value = null;
    };

    try {
      request.perform(finish);
    } catch (error) {
      finish();
      throw error;
    }
  }

  return {
    dialog,
    requestConfirmation,
    cancelConfirmation,
    confirmAction,
  };
}
