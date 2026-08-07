<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps<{
  alliance: {
    id: string;
    name: string;
    slug: string;
    kingdom: string | null;
    language: string;
    timezone: string;
  };
  membership: {
    id: string;
    roles: Array<{ key: string; name: string }>;
  };
  invitationManagement: {
    allowed: boolean;
    invitations: Array<{
      id: string;
      email: string;
      status: string;
      expiresAt: string | null;
      createdAt: string | null;
    }>;
    issuedLink: string | null;
  };
  membershipManagement: {
    allowed: boolean;
    rolesAllowed: boolean;
    members: Array<{
      id: string;
      user: { id: number; name: string; email: string };
      status: string;
      roles: Array<{ id: string; key: string; name: string }>;
    }>;
    roleCatalog: Array<{ id: string; key: string; name: string }>;
    currentUserId: number;
  };
}>();

const inviteForm = useForm({
  email: '',
});

const statusSelections = reactive<Record<string, string>>(
  Object.fromEntries(
    props.membershipManagement.members.map((member) => [member.id, member.status]),
  ),
);
const roleSelections = reactive<Record<string, string>>({});

function sendInvitation(): void {
  inviteForm.post('/alliance/invitations', {
    preserveScroll: true,
    onSuccess: () => inviteForm.reset(),
  });
}

function resendInvitation(id: string): void {
  router.post(`/alliance/invitations/${id}/resend`, {}, { preserveScroll: true });
}

function revokeInvitation(id: string): void {
  router.delete(`/alliance/invitations/${id}`, { preserveScroll: true });
}

function updateMembershipStatus(id: string): void {
  router.patch(
    `/alliance/memberships/${id}/status`,
    { status: statusSelections[id] },
    { preserveScroll: true },
  );
}

function assignRole(membershipId: string): void {
  const roleId = roleSelections[membershipId];
  if (!roleId) return;

  router.put(
    `/alliance/memberships/${membershipId}/roles/${roleId}`,
    {},
    {
      preserveScroll: true,
      onSuccess: () => {
        roleSelections[membershipId] = '';
      },
    },
  );
}

function removeRole(membershipId: string, roleId: string): void {
  router.delete(`/alliance/memberships/${membershipId}/roles/${roleId}`, {
    preserveScroll: true,
  });
}

function leaveAlliance(): void {
  if (!window.confirm(`Leave ${props.alliance.name}? You will lose your current alliance roles.`)) {
    return;
  }

  router.delete('/alliance/membership');
}
</script>

