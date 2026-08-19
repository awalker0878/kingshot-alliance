<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import RoomBanner from '@/components/game/RoomBanner.vue';
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
  <AppLayout>
    <RoomBanner
      :eyebrow="t('kingdomP7A.settingsEyebrow')"
      :title="t('kingdomP7A.settingsTitle')"
      :subtitle="alliance.kingdom ? `#${alliance.kingdom} · ${alliance.name}` : alliance.name"
      image="/images/kingshot/v4/kingdom-map.svg"
      compact
    >
      <template #actions>
        <Link href="/alliance/kingdom-alliances" class="ks-command-link" data-variant="secondary">
          ← {{ t('kingdomP7A.overviewTitle') }}
        </Link>
        <Link href="/alliance/kingdom-ingestion/manage" class="ks-command-link">
          {{ t('kingdomP7A.ingestion') }}
        </Link>
        <Link
          v-if="canManageKingdomRoles"
          href="/alliance/settings/kingdom/roles"
          class="ks-command-link"
        >
          {{ t('kingdomP7A.rolesManage') }}
        </Link>
      </template>
    </RoomBanner>

    <section class="ks-surface-gold mt-5 p-5 sm:p-6" aria-labelledby="kingdom-association-heading">
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
