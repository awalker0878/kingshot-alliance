<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import ActionNotice from '@/components/ui/ActionNotice.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmActionDialog from '@/components/ui/ConfirmActionDialog.vue';
import FormError from '@/components/ui/FormError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

const props = defineProps<{
  user: { name: string; email: string };
  player: { id: string; name: string };
  alliance: { id: string; name: string };
  providers: string[];
  links: Array<{
    id: string;
    provider: string;
    subjectHint: string;
    verifiedAt: string;
    revokedAt: string | null;
  }>;
  issuedPairing: { id: string; provider: string; code: string; expiresAt: string } | null;
}>();

const { t, formatDate } = useLocale();
const pairingForm = useForm({ provider: props.providers[0] ?? 'discord' });
const pendingRevoke = ref<{ id: string; provider: string } | null>(null);
const revoking = ref(false);
const mutationError = ref<string | null>(null);
const activeLinks = computed(() => props.links.filter((link) => link.revokedAt === null));

function providerLabel(provider: string): string {
  return provider === 'telegram'
    ? t('accountExperience.connections.telegram')
    : t('accountExperience.connections.discord');
}

function issuePairingCode(): void {
  mutationError.value = null;
  pairingForm.post('/profile/connections/pairing-codes', {
    preserveScroll: true,
    onError: (errors) => {
      mutationError.value = Object.values(errors)[0] ?? t('accountExperience.connections.failed');
    },
  });
}

function requestRevoke(id: string, provider: string): void {
  pendingRevoke.value = { id, provider };
}

function confirmRevoke(): void {
  const target = pendingRevoke.value;
  if (!target || revoking.value) return;

  revoking.value = true;
  mutationError.value = null;
  router.delete(`/profile/connections/${target.id}`, {
    preserveScroll: true,
    onSuccess: () => (pendingRevoke.value = null),
    onError: (errors) => {
      mutationError.value = Object.values(errors)[0] ?? t('accountExperience.connections.failed');
    },
    onFinish: () => (revoking.value = false),
  });
}
</script>

<template>
  <Head :title="t('accountExperience.connections.title')" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <RoomBanner
      :eyebrow="t('accountExperience.connections.eyebrow')"
      :title="t('accountExperience.connections.title')"
      :subtitle="t('accountExperience.connections.subtitle', { player: player.name })"
      image="/images/kingshot/v4/connections.svg"
      compact
    >
      <template #actions>
        <Link href="/profile" class="ks-command-link">
          {{ t('accountExperience.connections.back') }}
        </Link>
      </template>
    </RoomBanner>

    <ActionNotice class="mt-5" :message="mutationError" tone="danger" />

    <section
      v-if="issuedPairing"
      class="mt-5 rounded-[var(--ks-radius-lg)] border border-amber-400/30 bg-amber-500/[.07] p-5 sm:p-6"
      aria-labelledby="issued-pairing-heading"
    >
      <p class="ks-kicker">{{ providerLabel(issuedPairing.provider) }}</p>
      <h2 id="issued-pairing-heading" class="ks-display mt-1 text-2xl font-semibold text-amber-100">
        {{ t('accountExperience.connections.pairNow') }}
      </h2>
      <p class="mt-2 max-w-3xl text-sm leading-6 text-amber-100/80">
        {{ t('accountExperience.connections.oneTime') }}
      </p>
      <code
        class="mt-4 block w-fit rounded-[var(--ks-radius-sm)] bg-black/35 px-4 py-3 text-xl font-bold tracking-[.14em] text-[var(--ks-ivory)]"
        >{{ issuedPairing.code }}</code
      >
      <p class="mt-3 text-xs text-[var(--ks-muted)]">
        {{
          t('accountExperience.connections.expires', {
            time: formatDate(issuedPairing.expiresAt, { dateStyle: 'medium', timeStyle: 'short' }),
          })
        }}
      </p>
    </section>

    <div class="mt-5 grid gap-5 xl:grid-cols-2">
      <section class="ks-surface p-5 sm:p-6" aria-labelledby="pair-provider-heading">
        <p class="ks-kicker">{{ t('accountExperience.connections.pairing') }}</p>
        <h2 id="pair-provider-heading" class="ks-display mt-1 text-2xl font-semibold">
          {{ t('accountExperience.connections.createCode') }}
        </h2>
        <p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('accountExperience.connections.help') }}
        </p>

        <form class="mt-5 grid gap-4" @submit.prevent="issuePairingCode">
          <div>
            <label for="actor-provider" class="text-sm font-semibold">
              {{ t('accountExperience.connections.provider') }}
            </label>
            <select id="actor-provider" v-model="pairingForm.provider" class="ks-input mt-2">
              <option v-for="provider in providers" :key="provider" :value="provider">
                {{ providerLabel(provider) }}
              </option>
            </select>
            <FormError :message="pairingForm.errors.provider" />
          </div>
          <AppButton class="w-fit" type="submit" :disabled="pairingForm.processing">
            {{
              pairingForm.processing
                ? t('accountExperience.connections.creating')
                : t('accountExperience.connections.createCode')
            }}
          </AppButton>
        </form>
      </section>

      <section class="ks-surface overflow-hidden" aria-labelledby="linked-providers-heading">
        <div class="border-b border-[var(--ks-border)] p-5 sm:p-6">
          <p class="ks-kicker">{{ t('accountExperience.connections.linked') }}</p>
          <h2 id="linked-providers-heading" class="ks-display mt-1 text-2xl font-semibold">
            {{ t('accountExperience.connections.activeLinks', { count: activeLinks.length }) }}
          </h2>
        </div>
        <div v-if="links.length" class="divide-y divide-[var(--ks-border)]">
          <article
            v-for="link in links"
            :key="link.id"
            class="flex flex-wrap items-center gap-3 p-5"
          >
            <div class="min-w-0 flex-1">
              <p class="font-semibold">{{ providerLabel(link.provider) }}</p>
              <p class="mt-1 text-xs text-[var(--ks-muted)]">
                {{ link.subjectHint }} ·
                {{ formatDate(link.verifiedAt, { dateStyle: 'medium', timeStyle: 'short' }) }}
              </p>
            </div>
            <span class="ks-status" :data-tone="link.revokedAt ? 'danger' : 'success'">
              {{
                link.revokedAt
                  ? t('accountExperience.connections.revoked')
                  : t('accountExperience.connections.active')
              }}
            </span>
            <AppButton
              v-if="!link.revokedAt"
              type="button"
              variant="danger"
              @click="requestRevoke(link.id, link.provider)"
            >
              {{ t('accountExperience.connections.revoke') }}
            </AppButton>
          </article>
        </div>
        <p v-else class="p-5 text-sm text-[var(--ks-muted)]">
          {{ t('accountExperience.connections.empty') }}
        </p>
      </section>
    </div>

    <ConfirmActionDialog
      id="external-actor-revoke-confirmation"
      :open="pendingRevoke !== null"
      :title="t('accountExperience.connections.revokeTitle')"
      :description="
        pendingRevoke
          ? t('accountExperience.connections.revokeDescription', {
              provider: providerLabel(pendingRevoke.provider),
            })
          : ''
      "
      :confirm-label="t('accountExperience.connections.revoke')"
      :cancel-label="t('common.cancel')"
      :busy="revoking"
      :busy-label="t('accountExperience.connections.revoking')"
      danger
      @confirm="confirmRevoke"
      @cancel="pendingRevoke = null"
    />
  </AppLayout>
</template>
