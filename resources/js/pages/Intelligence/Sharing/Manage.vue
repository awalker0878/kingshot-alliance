<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type SharedTarget = {
  id: string;
  trackingId: string;
  state: string;
  name: string;
  tag: string | null;
  sharedAt: string;
  removedAt: string | null;
};

type OutboundShare = {
  id: string;
  state: string;
  kingdomId: string;
  recipientAlliance: { id: string; name: string } | null;
  invitationExpiresAt: string;
  invitationUsedAt: string | null;
  acceptedAt: string | null;
  declinedAt: string | null;
  revokedAt: string | null;
  targets: SharedTarget[];
};

type InboundShare = {
  id: string;
  state: string;
  kingdomId: string;
  sourceAlliance: { id: string; name: string };
  acceptedAt: string | null;
  declinedAt: string | null;
  revokedAt: string | null;
};

type TrackableTarget = {
  id: string;
  name: string;
  tag: string | null;
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string; kingdom: string | null };
  passwordConfirmUrl: string;
  sharing: {
    outbound: OutboundShare[];
    inbound: InboundShare[];
    trackableTargets: TrackableTarget[];
  };
}>();

const { t, formatDate: localeFormatDate, formatNumber } = useLocale();

const invitationToken = ref<string | null>(null);
const invitationError = ref<string | null>(null);
const invitationBusy = ref(false);
const consentToken = ref('');
const targetSelections = reactive<Record<string, string>>({});

function csrfToken(): string | null {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? null;
}

async function createInvitation(): Promise<void> {
  invitationBusy.value = true;
  invitationError.value = null;
  invitationToken.value = null;

  try {
    const response = await fetch('/alliance/kingdom-sharing/invitations', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken() ?? '',
      },
      body: JSON.stringify({}),
    });

    if (response.redirected) {
      window.location.assign(response.url);
      return;
    }

    if (response.status === 423) {
      window.location.assign(props.passwordConfirmUrl);
      return;
    }

    if (!response.ok) {
      invitationError.value = t('kingdomP7C.invitationCreateFailed');
      return;
    }

    const body = (await response.json()) as { shareId: string; token: string };
    invitationToken.value = body.token;
    router.reload({ only: ['sharing'] });
  } catch {
    invitationError.value = t('kingdomP7C.invitationTryAgain');
  } finally {
    invitationBusy.value = false;
  }
}

async function copyInvitationToken(): Promise<void> {
  if (!invitationToken.value) return;
  await window.navigator.clipboard.writeText(invitationToken.value);
}

function acceptInvitation(): void {
  if (!consentToken.value) return;
  router.post(
    '/alliance/kingdom-sharing/invitations/accept',
    { token: consentToken.value },
    {
      preserveScroll: true,
      onSuccess: () => {
        consentToken.value = '';
      },
    },
  );
}

function declineInvitation(): void {
  if (!consentToken.value) return;
  router.post(
    '/alliance/kingdom-sharing/invitations/decline',
    { token: consentToken.value },
    {
      preserveScroll: true,
      onSuccess: () => {
        consentToken.value = '';
      },
    },
  );
}

function revokeShare(shareId: string): void {
  router.post(`/alliance/kingdom-sharing/${shareId}/revoke`, {}, { preserveScroll: true });
}

function leaveShare(shareId: string): void {
  router.post(`/alliance/kingdom-sharing/${shareId}/leave`, {}, { preserveScroll: true });
}

function addTarget(shareId: string): void {
  const trackingId = targetSelections[shareId];
  if (!trackingId) return;
  router.post(
    `/alliance/kingdom-sharing/${shareId}/targets/${trackingId}`,
    {},
    {
      preserveScroll: true,
      onSuccess: () => {
        targetSelections[shareId] = '';
      },
    },
  );
}

function removeTarget(shareId: string, targetId: string): void {
  router.post(
    `/alliance/kingdom-sharing/${shareId}/targets/${targetId}/remove`,
    {},
    { preserveScroll: true },
  );
}

