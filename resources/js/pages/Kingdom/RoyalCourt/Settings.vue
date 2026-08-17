<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string; kingdom: string | null };
  canManageKingdomRoles: boolean;
}>();

const { t } = useLocale();
</script>

<template>
  <Head :title="`${t('kingdomP7A.settingsTitle')} · ${alliance.name}`" />
  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <header class="flex flex-wrap items-start justify-between gap-5">
      <div class="max-w-3xl">
        <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('kingdomP7A.settingsEyebrow') }}
        </p>
        <h1 class="ks-display mt-2 text-3xl font-bold sm:text-4xl">
          {{ t('kingdomP7A.settingsTitle') }}
        </h1>
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
        <Link
          v-if="canManageKingdomRoles"
          href="/alliance/settings/kingdom/roles"
          class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold"
          >{{ t('kingdomP7A.rolesManage') }}</Link
        >
      </div>
    </header>

    <section class="ks-surface mt-6 p-5" aria-labelledby="kingdom-association-heading">
      <p
        id="kingdom-association-heading"
        class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase"
      >
        {{ t('kingdomP7A.association') }}
      </p>
      <p class="ks-display mt-3 text-3xl font-bold text-[var(--ks-gold)]">
        {{ alliance.kingdom ? `#${alliance.kingdom}` : t('kingdomP7A.notConfigured') }}
      </p>
    </section>
  </AppLayout>
</template>
