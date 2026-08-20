<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, shallowRef } from 'vue';

import { useLocale } from '@/localization';

interface BeforeInstallPromptEvent extends Event {
  prompt: () => Promise<void>;
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed'; platform: string }>;
}

const { t } = useLocale();
const installPrompt = shallowRef<BeforeInstallPromptEvent | null>(null);
const updateRegistration = shallowRef<ServiceWorkerRegistration | null>(null);
const offline = ref(!navigator.onLine);

function handleInstallPrompt(event: Event): void {
  event.preventDefault();
  installPrompt.value = event as BeforeInstallPromptEvent;
}

function handleInstalled(): void {
  installPrompt.value = null;
}

function handleOnline(): void {
  offline.value = false;
}

function handleOffline(): void {
  offline.value = true;
}

function handleUpdate(event: Event): void {
  if (!(event instanceof CustomEvent)) return;

  const registration = (event as CustomEvent<{ registration?: ServiceWorkerRegistration }>).detail
    ?.registration;
  if (registration?.waiting) updateRegistration.value = registration;
}

async function install(): Promise<void> {
  const prompt = installPrompt.value;
  if (!prompt) return;

  await prompt.prompt();
  await prompt.userChoice;
  installPrompt.value = null;
}

function applyUpdate(): void {
  const worker = updateRegistration.value?.waiting;
  if (!worker) return;

  navigator.serviceWorker.addEventListener(
    'controllerchange',
    () => window.location.reload(),
    { once: true },
  );
  worker.postMessage({ type: 'SKIP_WAITING' });
}

onMounted(() => {
  window.addEventListener('beforeinstallprompt', handleInstallPrompt);
  window.addEventListener('appinstalled', handleInstalled);
  window.addEventListener('online', handleOnline);
  window.addEventListener('offline', handleOffline);
  window.addEventListener('pwa:update-available', handleUpdate);

  if ('serviceWorker' in navigator) {
    void navigator.serviceWorker
      .getRegistration()
      .then((registration) => {
        if (registration?.waiting) updateRegistration.value = registration;
      })
      .catch(() => undefined);
  }
});

onBeforeUnmount(() => {
  window.removeEventListener('beforeinstallprompt', handleInstallPrompt);
  window.removeEventListener('appinstalled', handleInstalled);
  window.removeEventListener('online', handleOnline);
  window.removeEventListener('offline', handleOffline);
  window.removeEventListener('pwa:update-available', handleUpdate);
});
</script>

<template>
  <aside
    v-if="offline || updateRegistration || installPrompt"
    class="fixed end-3 bottom-3 z-[180] w-[min(25rem,calc(100vw-1.5rem))] rounded-[var(--ks-radius-md)] border border-[var(--ks-border-strong)] bg-[rgba(5,10,11,.97)] p-4 text-sm shadow-2xl backdrop-blur-xl sm:end-5 sm:bottom-5"
    aria-live="polite"
  >
    <p v-if="offline" class="font-semibold text-amber-100">
      {{ t('common.pwaOffline') }}
    </p>
    <p v-else-if="updateRegistration" class="font-semibold text-[var(--ks-gold-bright)]">
      {{ t('common.pwaUpdateReady') }}
    </p>
    <p v-else class="font-semibold text-[var(--ks-ivory)]">
      {{ t('common.pwaInstallReady') }}
    </p>
    <p class="mt-1 leading-6 text-[var(--ks-text-muted)]">
      {{
        offline
          ? t('common.pwaOfflineHelp')
          : updateRegistration
            ? t('common.pwaUpdateHelp')
            : t('common.pwaInstallHelp')
      }}
    </p>
    <div v-if="!offline" class="mt-3 flex flex-wrap gap-2">
      <button
        v-if="updateRegistration"
        type="button"
        class="min-h-11 rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-4 font-bold text-[var(--ks-ink)]"
        @click="applyUpdate"
      >
        {{ t('common.pwaUpdate') }}
      </button>
      <button
        v-if="installPrompt"
        type="button"
        class="min-h-11 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border-strong)] px-4 font-semibold text-[var(--ks-gold-bright)]"
        @click="install"
      >
        {{ t('common.pwaInstall') }}
      </button>
    </div>
  </aside>
</template>
