<script setup lang="ts">
import { useLocale } from '../../localization';
import type { LocaleCode } from '../../localization/locales';

const { locale, availableLocales, setLocale, t } = useLocale();

function changeLocale(event: Event): void {
  setLocale((event.target as HTMLSelectElement).value as LocaleCode);
}
</script>

<template>
  <label class="inline-flex min-w-0 items-center gap-2 text-sm text-[var(--ks-text-secondary)]">
    <span class="sr-only">{{ t('common.language') }}</span>
    <span class="hidden sm:inline" aria-hidden="true">◎</span>
    <select
      :value="locale"
      class="min-w-0 max-w-28 truncate rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg-elevated)] px-2.5 py-2 text-sm font-semibold text-[var(--ks-text)] outline-none transition hover:border-[var(--ks-border-strong)] focus:border-[var(--ks-blue)] sm:max-w-44 sm:px-3"
      :aria-label="t('common.language')"
      @change="changeLocale"
    >
      <option v-for="item in availableLocales" :key="item.code" :value="item.code">
        {{ item.nativeName }}
      </option>
    </select>
  </label>
</template>
