<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import RoomBanner from '@/components/game/RoomBanner.vue';
import { useLocale } from '@/localization';
type Kingdom = { id: string; number: number };
type Player = { id: string; kingdomId: string; name: string; gamePlayerId: string | null };
const props = defineProps<{ user: { name: string; email: string }; kingdoms: Kingdom[]; players: Player[] }>();
const { t } = useLocale();
const form = useForm({ kingdom_id: props.kingdoms[0]?.id ?? '', player_id: '', reason: '', replace_existing: false });
const eligiblePlayers = computed(() => props.players.filter((player) => player.kingdomId === form.kingdom_id));
function recover(): void { form.post('/platform/kingdom-governance-recovery', { preserveScroll: true, onSuccess: () => form.reset('reason', 'replace_existing') }); }
</script>
<template>
  <Head :title="t('governanceExpansion.recoveryTitle')" />
  <AppLayout :user="user" :has-player-alliance="false">
    <RoomBanner :eyebrow="t('governanceExpansion.eyebrow')" :title="t('governanceExpansion.recoveryTitle')" :subtitle="t('governanceExpansion.recoveryHelp')" image="/images/kingshot/v4/kingdom-map.svg" compact><template #actions><Link href="/platform" class="ks-command-link">← Platform</Link></template></RoomBanner>
    <section class="ks-surface mt-5 max-w-3xl p-5"><div class="rounded border border-amber-400/40 p-4 text-sm">{{ t('governanceExpansion.recoveryWarning') }}</div><form class="mt-5 space-y-4" @submit.prevent="recover"><label class="block text-sm font-semibold">{{ t('governanceExpansion.kingdom') }}<select v-model="form.kingdom_id" class="ks-input mt-2" @change="form.player_id = ''"><option v-for="kingdom in kingdoms" :key="kingdom.id" :value="kingdom.id">#{{ kingdom.number }}</option></select></label><label class="block text-sm font-semibold">{{ t('governanceExpansion.replacementGovernor') }}<select v-model="form.player_id" class="ks-input mt-2"><option value="">—</option><option v-for="player in eligiblePlayers" :key="player.id" :value="player.id">{{ player.name }}{{ player.gamePlayerId ? ` · ${player.gamePlayerId}` : '' }}</option></select></label><label class="block text-sm font-semibold">{{ t('governanceExpansion.recoveryReason') }}<textarea v-model="form.reason" class="ks-input mt-2 min-h-28" minlength="10" maxlength="500" /></label><label class="flex gap-2 text-sm"><input v-model="form.replace_existing" type="checkbox" />{{ t('governanceExpansion.replaceExisting') }}</label><button type="submit" class="ks-command-button" :disabled="form.processing || !form.player_id">{{ t('governanceExpansion.recover') }}</button></form></section>
  </AppLayout>
</template>
