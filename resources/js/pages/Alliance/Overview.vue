<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';

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
}>();

const inviteForm = useForm({
  email: '',
});

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
          class="ml-1 break-all font-semibold underline"
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
              <th class="pb-3 pr-5 font-medium">Email</th>
              <th class="pb-3 pr-5 font-medium">Status</th>
              <th class="pb-3 pr-5 font-medium">Expires</th>
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
  </main>
</template>
