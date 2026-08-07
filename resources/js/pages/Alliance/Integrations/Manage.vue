<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{
  alliance: { id: string; name: string };
  settings: { apiAccessEnabled: boolean; webhooksEnabled: boolean };
  limits: {
    members: number;
    storageBytes: number;
    apiCredentials: number;
    webhookSubscriptions: number;
  };
  allowedScopes: string[];
  credentials: Array<{
    id: string;
    name: string;
    prefix: string;
    scopes: string[];
    expiresAt: string | null;
    lastUsedAt: string | null;
    revokedAt: string | null;
  }>;
  webhooks: Array<{
    id: string;
    name: string;
    url: string;
    events: string[];
    active: boolean;
    revokedAt: string | null;
  }>;
  recentDeliveries: Array<{
    id: string;
    event: string;
    status: string;
    attempts: number;
    responseCode: number | null;
    lastError: string | null;
    lastAttemptAt: string | null;
    deliveredAt: string | null;
  }>;
  issuedCredential: { id: string; name: string; token: string } | null;
  issuedWebhookSecret: { id: string; name: string; secret: string } | null;
  status: string | null;
}>();

const credentialForm = useForm({
  name: '',
  scopes: [] as string[],
  expires_at: '',
});
const webhookForm = useForm({
  name: '',
  url: '',
  events: ['alliance.created'] as string[],
});
const webhookEventsText = ref('alliance.created');

function createCredential(): void {
  credentialForm.post('/alliance/integrations/api-credentials', {
    preserveScroll: true,
    onSuccess: () => credentialForm.reset(),
  });
}

function createWebhook(): void {
  webhookForm.events = webhookEventsText.value
    .split(/[,\n]/)
    .map((event) => event.trim())
    .filter(Boolean);
  webhookForm.post('/alliance/integrations/webhooks', {
    preserveScroll: true,
    onSuccess: () => {
      webhookForm.reset();
      webhookEventsText.value = 'alliance.created';
    },
  });
}
</script>

