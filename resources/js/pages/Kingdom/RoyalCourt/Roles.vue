<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';

import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type RoleOption = { key: string; name: string };
type PlayerOption = { id: string; name: string; gamePlayerId: string | null };
type Assignment = {
  id: string;
  player: PlayerOption;
  role: RoleOption;
  assignedAt: string | null;
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string };
  kingdom: { id: string; number: number };
  roles: RoleOption[];
  players: PlayerOption[];
  assignments: Assignment[];
}>();

const { t, formatDate } = useLocale();
const form = useForm({
  player_id: props.players[0]?.id ?? '',
  role: props.roles[0]?.key ?? '',
});

function assignRole(): void {
  form.post('/alliance/settings/kingdom/roles', {
    preserveScroll: true,
    onSuccess: () => form.reset('player_id'),
  });
}

function removeRole(assignment: Assignment): void {
  if (!window.confirm(t('kingdomP7A.rolesRemoveConfirm', { name: assignment.player.name }))) return;

  router.delete(`/alliance/settings/kingdom/roles/${assignment.id}`, {
    preserveScroll: true,
  });
}
</script>

<template>
  <Head :title="`${t('kingdomP7A.rolesTitle')} · #${kingdom.number}`" />
  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <header class="flex flex-wrap items-start justify-between gap-5">
      <div class="max-w-3xl">
        <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('kingdomP7A.rolesEyebrow') }}
        </p>
        <h1 class="ks-display mt-2 text-3xl font-bold sm:text-4xl">
          {{ t('kingdomP7A.rolesTitle') }}
        </h1>
        <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('kingdomP7A.rolesSubtitle', { kingdom: kingdom.number }) }}
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <Link
          href="/alliance/kingdom-alliances"
          class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold"
        >
          {{ t('kingdomP7A.overviewTitle') }}
        </Link>
      </div>
    </header>

    <div class="mt-6 grid gap-5 xl:grid-cols-[minmax(320px,0.42fr)_minmax(0,1fr)]">
      <section class="ks-surface p-5" aria-labelledby="kingdom-role-assignment-heading">
        <h2 id="kingdom-role-assignment-heading" class="ks-display text-xl font-semibold">
          {{ t('kingdomP7A.rolesAssign') }}
        </h2>
        <p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('kingdomP7A.rolesAssignHelp') }}
        </p>
        <form class="mt-5 space-y-4" @submit.prevent="assignRole">
          <div>
            <label for="kingdom-role-player" class="text-sm font-semibold">
              {{ t('kingdomP7A.rolesPlayer') }}
            </label>
            <select
              id="kingdom-role-player"
              v-model="form.player_id"
              class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/20 px-3 py-2.5"
              :aria-invalid="form.errors.player_id ? 'true' : undefined"
            >
              <option v-for="player in players" :key="player.id" :value="player.id">
                {{ player.name }}{{ player.gamePlayerId ? ` · ${player.gamePlayerId}` : '' }}
              </option>
            </select>
            <p v-if="form.errors.player_id" class="mt-2 text-sm text-red-300" role="alert">
              {{ form.errors.player_id }}
            </p>
          </div>

          <div>
            <label for="kingdom-role-key" class="text-sm font-semibold">
              {{ t('kingdomP7A.rolesRole') }}
            </label>
            <select
              id="kingdom-role-key"
              v-model="form.role"
              class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/20 px-3 py-2.5"
            >
              <option v-for="role in roles" :key="role.key" :value="role.key">
                {{ role.name }}
              </option>
            </select>
            <p v-if="form.errors.role" class="mt-2 text-sm text-red-300" role="alert">
              {{ form.errors.role }}
            </p>
          </div>

          <button
            type="submit"
            :disabled="form.processing"
            class="rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2.5 text-sm font-semibold text-[var(--ks-ivory)] disabled:opacity-60"
          >
            {{ t('kingdomP7A.rolesAssignAction') }}
          </button>
        </form>
      </section>

      <section class="ks-surface overflow-hidden" aria-labelledby="kingdom-role-list-heading">
        <div class="border-b border-[var(--ks-border)] p-5">
          <h2 id="kingdom-role-list-heading" class="ks-display text-xl font-semibold">
            {{ t('kingdomP7A.rolesAssignments') }}
          </h2>
          <p class="mt-2 text-sm text-[var(--ks-text-secondary)]">
            {{ t('kingdomP7A.rolesAssignmentsHelp') }}
          </p>
        </div>

        <div v-if="assignments.length" class="divide-y divide-[var(--ks-border)]">
          <article
            v-for="assignment in assignments"
            :key="assignment.id"
            class="flex flex-wrap items-center justify-between gap-4 p-5"
          >
            <div>
              <p class="font-semibold">{{ assignment.player.name }}</p>
              <p
                v-if="assignment.player.gamePlayerId"
                class="mt-1 text-sm text-[var(--ks-text-muted)]"
              >
                {{ assignment.player.gamePlayerId }}
              </p>
              <p class="mt-2 text-sm text-[var(--ks-gold)]">{{ assignment.role.name }}</p>
              <p v-if="assignment.assignedAt" class="mt-1 text-xs text-[var(--ks-text-muted)]">
                {{ t('kingdomP7A.rolesAssignedAt', { date: formatDate(assignment.assignedAt) }) }}
              </p>
            </div>
            <button
              type="button"
              class="rounded-[var(--ks-radius-sm)] border border-red-400/40 px-3 py-2 text-sm font-semibold text-red-200"
              @click="removeRole(assignment)"
            >
              {{ t('kingdomP7A.rolesRemove') }}
            </button>
          </article>
        </div>
        <p v-else class="p-5 text-sm text-[var(--ks-text-muted)]">
          {{ t('kingdomP7A.rolesNoAssignments') }}
        </p>
      </section>
    </div>
  </AppLayout>
</template>
