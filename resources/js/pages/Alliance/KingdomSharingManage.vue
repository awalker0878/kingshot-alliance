<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

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
  alliance: { id: string; name: string; kingdom: string | null };
  passwordConfirmUrl: string;
  sharing: {
    outbound: OutboundShare[];
    inbound: InboundShare[];
    trackableTargets: TrackableTarget[];
  };
}>();

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
      invitationError.value = 'The invitation could not be created. Confirm your password and try again.';
      return;
    }

    const body = (await response.json()) as { shareId: string; token: string };
    invitationToken.value = body.token;
    router.reload({ only: ['sharing'], preserveScroll: true, preserveState: true });
  } catch {
    invitationError.value = 'The invitation could not be created. Try again.';
  } finally {
    invitationBusy.value = false;
  }
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
  if (!value) return '—';
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value));
}
</script>

<template>
  <Head title="Manage shared Kingdom intelligence" />

  <main class="mx-auto min-h-screen max-w-6xl px-6 py-12 lg:px-8">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="text-sm font-semibold tracking-[0.2em] text-cyan-300 uppercase">Shared intelligence</p>
        <h1 class="mt-2 text-3xl font-bold">Manage sharing</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-400">
          {{ alliance.name }} · current Kingdom {{ alliance.kingdom ?? 'not configured' }}. Sharing is
          directional, same-Kingdom, and explicit per tracked game alliance.
        </p>
      </div>
      <nav aria-label="Sharing navigation" class="flex flex-wrap gap-3">
        <Link
          class="rounded-lg border border-cyan-800 px-4 py-2 text-sm font-semibold text-cyan-300"
          href="/alliance/kingdom-sharing"
        >
          Shared facts
        </Link>
        <Link
          class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200"
          href="/dashboard"
        >
          Dashboard
        </Link>
      </nav>
    </header>

    <section aria-labelledby="invite-heading" class="mt-10 rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <h2 id="invite-heading" class="text-xl font-semibold">Invite another Alliance</h2>
      <p class="mt-1 max-w-3xl text-sm text-slate-400">
        The invitation secret is shown only after creation. Send it to an authorized manager of the
        intended Alliance using a channel you trust. The token alone does not expose intelligence.
      </p>

      <button
        class="mt-5 rounded-lg bg-cyan-300 px-4 py-2 text-sm font-semibold text-slate-950 disabled:cursor-not-allowed disabled:opacity-60"
        type="button"
        :disabled="invitationBusy || alliance.kingdom === null"
        @click="createInvitation"
      >
        {{ invitationBusy ? 'Creating…' : 'Create one-time invitation' }}
      </button>

      <p v-if="alliance.kingdom === null" class="mt-3 text-sm text-amber-300">
        Configure the Alliance Kingdom before creating a sharing invitation.
      </p>
      <p v-if="invitationError" role="alert" class="mt-3 text-sm text-rose-300">{{ invitationError }}</p>

      <div
        v-if="invitationToken"
        class="mt-5 rounded-xl border border-amber-700 bg-amber-950/40 p-4"
        role="status"
      >
        <label class="block text-sm font-semibold text-amber-200" for="issued-sharing-token">
          One-time invitation token
        </label>
        <input
          id="issued-sharing-token"
          class="mt-2 w-full rounded-lg border border-amber-800 bg-slate-950 px-3 py-2 font-mono text-sm text-amber-100"
          readonly
          :value="invitationToken"
        />
        <div class="mt-3 flex flex-wrap items-center gap-3">
          <button
            class="rounded-lg border border-amber-700 px-3 py-2 text-sm font-semibold text-amber-200"
            type="button"
            @click="navigator.clipboard.writeText(invitationToken)"
          >
            Copy token
          </button>
          <button
            class="rounded-lg border border-slate-700 px-3 py-2 text-sm font-semibold text-slate-300"
            type="button"
            @click="invitationToken = null"
          >
            Clear from this page
          </button>
        </div>
      </div>
    </section>

    <section aria-labelledby="redeem-heading" class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <h2 id="redeem-heading" class="text-xl font-semibold">Respond to an invitation</h2>
      <p class="mt-1 text-sm text-slate-400">
        Enter the one-time token supplied by another Alliance manager. Acceptance succeeds only when
        both Alliances are currently in the captured Kingdom.
      </p>
      <label class="mt-5 block text-sm font-semibold text-slate-200" for="sharing-consent-token">
        Invitation token
      </label>
      <input
        id="sharing-consent-token"
        v-model.trim="consentToken"
        autocomplete="off"
        class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 font-mono text-sm text-slate-100"
        maxlength="64"
        spellcheck="false"
      />
      <div class="mt-3 flex flex-wrap gap-3">
        <button
          class="rounded-lg bg-cyan-300 px-4 py-2 text-sm font-semibold text-slate-950 disabled:opacity-60"
          type="button"
          :disabled="consentToken.length !== 64"
          @click="acceptInvitation"
        >
          Accept invitation
        </button>
        <button
          class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 disabled:opacity-60"
          type="button"
          :disabled="consentToken.length !== 64"
          @click="declineInvitation"
        >
          Decline invitation
        </button>
      </div>
    </section>

    <section aria-labelledby="outbound-heading" class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 id="outbound-heading" class="text-xl font-semibold">Outbound sharing</h2>
          <p class="mt-1 text-sm text-slate-400">
            An active agreement still shares nothing until you explicitly grant a tracked game alliance.
          </p>
        </div>
        <p class="text-sm text-slate-400">{{ sharing.outbound.length }} agreement(s)</p>
      </div>

      <div v-if="sharing.outbound.length" class="mt-6 space-y-5">
        <article
          v-for="share in sharing.outbound"
          :key="share.id"
          class="rounded-xl border border-slate-800 bg-slate-950/60 p-5"
        >
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h3 class="font-semibold text-slate-100">
                {{ share.recipientAlliance?.name ?? 'Pending recipient' }}
              </h3>
              <p class="mt-1 text-sm text-slate-400">
                State {{ share.state }} · invitation expires {{ formatDate(share.invitationExpiresAt) }}
              </p>
            </div>
            <button
              v-if="share.state === 'pending' || share.state === 'active'"
              class="rounded-lg border border-rose-800 px-3 py-2 text-sm font-semibold text-rose-300"
              type="button"
              @click="revokeShare(share.id)"
            >
              Revoke
            </button>
          </div>

          <div v-if="share.state === 'active'" class="mt-5 border-t border-slate-800 pt-5">
            <h4 class="text-sm font-semibold text-slate-200">Explicit targets</h4>
            <ul v-if="share.targets.length" class="mt-3 space-y-2" aria-label="Shared targets">
              <li
                v-for="target in share.targets"
                :key="target.id"
                class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-800 px-3 py-3"
              >
                <div>
                  <p class="font-medium text-slate-200">{{ target.name }}</p>
                  <p class="text-xs text-slate-400">{{ target.tag ?? 'No tag' }} · {{ target.state }}</p>
                </div>
                <button
                  v-if="target.state === 'active'"
                  class="rounded-lg border border-slate-700 px-3 py-2 text-sm font-semibold text-slate-300"
                  type="button"
                  @click="removeTarget(share.id, target.id)"
                >
                  Remove
                </button>
              </li>
            </ul>
            <p v-else class="mt-3 text-sm text-slate-400">No targets are shared by this agreement.</p>

            <div class="mt-4 flex flex-wrap items-end gap-3">
              <div class="min-w-64 flex-1">
                <label class="block text-sm font-semibold text-slate-200" :for="`target-${share.id}`">
                  Grant tracked game alliance
                </label>
                <select
                  :id="`target-${share.id}`"
                  v-model="targetSelections[share.id]"
                  class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100"
                >
                  <option value="">Choose a tracked game alliance</option>
                  <option v-for="target in sharing.trackableTargets" :key="target.id" :value="target.id">
                    {{ target.name }}{{ target.tag ? ` (${target.tag})` : '' }}
                  </option>
                </select>
              </div>
              <button
                class="rounded-lg bg-cyan-300 px-4 py-2 text-sm font-semibold text-slate-950 disabled:opacity-60"
                type="button"
                :disabled="!targetSelections[share.id]"
                @click="addTarget(share.id)"
              >
                Share target
              </button>
            </div>
          </div>
        </article>
      </div>
      <p v-else class="mt-6 text-sm text-slate-400">No outbound sharing agreements yet.</p>
    </section>

    <section aria-labelledby="inbound-heading" class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 id="inbound-heading" class="text-xl font-semibold">Inbound sharing</h2>
          <p class="mt-1 text-sm text-slate-400">Active sources may share only targets they explicitly grant.</p>
        </div>
        <p class="text-sm text-slate-400">{{ sharing.inbound.length }} agreement(s)</p>
      </div>

      <div v-if="sharing.inbound.length" class="mt-6 overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-800 text-left text-sm">
          <caption class="sr-only">Inbound shared-intelligence agreements</caption>
          <thead class="text-xs tracking-wide text-slate-400 uppercase">
            <tr>
              <th class="px-3 py-3 font-semibold">Source Alliance</th>
              <th class="px-3 py-3 font-semibold">State</th>
              <th class="px-3 py-3 font-semibold">Accepted</th>
              <th class="px-3 py-3 font-semibold">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-for="share in sharing.inbound" :key="share.id">
              <td class="px-3 py-4 font-medium text-slate-200">{{ share.sourceAlliance.name }}</td>
              <td class="px-3 py-4 text-slate-300">{{ share.state }}</td>
              <td class="px-3 py-4 text-slate-300">{{ formatDate(share.acceptedAt) }}</td>
              <td class="px-3 py-4">
                <button
                  v-if="share.state === 'active'"
                  class="rounded-lg border border-slate-700 px-3 py-2 text-sm font-semibold text-slate-300"
                  type="button"
                  @click="leaveShare(share.id)"
                >
                  Leave sharing
                </button>
                <span v-else class="text-slate-500">Terminal</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="mt-6 text-sm text-slate-400">No inbound sharing agreements yet.</p>
    </section>
  </main>
</template>
