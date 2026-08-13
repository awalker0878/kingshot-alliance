<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import AppLayout from '../../layouts/AppLayout.vue';
import { useLocale } from '../../localization';

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string; kingdom: string | null };
}>();

const { t } = useLocale();
const form = useForm({ kingdom: props.alliance.kingdom ?? '' });
const kingdomDescription = computed(() =>
  form.errors.kingdom ? 'kingdom-number-help kingdom-number-error' : 'kingdom-number-help',
);

function saveKingdom(): void {
  form.patch('/alliance/settings/kingdom', { preserveScroll: true });
}
</script>

<template>
  <Head :title="`${t('kingdomP7A.settingsTitle')} · ${alliance.name}`" />
  <AppLayout :user="user" :alliance-name="alliance.name" :has-active-alliance="true">
    <header class="flex flex-wrap items-start justify-between gap-5">
      <div class="max-w-3xl">
        <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('kingdomP7A.settingsEyebrow') }}
        </p>
        <h1 class="ks-display mt-2 text-3xl font-bold sm:text-4xl">
          {{ t('kingdomP7A.settingsTitle') }}
        </h1>
        <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('kingdomP7A.settingsSubtitle', { alliance: alliance.name }) }}
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <Link
          href="/alliance/kingdom-alliances"
          class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold"
          >{{ t('kingdomP7A.overviewTitle') }}</Link
        >
        <Link
          href="/alliance/kingdom-ingestion/manage"
          class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold"
          >{{ t('kingdomP7A.ingestion') }}</Link
        >
      </div>
    </header>

    <div class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(260px,0.38fr)]">
      <section class="ks-surface p-5" aria-labelledby="kingdom-settings-heading">
        <h2 id="kingdom-settings-heading" class="ks-display text-xl font-semibold">
          {{ t('kingdomP7A.settingsTitle') }}
        </h2>
        <form class="mt-5" @submit.prevent="saveKingdom">
          <label class="text-sm font-semibold" for="kingdom-number">{{
            t('kingdomP7A.kingdomNumber')
          }}</label>
          <input
            id="kingdom-number"
            v-model="form.kingdom"
            :aria-describedby="kingdomDescription"
            :aria-invalid="form.errors.kingdom ? 'true' : undefined"
            class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/20 px-3 py-2.5"
            inputmode="numeric"
            pattern="[1-9][0-9]*"
            type="text"
          />
          <p id="kingdom-number-help" class="mt-2 text-xs leading-5 text-[var(--ks-text-muted)]">
            {{ t('kingdomP7A.kingdomNumberHelp') }}
          </p>
          <p
            v-if="form.errors.kingdom"
            id="kingdom-number-error"
            class="mt-2 text-sm text-red-300"
            role="alert"
          >
            {{ form.errors.kingdom }}
          </p>
          <button
            class="mt-5 rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60"
            :disabled="form.processing"
            type="submit"
          >
            {{ t('kingdomP7A.saveKingdom') }}
          </button>
        </form>
      </section>

      <aside class="ks-surface p-5">
        <p class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
          {{ t('kingdomP7A.association') }}
        </p>
        <p class="ks-display mt-3 text-3xl font-bold text-[var(--ks-gold)]">
          {{ alliance.kingdom ? `#${alliance.kingdom}` : t('kingdomP7A.notConfigured') }}
        </p>
        <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('kingdomP7A.kingdomNumberHelp') }}
        </p>
      </aside>
    </div>
  </AppLayout>
</template>
