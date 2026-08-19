import { useForm } from '@inertiajs/vue3';
import { onScopeDispose, watch } from 'vue';

import { useGameContext } from '@/composables/useGameContext';
import {
  activeContextKey,
  registerContextDisposer,
} from '@/identity/context-isolation';

/**
 * Creates an Inertia form whose pending work and transient state belong to the
 * active server-issued Governor context. A Governor/Alliance/Kingdom authority
 * change cancels the request and clears the old context's form state.
 *
 * Platform/account forms intentionally continue using Inertia's plain useForm.
 */
export function useContextForm<TForm extends Record<string, any>>(data: TForm) {
  const form = useForm(data);
  const { context } = useGameContext();
  let registeredContextKey: string | null = null;
  let unregister = () => {};

  function clearContextState(): void {
    form.cancel();
    form.reset();
    form.clearErrors();
  }

  function bind(contextKey: string | null): void {
    unregister();
    registeredContextKey = contextKey;
    unregister = contextKey
      ? registerContextDisposer(contextKey, clearContextState)
      : () => {};
  }

  bind(activeContextKey(context.value));

  const stop = watch(
    () => activeContextKey(context.value),
    (nextContextKey) => {
      if (nextContextKey === registeredContextKey) return;
      clearContextState();
      bind(nextContextKey);
    },
  );

  onScopeDispose(() => {
    stop();
    unregister();
    form.cancel();
  });

  return form;
}
