<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import RecoveryChoicePicker from '@/components/platform/RecoveryChoicePicker.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import RoomBanner from '@/components/game/RoomBanner.vue';
import { useLocale } from '@/localization';
const props = defineProps<{
  user: { name: string; email: string };
  actorId: number;
}>();
const { t } = useLocale();
const form = useForm({
  kingdom_id: '',
  player_id: '',
  reason: '',
  replace_existing: false,
});
const failed = ref(false);
watch(
  () => props.actorId,
  () => {
    form.reset();
    form.clearErrors();
    failed.value = false;
  },
);
watch(
  () => form.kingdom_id,
  () => {
    form.reset('player_id', 'reason', 'replace_existing');
    form.clearErrors();
    failed.value = false;
  },
);
function recover(): void {
  failed.value = false;
  form.post('/platform/kingdom-governance-recovery', {
    preserveScroll: true,
    onSuccess: () => form.reset('reason', 'replace_existing'),
    onHttpException: () => {
      failed.value = true;
      return false;
    },
    onNetworkError: () => {
      failed.value = true;
      return false;
    },
  });
}
</script>
<template>
  <Head :title="t('governanceExpansion.recoveryTitle')" />
  <AppLayout :user="user" :has-player-alliance="false">
    <RoomBanner
      :eyebrow="t('governanceExpansion.eyebrow')"
      :title="t('governanceExpansion.recoveryTitle')"
      :subtitle="t('governanceExpansion.recoveryHelp')"
      image="/images/kingshot/v4/kingdom-map.svg"
      compact
      ><template #actions
        ><Link href="/platform" class="ks-command-link"
          >← {{ t('platformAdmin.title') }}</Link
        ></template
      ></RoomBanner
    >
    <section class="ks-surface mt-5 max-w-3xl p-5">
      <div class="rounded border border-amber-400/40 p-4 text-sm">
        {{ t('governanceExpansion.recoveryWarning') }}
      </div>
      <form class="mt-5 space-y-4" @submit.prevent="recover">
        <RecoveryChoicePicker
          id="recovery-kingdom"
          v-model="form.kingdom_id"
          kind="kingdoms"
          :scope="String(actorId)"
          :label="t('governanceExpansion.kingdom')"
          empty-label="—"
          required
        />
        <RecoveryChoicePicker
          id="recovery-player"
          v-model="form.player_id"
          kind="players"
          :kingdom-id="form.kingdom_id"
          :scope="`${actorId}:${form.kingdom_id}`"
          :label="t('governanceExpansion.replacementGovernor')"
          empty-label="—"
          :disabled="!form.kingdom_id"
          required
        />
        <label class="block text-sm font-semibold"
          >{{ t('governanceExpansion.recoveryReason')
          }}<textarea
            id="recovery-reason"
            v-model="form.reason"
            class="ks-input mt-2 min-h-28"
            minlength="10"
            maxlength="500"
          /></label
        ><label class="flex gap-2 text-sm"
          ><input v-model="form.replace_existing" type="checkbox" />{{
            t('governanceExpansion.replaceExisting')
          }}</label
        ><button
          type="submit"
          class="ks-command-button"
          :disabled="form.processing || !form.player_id"
        >
          {{ t('governanceExpansion.recover') }}
        </button>
        <div
          v-if="Object.keys(form.errors).length || failed"
          role="alert"
          class="text-sm text-rose-300"
        >
          <p v-for="(error, key) in form.errors" :key="key">{{ error }}</p>
          <p v-if="failed">{{ t('platformAdmin.recoveryFailed') }}</p>
        </div>
      </form>
    </section>
  </AppLayout>
</template>