<template>
  <Head title="Integrations" />
  <main class="mx-auto max-w-6xl space-y-8 p-6">
    <header class="space-y-2">
      <Link href="/alliance" class="text-sm font-semibold">← {{ alliance.name }}</Link>
      <h1 class="text-3xl font-bold">API credentials and webhooks</h1>
      <p class="max-w-3xl text-sm text-slate-600">
        Credentials are scoped and shown only once. Webhooks are signed with HMAC-SHA256 and retried
        on the isolated integrations queue.
      </p>
      <p v-if="status" role="status" class="rounded border p-3 text-sm">{{ status }}</p>
    </header>

    <section
      v-if="issuedCredential"
      aria-labelledby="credential-secret-heading"
      class="rounded border border-amber-400 p-5"
    >
      <h2 id="credential-secret-heading" class="text-xl font-semibold">
        Save this API credential now
      </h2>
      <p class="mt-2 text-sm">The token cannot be retrieved again after this response.</p>
      <code class="mt-3 block overflow-x-auto rounded bg-slate-950 p-3 text-sm text-white">{{
        issuedCredential.token
      }}</code>
    </section>

    <section
      v-if="issuedWebhookSecret"
      aria-labelledby="webhook-secret-heading"
      class="rounded border border-amber-400 p-5"
    >
      <h2 id="webhook-secret-heading" class="text-xl font-semibold">
        Save this webhook signing secret now
      </h2>
      <p class="mt-2 text-sm">Use it to verify the <code>X-Kingshot-Signature</code> header.</p>
      <code class="mt-3 block overflow-x-auto rounded bg-slate-950 p-3 text-sm text-white">{{
        issuedWebhookSecret.secret
      }}</code>
    </section>

    <section aria-labelledby="api-heading" class="space-y-5 rounded border p-5">
      <div>
        <h2 id="api-heading" class="text-xl font-semibold">API credentials</h2>
        <p class="text-sm text-slate-600">
          {{
            settings.apiAccessEnabled
              ? 'API access is enabled.'
              : 'API access is disabled by the platform.'
          }}
          Limit: {{ limits.apiCredentials }} active credentials.
        </p>
      </div>
      <form
        v-if="settings.apiAccessEnabled"
        class="grid gap-4 md:grid-cols-2"
        @submit.prevent="createCredential"
      >
        <label class="grid gap-1 text-sm"
          >Name<input
            v-model="credentialForm.name"
            required
            maxlength="100"
            class="rounded border px-3 py-2"
        /></label>
        <label class="grid gap-1 text-sm"
          >Expires at (optional)<input
            v-model="credentialForm.expires_at"
            type="datetime-local"
            class="rounded border px-3 py-2"
        /></label>
        <fieldset class="md:col-span-2">
          <legend class="text-sm font-medium">Scopes</legend>
          <div class="mt-2 flex flex-wrap gap-4">
            <label
              v-for="scope in allowedScopes"
              :key="scope"
              class="flex items-center gap-2 text-sm"
            >
              <input v-model="credentialForm.scopes" type="checkbox" :value="scope" /> {{ scope }}
            </label>
          </div>
        </fieldset>
        <button
          type="submit"
          class="w-fit rounded bg-slate-900 px-4 py-2 text-white"
          :disabled="credentialForm.processing"
        >
          Create credential
        </button>
      </form>
      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead>
            <tr>
              <th class="p-2">Name</th>
              <th class="p-2">Prefix</th>
              <th class="p-2">Scopes</th>
              <th class="p-2">Last used</th>
              <th class="p-2">State</th>
              <th class="p-2">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="credential in credentials" :key="credential.id" class="border-t">
              <td class="p-2">{{ credential.name }}</td>
              <td class="p-2 font-mono">{{ credential.prefix }}</td>
              <td class="p-2">{{ credential.scopes.join(', ') }}</td>
              <td class="p-2">{{ credential.lastUsedAt || 'Never' }}</td>
              <td class="p-2">{{ credential.revokedAt ? 'Revoked' : 'Active' }}</td>
              <td class="p-2">
                <button
                  v-if="!credential.revokedAt"
                  type="button"
                  class="rounded border px-2 py-1"
                  @click="router.delete(`/alliance/integrations/api-credentials/${credential.id}`)"
                >
                  Revoke
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section aria-labelledby="webhook-heading" class="space-y-5 rounded border p-5">
      <div>
        <h2 id="webhook-heading" class="text-xl font-semibold">Webhook subscriptions</h2>
        <p class="text-sm text-slate-600">
          {{
            settings.webhooksEnabled
              ? 'Webhooks are enabled.'
              : 'Webhooks are disabled by the platform.'
          }}
          Limit: {{ limits.webhookSubscriptions }} active subscriptions. HTTPS endpoints only.
        </p>
      </div>
      <form
        v-if="settings.webhooksEnabled"
        class="grid gap-4 md:grid-cols-2"
        @submit.prevent="createWebhook"
      >
        <label class="grid gap-1 text-sm"
          >Name<input
            v-model="webhookForm.name"
            required
            maxlength="100"
            class="rounded border px-3 py-2"
        /></label>
        <label class="grid gap-1 text-sm"
          >HTTPS endpoint<input
            v-model="webhookForm.url"
            type="url"
            required
            maxlength="2048"
            class="rounded border px-3 py-2"
        /></label>
        <label class="grid gap-1 text-sm md:col-span-2"
          >Events (comma or newline separated)<textarea
            v-model="webhookEventsText"
            required
            rows="3"
            class="rounded border px-3 py-2"
          />
        </label>
        <button
          type="submit"
          class="w-fit rounded bg-slate-900 px-4 py-2 text-white"
          :disabled="webhookForm.processing"
        >
          Create webhook
        </button>
      </form>
      <ul class="space-y-2">
        <li
          v-for="webhook in webhooks"
          :key="webhook.id"
          class="flex flex-wrap items-start justify-between gap-3 rounded border p-3 text-sm"
        >
          <div>
            <strong>{{ webhook.name }}</strong
            ><br /><span class="break-all text-slate-600">{{ webhook.url }}</span
            ><br /><span>{{ webhook.events.join(', ') }}</span>
          </div>
          <button
            v-if="webhook.active && !webhook.revokedAt"
            type="button"
            class="rounded border px-2 py-1"
            @click="router.delete(`/alliance/integrations/webhooks/${webhook.id}`)"
          >
            Revoke
          </button>
        </li>
      </ul>
    </section>

    <section aria-labelledby="delivery-heading" class="space-y-3 rounded border p-5">
      <h2 id="delivery-heading" class="text-xl font-semibold">Recent delivery log</h2>
      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead>
            <tr>
              <th class="p-2">Event</th>
              <th class="p-2">Status</th>
              <th class="p-2">Attempts</th>
              <th class="p-2">HTTP</th>
              <th class="p-2">Last error</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="delivery in recentDeliveries" :key="delivery.id" class="border-t">
              <td class="p-2">{{ delivery.event }}</td>
              <td class="p-2">{{ delivery.status }}</td>
              <td class="p-2">{{ delivery.attempts }}</td>
              <td class="p-2">{{ delivery.responseCode ?? '—' }}</td>
              <td class="max-w-xl p-2">{{ delivery.lastError || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</template>