<template>
  <Head :title="alliance.name" />

  <main class="mx-auto min-h-screen max-w-5xl px-6 py-12 lg:px-8">
    <Link class="text-sm font-semibold text-cyan-300 hover:text-cyan-200" href="/dashboard">
      ← Back to dashboard
    </Link>

    <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-8">
      <p class="text-sm font-semibold tracking-[0.2em] text-cyan-300 uppercase">Active alliance</p>
      <h1 class="mt-3 text-4xl font-bold">{{ alliance.name }}</h1>
      <dl class="mt-8 grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-slate-800 p-4">
          <dt class="text-sm text-slate-400">Kingdom</dt>
          <dd class="mt-1 font-semibold">{{ alliance.kingdom || 'Not set' }}</dd>
        </div>
        <div class="rounded-xl border border-slate-800 p-4">
          <dt class="text-sm text-slate-400">Time zone</dt>
          <dd class="mt-1 font-semibold">{{ alliance.timezone }}</dd>
        </div>
        <div class="rounded-xl border border-slate-800 p-4">
          <dt class="text-sm text-slate-400">Language</dt>
          <dd class="mt-1 font-semibold">{{ alliance.language }}</dd>
        </div>
        <div class="rounded-xl border border-slate-800 p-4">
          <dt class="text-sm text-slate-400">Your roles</dt>
          <dd class="mt-1 font-semibold">
            {{ membership.roles.map((role) => role.name).join(', ') || 'None' }}
          </dd>
        </div>
      </dl>
      <button
        class="mt-6 text-sm font-semibold text-rose-300 hover:text-rose-200"
        type="button"
        @click="leaveAlliance"
      >
        Leave alliance
      </button>
    </section>

    <section
      v-if="props.invitationManagement.allowed"
      class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-8"
    >
      <h2 class="text-2xl font-semibold">Invitations</h2>
      <p class="mt-2 text-sm text-slate-400">
        Invite an account by email. Resending rotates the token so older links stop working.
      </p>

      <div
        v-if="props.invitationManagement.issuedLink"
        class="mt-5 rounded-lg border border-emerald-800 bg-emerald-950/30 p-4 text-sm text-emerald-100"
      >
        New invitation link:
        <a
          class="ml-1 font-semibold break-all underline"
          :href="props.invitationManagement.issuedLink"
          rel="noopener noreferrer"
          target="_blank"
        >
          {{ props.invitationManagement.issuedLink }}
        </a>
      </div>

      <form class="mt-6 flex flex-col gap-3 sm:flex-row" @submit.prevent="sendInvitation">
        <div class="flex-1">
          <label class="sr-only" for="invite-email">Email address</label>
          <input
            id="invite-email"
            v-model="inviteForm.email"
            class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
            placeholder="member@example.com"
            required
            type="email"
          />
          <p v-if="inviteForm.errors.email" class="mt-1 text-sm text-rose-300">
            {{ inviteForm.errors.email }}
          </p>
        </div>
        <button
          class="rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
          :disabled="inviteForm.processing"
          type="submit"
        >
          Send invitation
        </button>
      </form>

      <div class="mt-8 overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead class="text-slate-400">
            <tr>
              <th class="pr-5 pb-3 font-medium">Email</th>
              <th class="pr-5 pb-3 font-medium">Status</th>
              <th class="pr-5 pb-3 font-medium">Expires</th>
              <th class="pb-3 font-medium">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-for="invitation in props.invitationManagement.invitations" :key="invitation.id">
              <td class="py-4 pr-5">{{ invitation.email }}</td>
              <td class="py-4 pr-5 capitalize">{{ invitation.status }}</td>
              <td class="py-4 pr-5 text-slate-400">
                {{ invitation.expiresAt ? new Date(invitation.expiresAt).toLocaleString() : '—' }}
              </td>
              <td class="py-4">
                <div v-if="['pending', 'expired'].includes(invitation.status)" class="flex gap-3">
                  <button
                    class="font-semibold text-cyan-300 hover:text-cyan-200"
                    type="button"
                    @click="resendInvitation(invitation.id)"
                  >
                    Resend
                  </button>
                  <button
                    v-if="invitation.status === 'pending'"
                    class="font-semibold text-rose-300 hover:text-rose-200"
                    type="button"
                    @click="revokeInvitation(invitation.id)"
                  >
                    Revoke
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="props.invitationManagement.invitations.length === 0">
              <td class="py-5 text-slate-500" colspan="4">No invitations yet.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section
      v-if="props.membershipManagement.allowed || props.membershipManagement.rolesAllowed"
      class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-8"
    >
      <h2 class="text-2xl font-semibold">Membership administration</h2>
      <p class="mt-2 text-sm text-slate-400">
        Status changes follow the alliance role hierarchy. Only owners can change role assignments.
      </p>

      <div class="mt-6 space-y-4">
        <article
          v-for="member in props.membershipManagement.members"
          :key="member.id"
          class="rounded-xl border border-slate-800 p-5"
        >
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
              <h3 class="font-semibold">
                {{ member.user.name }}
                <span
                  v-if="member.user.id === props.membershipManagement.currentUserId"
                  class="text-slate-500"
                >
                  (you)
                </span>
              </h3>
              <p class="mt-1 text-sm text-slate-400">{{ member.user.email }}</p>
              <p class="mt-2 text-xs text-slate-500 capitalize">Status: {{ member.status }}</p>
            </div>

            <div
              v-if="
                props.membershipManagement.allowed &&
                member.user.id !== props.membershipManagement.currentUserId
              "
              class="flex gap-2"
            >
              <select
                v-model="statusSelections[member.id]"
                class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm"
              >
                <option value="active">Active</option>
                <option value="suspended">Suspended</option>
                <option value="removed">Removed</option>
              </select>
              <button
                class="rounded-lg border border-slate-700 px-3 py-2 text-sm font-semibold"
                type="button"
                @click="updateMembershipStatus(member.id)"
              >
                Apply
              </button>
            </div>
          </div>

          <div class="mt-4 flex flex-wrap gap-2">
            <span
              v-for="role in member.roles"
              :key="role.id"
              class="inline-flex items-center gap-2 rounded-full bg-slate-800 px-3 py-1 text-xs"
            >
              {{ role.name }}
              <button
                v-if="props.membershipManagement.rolesAllowed"
                class="font-bold text-rose-300"
                :aria-label="`Remove ${role.name} from ${member.user.name}`"
                type="button"
                @click="removeRole(member.id, role.id)"
              >
                ×
              </button>
            </span>
          </div>

          <div v-if="props.membershipManagement.rolesAllowed" class="mt-4 flex flex-wrap gap-2">
            <select
              v-model="roleSelections[member.id]"
              class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm"
            >
              <option value="">Choose role</option>
              <option
                v-for="role in props.membershipManagement.roleCatalog"
                :key="role.id"
                :disabled="member.roles.some((assigned) => assigned.id === role.id)"
                :value="role.id"
              >
                {{ role.name }}
              </option>
            </select>
            <button
              class="rounded-lg border border-slate-700 px-3 py-2 text-sm font-semibold"
              type="button"
              @click="assignRole(member.id)"
            >
              Add role
            </button>
          </div>
        </article>
      </div>
    </section>
  </main>
</template>
