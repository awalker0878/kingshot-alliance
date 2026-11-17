<script setup lang="ts">
import { ref } from 'vue';

import { useLocale } from '../../localization';
import type { LocaleCode } from '../../localization/locales';

const { locale, localeLoadFailed, availableLocales, setLocale, retryLocale, t } = useLocale();
const changing = ref(false);

async function selectLocale(next?: LocaleCode): Promise<void> {
  changing.value = true;
  try {
    if (next) await setLocale(next);
    else await retryLocale();
  } catch {
    // The coordinator retains the current locale and exposes the failure below.
  } finally {
    changing.value = false;
  }
}

async function changeLocale(event: Event): Promise<void> {
  const select = event.target as HTMLSelectElement;
  await selectLocale(select.value as LocaleCode);
  select.value = locale.value;
}
</script>

<template>
  <label class="inline-flex min-w-0 items-center gap-2 text-sm text-[var(--ks-text-secondary)]">
    <span class="sr-only">{{ t('common.language') }}</span>
    <span class="hidden sm:inline" aria-hidden="true">◎</span>
    <select
      :value="locale"
      :disabled="changing"
      :aria-invalid="localeLoadFailed || undefined"
      class="max-w-28 min-w-0 truncate rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-2.5 py-2 text-sm font-semibold text-[var(--ks-text)] transition outline-none hover:border-[var(--ks-border-strong)] focus:border-[var(--ks-blue)] sm:max-w-44 sm:px-3"
      :aria-label="t('common.language')"
      @change="changeLocale"
    >
      <option v-for="item in availableLocales" :key="item.code" :value="item.code">
        {{ item.nativeName }}
      </option>
    </select>
  </label>
  <span v-if="localeLoadFailed" role="alert" class="text-sm text-[var(--ks-text-secondary)]">
    {{ t('common.language') }}: {{ t('giftCodes.needsAttention') }}.
    <button type="button" class="underline" :disabled="changing" @click="selectLocale()">
      {{ t('giftCodes.tryAgain') }}
    </button>
  </span>
</template>