function formatDate(value: string | null): string {
  return value ? localeFormatDate(value, { dateStyle: 'medium', timeStyle: 'short' }) : '—';
}
</script>

<template>
  <Head :title="`${t('kingdomP7C.manageTitle')} · ${alliance.name}`" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="text-sm font-semibold tracking-[0.2em] text-[var(--ks-blue-strong)] uppercase">
          {{ t('kingdomP7C.manageEyebrow') }}
        </p>
        <h1 class="mt-2 text-3xl font-bold">{{ t('kingdomP7C.manageTitle') }}</h1>
        <p class="mt-2 max-w-3xl text-sm text-[var(--ks-text-muted)]">
          {{
            t('kingdomP7C.manageSubtitle', {
              alliance: alliance.name,
              kingdom: alliance.kingdom ?? t('kingdomP7C.notConfigured'),
            })
          }}
        </p>
      </div>
      <nav :aria-label="t('kingdomP7C.sharingNavigation')" class="flex flex-wrap gap-3">
        <Link
          class="rounded-lg border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold text-[var(--ks-blue-strong)]"
          href="/alliance/kingdom-sharing"
        >
          {{ t('kingdomP7C.receivedFacts') }}
        </Link>
        <Link
          class="rounded-lg border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold text-[var(--ks-text)]"
          href="/dashboard"
        >
          {{ t('kingdomP7C.dashboard') }}
        </Link>
      </nav>
    </header>

    <section aria-labelledby="invite-heading" class="ks-surface mt-6 p-5 sm:p-6">
      <h2 id="invite-heading" class="text-xl font-semibold">
        {{ t('kingdomP7C.inviteAlliance') }}
      </h2>
      <p class="mt-1 max-w-3xl text-sm text-[var(--ks-text-muted)]">
        {{ t('kingdomP7C.inviteHelp') }}
      </p>

      <button
        class="mt-5 rounded-lg bg-[var(--ks-gold)] px-4 py-2 text-sm font-semibold text-[var(--ks-ink)] disabled:cursor-not-allowed disabled:opacity-60"
        type="button"
        :disabled="invitationBusy || alliance.kingdom === null"
        @click="createInvitation"
      >
        {{ invitationBusy ? t('kingdomP7C.creating') : t('kingdomP7C.createInvitation') }}
      </button>

      <p v-if="alliance.kingdom === null" class="mt-3 text-sm text-amber-200">
        {{ t('kingdomP7C.kingdomRequired') }}
      </p>
      <p v-if="invitationError" role="alert" class="mt-3 text-sm text-red-200">
        {{ invitationError }}
      </p>

      <div
        v-if="invitationToken"
        class="mt-5 rounded-xl border border-amber-400/30 bg-amber-500/10 p-4"
        role="status"
      >
        <label class="block text-sm font-semibold text-amber-200" for="issued-sharing-token">
          {{ t('kingdomP7C.issuedInvitation') }}
        </label>
        <input
          id="issued-sharing-token"
          class="mt-2 w-full rounded-lg border border-amber-400/30 bg-[var(--ks-bg)] px-3 py-2 font-mono text-sm text-amber-100"
          readonly
          :value="invitationToken"
        />
        <div class="mt-3 flex flex-wrap items-center gap-3">
          <button
            class="rounded-lg border border-amber-400/30 px-3 py-2 text-sm font-semibold text-amber-200"
            type="button"
            @click="copyInvitationToken"
          >
            {{ t('kingdomP7C.copyInvitation') }}
          </button>
          <button
            class="rounded-lg border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold text-[var(--ks-text-secondary)]"
            type="button"
            @click="invitationToken = null"
          >
            {{ t('kingdomP7C.clearInvitation') }}
          </button>
        </div>
      </div>
    </section>

    <section aria-labelledby="redeem-heading" class="ks-surface mt-6 p-5 sm:p-6">
      <h2 id="redeem-heading" class="text-xl font-semibold">
        {{ t('kingdomP7C.respondInvitation') }}
      </h2>
      <p class="mt-1 text-sm text-[var(--ks-text-muted)]">
        {{ t('kingdomP7C.respondHelp') }}
      </p>
      <label
        class="mt-5 block text-sm font-semibold text-[var(--ks-text)]"
        for="sharing-consent-token"
      >
        {{ t('kingdomP7C.invitationValue') }}
      </label>
      <input
        id="sharing-consent-token"
        v-model.trim="consentToken"
        autocomplete="off"
        class="mt-2 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2 font-mono text-sm text-[var(--ks-text)]"
        maxlength="64"
        spellcheck="false"
      />
      <div class="mt-3 flex flex-wrap gap-3">
        <button
          class="rounded-lg bg-[var(--ks-gold)] px-4 py-2 text-sm font-semibold text-[var(--ks-ink)] disabled:opacity-60"
          type="button"
          :disabled="consentToken.length !== 64"
          @click="acceptInvitation"
        >
          {{ t('kingdomP7C.acceptInvitation') }}
        </button>
        <button
          class="rounded-lg border border-[var(--ks-border)] px-4 py-2 text-sm font-semibold text-[var(--ks-text)] disabled:opacity-60"
          type="button"
          :disabled="consentToken.length !== 64"
          @click="declineInvitation"
        >
          {{ t('kingdomP7C.declineInvitation') }}
        </button>
      </div>
    </section>

    <section aria-labelledby="outbound-heading" class="ks-surface mt-6 p-5 sm:p-6">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 id="outbound-heading" class="text-xl font-semibold">
            {{ t('kingdomP7C.outboundSharing') }}
          </h2>
          <p class="mt-1 text-sm text-[var(--ks-text-muted)]">
            {{ t('kingdomP7C.outboundHelp') }}
          </p>
        </div>
        <p class="text-sm text-[var(--ks-text-muted)]">
          {{ formatNumber(sharing.outbound.length) }} {{ t('kingdomP7C.agreements') }}
        </p>
      </div>

      <div v-if="sharing.outbound.length" class="mt-6 space-y-5">
        <article
          v-for="share in sharing.outbound"
          :key="share.id"
          class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-parchment)]/[0.02] p-5"
        >
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h3 class="font-semibold text-[var(--ks-text)]">
                {{ share.recipientAlliance?.name ?? t('kingdomP7C.pendingRecipient') }}
              </h3>
              <p class="mt-1 text-sm text-[var(--ks-text-muted)]">
                {{ t('kingdomP7C.state') }} {{ share.state }} ·
                {{
                  t('kingdomP7C.invitationExpires', { date: formatDate(share.invitationExpiresAt) })
                }}
              </p>
            </div>
            <button
              v-if="share.state === 'pending' || share.state === 'active'"
              class="rounded-lg border border-red-400/30 px-3 py-2 text-sm font-semibold text-red-200"
              type="button"
              @click="revokeShare(share.id)"
            >
              {{ t('kingdomP7C.revoke') }}
            </button>
          </div>

          <div v-if="share.state === 'active'" class="mt-5 border-t border-[var(--ks-border)] pt-5">
            <h4 class="text-sm font-semibold text-[var(--ks-text)]">
              {{ t('kingdomP7C.explicitTargets') }}
            </h4>
            <ul
              v-if="share.targets.length"
              class="mt-3 space-y-2"
              :aria-label="t('kingdomP7C.sharedTargetsAria')"
            >
              <li
                v-for="target in share.targets"
                :key="target.id"
                class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-[var(--ks-border)] px-3 py-3"
              >
                <div>
                  <p class="font-medium text-[var(--ks-text)]">{{ target.name }}</p>
                  <p class="text-xs text-[var(--ks-text-muted)]">
                    {{ target.tag ?? t('kingdomP7C.noTagShort') }} · {{ target.state }}
                  </p>
                </div>
                <button
                  v-if="target.state === 'active'"
                  class="rounded-lg border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold text-[var(--ks-text-secondary)]"
                  type="button"
                  @click="removeTarget(share.id, target.id)"
                >
                  {{ t('kingdomP7C.remove') }}
                </button>
              </li>
            </ul>
            <p v-else class="mt-3 text-sm text-[var(--ks-text-muted)]">
              {{ t('kingdomP7C.noTargets') }}
            </p>

            <div class="mt-4 flex flex-wrap items-end gap-3">
              <div class="min-w-64 flex-1">
                <label
                  class="block text-sm font-semibold text-[var(--ks-text)]"
                  :for="`target-${share.id}`"
                >
                  {{ t('kingdomP7C.grantTarget') }}
                </label>
                <select
                  :id="`target-${share.id}`"
                  v-model="targetSelections[share.id]"
                  class="mt-2 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2 text-sm text-[var(--ks-text)]"
                >
                  <option value="">{{ t('kingdomP7C.chooseTarget') }}</option>
                  <option
                    v-for="target in sharing.trackableTargets"
                    :key="target.id"
                    :value="target.id"
                  >
                    {{ target.name }}{{ target.tag ? ` (${target.tag})` : '' }}
                  </option>
                </select>
              </div>
              <button
                class="rounded-lg bg-[var(--ks-gold)] px-4 py-2 text-sm font-semibold text-[var(--ks-ink)] disabled:opacity-60"
                type="button"
                :disabled="!targetSelections[share.id]"
                @click="addTarget(share.id)"
              >
                {{ t('kingdomP7C.shareTarget') }}
              </button>
            </div>
          </div>
        </article>
      </div>
      <p v-else class="mt-6 text-sm text-[var(--ks-text-muted)]">
        {{ t('kingdomP7C.noOutbound') }}
      </p>
    </section>

    <section aria-labelledby="inbound-heading" class="ks-surface mt-6 p-5 sm:p-6">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 id="inbound-heading" class="text-xl font-semibold">
            {{ t('kingdomP7C.inboundSharing') }}
          </h2>
          <p class="mt-1 text-sm text-[var(--ks-text-muted)]">
            {{ t('kingdomP7C.inboundHelp') }}
          </p>
        </div>
        <p class="text-sm text-[var(--ks-text-muted)]">
          {{ formatNumber(sharing.inbound.length) }} {{ t('kingdomP7C.agreements') }}
        </p>
      </div>

      <div v-if="sharing.inbound.length" class="mt-6 overflow-x-auto">
        <table class="min-w-full divide-y divide-[var(--ks-border)] text-left text-sm">
          <caption class="sr-only">
            {{
              t('kingdomP7C.inboundCaption')
            }}
          </caption>
          <thead class="text-xs tracking-wide text-[var(--ks-text-muted)] uppercase">
            <tr>
              <th class="px-3 py-3 font-semibold">{{ t('kingdomP7C.sourceAllianceManage') }}</th>
              <th class="px-3 py-3 font-semibold">{{ t('kingdomP7C.state') }}</th>
              <th class="px-3 py-3 font-semibold">{{ t('kingdomP7C.acceptedAt') }}</th>
              <th class="px-3 py-3 font-semibold">{{ t('kingdomP7C.action') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[var(--ks-border)]">
            <tr v-for="share in sharing.inbound" :key="share.id">
              <td class="px-3 py-4 font-medium text-[var(--ks-text)]">
                {{ share.sourceAlliance.name }}
              </td>
              <td class="px-3 py-4 text-[var(--ks-text-secondary)]">{{ share.state }}</td>
              <td class="px-3 py-4 text-[var(--ks-text-secondary)]">
                {{ formatDate(share.acceptedAt) }}
              </td>
              <td class="px-3 py-4">
                <button
                  v-if="share.state === 'active'"
                  class="rounded-lg border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold text-[var(--ks-text-secondary)]"
                  type="button"
                  @click="leaveShare(share.id)"
                >
                  {{ t('kingdomP7C.leaveSharing') }}
                </button>
                <span v-else class="text-[var(--ks-text-muted)]">{{
                  t('kingdomP7C.terminal')
                }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="mt-6 text-sm text-[var(--ks-text-muted)]">{{ t('kingdomP7C.noInbound') }}</p>
    </section>
  </AppLayout>
</template>
