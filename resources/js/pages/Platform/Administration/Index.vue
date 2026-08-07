<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';

type AllianceRow = {
  id: string;
  name: string;
  slug: string;
  status: string;
  timezone: string;
  activeMembers: number;
  storageBytes: number;
  apiCredentials: number;
  webhooks: number;
  pendingOutbox: number;
  plan: string;
  retentionDays: number;
  queuePartition: string;
  apiAccessEnabled: boolean;
  webhooksEnabled: boolean;
  retentionUntil: string | null;
  lifecycleReason: string | null;
};

const props = defineProps<{
  platform: {
    metrics: Record<string, number>;
    alliances: AllianceRow[];
    administrators: Array<{
      id: string;
      userId: number;
      name: string | null;
      email: string | null;
      mfaEnabled: boolean;
      grantedAt: string;
      revokedAt: string | null;
    }>;
    plans: Array<{ code: string; name: string; entitlements: Record<string, number> }>;
    legalHolds: Array<{
      id: string;
      subjectType: string;
      subjectId: string;
      reason: string;
      placedAt: string;
    }>;
  };
  selectedAlliance: null | {
    id: string;
    name: string;
    features: Array<{
      key: string;
      enabled: boolean;
      configuration: Record<string, unknown> | null;
    }>;
    members: Array<{ id: string; name: string | null; email: string | null; status: string }>;
  };
  currentUserId: number;
  status: string | null;
}>();

const adminForm = useForm({ email: '' });
const provisionForm = useForm({
  owner_email: '',
  name: '',
  slug: '',
  kingdom: '',
  language: 'en',
  timezone: 'UTC',
});
const holdForm = useForm({ subject_type: 'alliance', subject_id: '', reason: '' });
const lifecycleForm = useForm({ reason: '' });
const ownershipForm = useForm({ membership_id: '' });
const featureForm = useForm({
  feature_key: '',
  enabled: true,
});
const selected = props.selectedAlliance
  ? (props.platform.alliances.find((alliance) => alliance.id === props.selectedAlliance?.id) ??
    null)
  : null;
const planForm = useForm({ plan_code: selected?.plan ?? 'standard' });
const settingsForm = useForm({
  retention_days: selected?.retentionDays ?? 30,
  queue_partition: selected?.queuePartition ?? 'standard',
  api_access_enabled: selected?.apiAccessEnabled ?? true,
  webhooks_enabled: selected?.webhooksEnabled ?? true,
});

function lifecycle(operation: 'suspend' | 'close' | 'delete' | 'restore'): void {
  if (!props.selectedAlliance || lifecycleForm.reason.trim() === '') return;
  lifecycleForm.post(`/platform/alliances/${props.selectedAlliance.id}/lifecycle/${operation}`, {
    preserveScroll: true,
  });
}
</script>

