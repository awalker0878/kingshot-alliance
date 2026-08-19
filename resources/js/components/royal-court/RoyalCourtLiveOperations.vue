<script setup lang="ts">
import { reactive } from 'vue';

import { displayUtc } from '@/lib/king-perks-display';
import type { GovernorOption, LiveCourt } from '@/types/king-perks';

const props = defineProps<{
  live: LiveCourt;
  players: GovernorOption[];
}>();

const emit = defineEmits<{
  replace: [appointmentId: string, playerId: string];
}>();

const replacementPlayer = reactive<Record<string, string>>({});

function replace(appointmentId: string): void {
  const playerId = replacementPlayer[appointmentId];
  if (!playerId) return;

  emit('replace', appointmentId, playerId);
  delete replacementPlayer[appointmentId];
}
</script>

<template>
  <section
    class="space-y-4 rounded-2xl border border-[var(--ks-border)] bg-[rgba(7,12,13,.70)] p-5"
  >
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h2 class="text-lg font-semibold text-[var(--ks-ivory)]">Live operations</h2>
        <p class="text-sm text-[var(--ks-muted)]">
          Now / next / following for each appointment position.
        </p>
      </div>
      <p class="text-xs text-[var(--ks-muted)]">Court watch {{ displayUtc(live.generatedAt) }}</p>
    </div>

    <div class="grid gap-3 xl:grid-cols-2">
      <article
        v-for="lane in live.lanes"
        :key="lane.type"
        class="rounded-xl border border-[var(--ks-border)] bg-[rgba(24,25,21,.70)] p-4"
      >
        <h3 class="font-semibold text-[var(--ks-ivory)]">{{ lane.label }}</h3>
        <div class="mt-3 grid gap-2 sm:grid-cols-3">
          <div
            v-for="entry in [
              { key: 'NOW', item: lane.now },
              { key: 'NEXT', item: lane.next },
              { key: 'FOLLOWING', item: lane.following },
            ]"
            :key="entry.key"
            class="rounded-lg bg-[rgba(7,12,13,.84)] p-3"
          >
            <p class="text-[11px] font-semibold tracking-wider text-[var(--ks-muted)]">
              {{ entry.key }}
            </p>
            <template v-if="entry.item">
              <p class="mt-1 text-sm font-semibold text-[var(--ks-ivory)]">
                {{ entry.item.playerName ?? 'Unknown Governor' }}
              </p>
              <p class="text-xs text-[var(--ks-muted)]">
                {{ displayUtc(entry.item.startsAt) }}
              </p>
              <p
                class="text-xs"
                :class="entry.item.playerEligible ? 'text-emerald-300' : 'text-rose-300'"
              >
                {{ entry.item.playerEligible ? entry.item.status : 'Governor left Kingdom' }}
              </p>
            </template>
            <p v-else class="mt-1 text-xs text-[var(--ks-muted)]">Open</p>
          </div>
        </div>

        <div
          v-if="lane.now && lane.now.status !== 'completed'"
          class="mt-3 flex flex-wrap items-end gap-2"
        >
          <label class="min-w-48 flex-1 space-y-1 text-xs text-[var(--ks-muted)]">
            <span>Rapid replacement</span>
            <select
              v-model="replacementPlayer[lane.now.id]"
              class="w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-night)] px-3 py-2 text-sm text-[var(--ks-ivory)]"
            >
              <option value="">Select Governor</option>
              <option v-for="player in props.players" :key="player.id" :value="player.id">
                {{ player.name }}
              </option>
            </select>
          </label>
          <button
            type="button"
            class="rounded-lg border border-rose-400/30 px-3 py-2 text-xs font-semibold text-rose-200"
            @click="replace(lane.now.id)"
          >
            No-show + replace
          </button>
        </div>
      </article>
    </div>
  </section>
</template>
