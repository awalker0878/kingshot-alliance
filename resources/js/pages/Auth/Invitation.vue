<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps<{
  invitation: {
    token: string;
    email: string;
    expiresAt: string | null;
    alliance: {
      id: string;
      name: string;
    };
  };
  authenticated: boolean;
  authenticatedEmail: string | null;
}>();

function accept(): void {
  router.post(`/invitations/${props.invitation.token}/accept`);
}
</script>

<template>
  <Head title="Alliance invitation" />

  <main class="mx-auto flex min-h-screen max-w-xl items-center px-6 py-16">
    <section class="w-full rounded-2xl border border-slate-800 bg-slate-900/70 p-8">
      <p class="text-sm font-semibold tracking-[0.2em] text-cyan-300 uppercase">Invitation</p>
      <h1 class="mt-3 text-3xl font-bold">Join {{ invitation.alliance.name }}</h1>
      <p class="mt-3 text-sm text-slate-300">
        This invitation is for <strong>{{ invitation.email }}</strong
        >.
      </p>
      <p v-if="invitation.expiresAt" class="mt-2 text-xs text-slate-500">
        Expires {{ new Date(invitation.expiresAt).toLocaleString() }}
      </p>

      <div v-if="authenticated" class="mt-8">
        <p
          v-if="authenticatedEmail?.toLowerCase() !== invitation.email.toLowerCase()"
          class="rounded-lg border border-rose-800 bg-rose-950/30 p-4 text-sm text-rose-200"
        >
          You are signed in as {{ authenticatedEmail }}. Sign in with the invited email address to
          accept this invitation.
        </p>
        <button
          v-else
          class="w-full rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950"
          type="button"
          @click="accept"
        >
          Accept invitation
        </button>
      </div>

      <div v-else class="mt-8 grid gap-3">
        <Link
          class="rounded-lg bg-cyan-300 px-4 py-2 text-center font-semibold text-slate-950"
          :href="`/register?invitation=${encodeURIComponent(invitation.token)}`"
        >
          Create account and join
        </Link>
        <Link
          class="rounded-lg border border-slate-700 px-4 py-2 text-center font-semibold"
          :href="`/login?invitation=${encodeURIComponent(invitation.token)}`"
        >
          Sign in to accept
        </Link>
      </div>
    </section>
  </main>
</template>