<template>
  <Head title="Platform administration" />
  <main class="mx-auto max-w-7xl space-y-8 p-6">
    <header class="space-y-2">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="text-sm font-semibold tracking-wide text-slate-500 uppercase">
            Platform operations
          </p>
          <h1 class="text-3xl font-bold">Platform administration</h1>
        </div>
        <Link href="/dashboard" class="rounded border px-3 py-2 text-sm font-medium"
          >Back to dashboard</Link
        >
      </div>
      <p class="max-w-3xl text-sm text-slate-600">
        Platform administration is separate from alliance roles. Access requires a verified account,
        MFA, an active platform-administrator grant, and recent password confirmation.
      </p>
      <p v-if="status" role="status" class="rounded bg-emerald-50 p-3 text-sm text-emerald-900">
        {{ status }}
      </p>
    </header>

    <section aria-labelledby="capacity-heading" class="space-y-3">
      <h2 id="capacity-heading" class="text-xl font-semibold">Capacity and operations</h2>
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div v-for="(value, key) in platform.metrics" :key="key" class="rounded border p-4">
          <p class="text-xs font-semibold tracking-wide break-words text-slate-500 uppercase">
            {{ key }}
          </p>
          <p class="mt-1 text-2xl font-bold">{{ value }}</p>
        </div>
      </div>
      <p class="text-sm text-slate-600">
        Queue partitions are isolated as default, notifications, integrations, and maintenance.
        Detailed worker telemetry remains available through Horizon for authenticated platform
        administrators.
      </p>
    </section>

    <section aria-labelledby="admins-heading" class="space-y-4 rounded border p-5">
      <h2 id="admins-heading" class="text-xl font-semibold">Platform administrators</h2>
      <form
        class="flex flex-wrap items-end gap-3"
        @submit.prevent="adminForm.post('/platform/administrators')"
      >
        <label class="grid gap-1 text-sm">
          User email
          <input v-model="adminForm.email" type="email" required class="rounded border px-3 py-2" />
        </label>
        <button
          type="submit"
          class="rounded bg-slate-900 px-4 py-2 text-white"
          :disabled="adminForm.processing"
        >
          Grant administrator
        </button>
      </form>
      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead>
            <tr>
              <th class="p-2">Account</th>
              <th class="p-2">MFA</th>
              <th class="p-2">Granted</th>
              <th class="p-2">State</th>
              <th class="p-2">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="admin in platform.administrators" :key="admin.id" class="border-t">
              <td class="p-2">
                {{ admin.name }}<br /><span class="text-slate-500">{{ admin.email }}</span>
              </td>
              <td class="p-2">{{ admin.mfaEnabled ? 'Enabled' : 'Required' }}</td>
              <td class="p-2">{{ admin.grantedAt }}</td>
              <td class="p-2">{{ admin.revokedAt ? 'Revoked' : 'Active' }}</td>
              <td class="p-2">
                <button
                  v-if="!admin.revokedAt && admin.userId !== currentUserId"
                  type="button"
                  class="rounded border px-2 py-1"
                  @click="router.delete(`/platform/administrators/${admin.id}`)"
                >
                  Revoke
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section aria-labelledby="provision-heading" class="space-y-4 rounded border p-5">
      <h2 id="provision-heading" class="text-xl font-semibold">Provision alliance</h2>
      <form
        class="grid gap-3 md:grid-cols-3"
        @submit.prevent="provisionForm.post('/platform/alliances')"
      >
        <label class="grid gap-1 text-sm"
          >Owner email<input
            v-model="provisionForm.owner_email"
            type="email"
            required
            class="rounded border px-3 py-2"
        /></label>
        <label class="grid gap-1 text-sm"
          >Alliance name<input
            v-model="provisionForm.name"
            required
            maxlength="120"
            class="rounded border px-3 py-2"
        /></label>
        <label class="grid gap-1 text-sm"
          >Slug<input
            v-model="provisionForm.slug"
            required
            maxlength="120"
            class="rounded border px-3 py-2"
        /></label>
        <label class="grid gap-1 text-sm"
          >Kingdom<input
            v-model="provisionForm.kingdom"
            maxlength="64"
            class="rounded border px-3 py-2"
        /></label>
        <label class="grid gap-1 text-sm"
          >Language<input
            v-model="provisionForm.language"
            required
            maxlength="16"
            class="rounded border px-3 py-2"
        /></label>
        <label class="grid gap-1 text-sm"
          >Time zone<input
            v-model="provisionForm.timezone"
            required
            class="rounded border px-3 py-2"
        /></label>
        <button
          type="submit"
          class="w-fit rounded bg-slate-900 px-4 py-2 text-white"
          :disabled="provisionForm.processing"
        >
          Provision
        </button>
      </form>
    </section>

    <section aria-labelledby="alliances-heading" class="space-y-4">
      <h2 id="alliances-heading" class="text-xl font-semibold">Alliance fleet</h2>
      <div class="overflow-x-auto rounded border">
        <table class="min-w-full text-left text-sm">
          <thead>
            <tr>
              <th class="p-2">Alliance</th>
              <th class="p-2">Status</th>
              <th class="p-2">Members</th>
              <th class="p-2">Storage</th>
              <th class="p-2">Integrations</th>
              <th class="p-2">Plan</th>
              <th class="p-2">Operations</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="alliance in platform.alliances"
              :key="alliance.id"
              class="border-t align-top"
            >
              <td class="p-2">
                <strong>{{ alliance.name }}</strong
                ><br /><span class="text-slate-500">{{ alliance.slug }}</span>
              </td>
              <td class="p-2">{{ alliance.status }}</td>
              <td class="p-2">{{ alliance.activeMembers }}</td>
              <td class="p-2">{{ alliance.storageBytes.toLocaleString() }} B</td>
              <td class="p-2">
                {{ alliance.apiCredentials }} API / {{ alliance.webhooks }} webhooks
              </td>
              <td class="p-2">{{ alliance.plan }}</td>
              <td class="p-2">
                <div class="flex flex-wrap gap-2">
                  <Link :href="`/platform?alliance=${alliance.id}`" class="rounded border px-2 py-1"
                    >Manage</Link
                  >
                  <a
                    :href="`/platform/alliances/${alliance.id}/export.json`"
                    class="rounded border px-2 py-1"
                    >Export</a
                  >
                  <button
                    type="button"
                    class="rounded border px-2 py-1"
                    @click="router.post(`/platform/alliances/${alliance.id}/usage`)"
                  >
                    Capture usage
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section
      v-if="selectedAlliance && selected"
      aria-labelledby="selected-heading"
      class="space-y-6 rounded border p-5"
    >
      <div>
        <h2 id="selected-heading" class="text-xl font-semibold">
          Manage {{ selectedAlliance.name }}
        </h2>
        <p class="text-sm text-slate-600">Current lifecycle state: {{ selected.status }}</p>
      </div>

      <div class="grid gap-5 lg:grid-cols-2">
        <form
          class="space-y-3 rounded border p-4"
          @submit.prevent="settingsForm.put(`/platform/alliances/${selectedAlliance.id}/settings`)"
        >
          <h3 class="font-semibold">Operational settings</h3>
          <label class="grid gap-1 text-sm"
            >Retention days<input
              v-model.number="settingsForm.retention_days"
              type="number"
              min="1"
              max="3650"
              class="rounded border px-3 py-2"
          /></label>
          <label class="grid gap-1 text-sm"
            >Queue partition<select
              v-model="settingsForm.queue_partition"
              class="rounded border px-3 py-2"
            >
              <option>standard</option>
              <option>high-volume</option>
              <option>maintenance-sensitive</option>
            </select></label
          >
          <label class="flex gap-2 text-sm"
            ><input v-model="settingsForm.api_access_enabled" type="checkbox" /> API access
            enabled</label
          >
          <label class="flex gap-2 text-sm"
            ><input v-model="settingsForm.webhooks_enabled" type="checkbox" /> Webhooks
            enabled</label
          >
          <button type="submit" class="rounded bg-slate-900 px-3 py-2 text-white">
            Save settings
          </button>
        </form>

        <form
          class="space-y-3 rounded border p-4"
          @submit.prevent="planForm.put(`/platform/alliances/${selectedAlliance.id}/plan`)"
        >
          <h3 class="font-semibold">Plan entitlement</h3>
          <label class="grid gap-1 text-sm"
            >Plan<select v-model="planForm.plan_code" class="rounded border px-3 py-2">
              <option v-for="plan in platform.plans" :key="plan.code" :value="plan.code">
                {{ plan.name }} ({{ plan.code }})
              </option>
            </select></label
          >
          <button type="submit" class="rounded bg-slate-900 px-3 py-2 text-white">
            Assign plan
          </button>
          <pre class="overflow-auto rounded bg-slate-50 p-2 text-xs">{{
            platform.plans.find((plan) => plan.code === planForm.plan_code)?.entitlements
          }}</pre>
        </form>

        <form
          class="space-y-3 rounded border p-4"
          @submit.prevent="
            ownershipForm.post(`/platform/alliances/${selectedAlliance.id}/ownership`)
          "
        >
          <h3 class="font-semibold">Ownership transfer</h3>
          <label class="grid gap-1 text-sm"
            >New owner<select
              v-model="ownershipForm.membership_id"
              required
              class="rounded border px-3 py-2"
            >
              <option value="" disabled>Select active member</option>
              <option
                v-for="member in selectedAlliance.members.filter(
                  (item) => item.status === 'active',
                )"
                :key="member.id"
                :value="member.id"
              >
                {{ member.name }} — {{ member.email }}
              </option>
            </select></label
          >
          <button type="submit" class="rounded bg-slate-900 px-3 py-2 text-white">
            Transfer ownership
          </button>
        </form>

        <form
          class="space-y-3 rounded border p-4"
          @submit.prevent="featureForm.put(`/platform/alliances/${selectedAlliance.id}/features`)"
        >
          <h3 class="font-semibold">Feature flag</h3>
          <label class="grid gap-1 text-sm"
            >Feature key<input
              v-model="featureForm.feature_key"
              required
              class="rounded border px-3 py-2"
          /></label>
          <label class="flex gap-2 text-sm"
            ><input v-model="featureForm.enabled" type="checkbox" /> Enabled</label
          >
          <button type="submit" class="rounded bg-slate-900 px-3 py-2 text-white">
            Set feature
          </button>
          <ul class="text-sm text-slate-600">
            <li v-for="feature in selectedAlliance.features" :key="feature.key">
              {{ feature.key }}: {{ feature.enabled ? 'on' : 'off' }}
            </li>
          </ul>
        </form>
      </div>

      <div class="space-y-3 rounded border border-amber-300 p-4">
        <h3 class="font-semibold">Lifecycle controls</h3>
        <label class="grid gap-1 text-sm"
          >Reason<input
            v-model="lifecycleForm.reason"
            maxlength="500"
            class="rounded border px-3 py-2"
        /></label>
        <div class="flex flex-wrap gap-2">
          <button type="button" class="rounded border px-3 py-2" @click="lifecycle('suspend')">
            Suspend
          </button>
          <button type="button" class="rounded border px-3 py-2" @click="lifecycle('close')">
            Close
          </button>
          <button
            type="button"
            class="rounded border border-red-500 px-3 py-2 text-red-700"
            @click="lifecycle('delete')"
          >
            Delete logically
          </button>
          <button type="button" class="rounded border px-3 py-2" @click="lifecycle('restore')">
            Restore
          </button>
        </div>
        <p class="text-xs text-slate-600">
          Deletion is reversible until the recorded retention deadline. Active legal holds block
          deletion.
        </p>
      </div>
    </section>

    <section aria-labelledby="holds-heading" class="space-y-4 rounded border p-5">
      <h2 id="holds-heading" class="text-xl font-semibold">Legal holds</h2>
      <form
        class="grid gap-3 md:grid-cols-4"
        @submit.prevent="holdForm.post('/platform/legal-holds')"
      >
        <label class="grid gap-1 text-sm"
          >Subject type<select v-model="holdForm.subject_type" class="rounded border px-3 py-2">
            <option value="alliance">Alliance</option>
            <option value="user">User</option>
          </select></label
        >
        <label class="grid gap-1 text-sm"
          >Subject ID<input v-model="holdForm.subject_id" required class="rounded border px-3 py-2"
        /></label>
        <label class="grid gap-1 text-sm md:col-span-2"
          >Reason<input
            v-model="holdForm.reason"
            required
            maxlength="1000"
            class="rounded border px-3 py-2"
        /></label>
        <button type="submit" class="w-fit rounded bg-slate-900 px-3 py-2 text-white">
          Place hold
        </button>
      </form>
      <ul class="space-y-2 text-sm">
        <li
          v-for="hold in platform.legalHolds"
          :key="hold.id"
          class="flex flex-wrap items-center justify-between gap-2 rounded border p-3"
        >
          <span
            ><strong>{{ hold.subjectType }} {{ hold.subjectId }}</strong> — {{ hold.reason }}</span
          >
          <button
            type="button"
            class="rounded border px-2 py-1"
            @click="router.delete(`/platform/legal-holds/${hold.id}`)"
          >
            Release
          </button>
        </li>
      </ul>
    </section>
  </main>
</template>
