<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
  alliance: {
    id: string;
    name: string;
    kingdom: string | null;
  };
}>();

const form = useForm({
  kingdom: props.alliance.kingdom ?? '',
});

const kingdomDescription = computed(() =>
  form.errors.kingdom ? 'kingdom-number-help kingdom-number-error' : 'kingdom-number-help',
);

function saveKingdom(): void {
  form.patch('/alliance/settings/kingdom', {
    preserveScroll: true,
  });
}
</script>

<template>
  <Head :title="`Kingdom settings · ${alliance.name}`" />

  <main class="mx-auto min-h-screen max-w-3xl px-6 py-12 text-slate-100 lg:px-8">
    <Link class="text-sm font-semibold text-cyan-300 hover:text-cyan-200" href="/alliance">
      ← Back to alliance
    </Link>

    <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-8">
      <p class="text-sm font-semibold tracking-[0.2em] text-cyan-300 uppercase">
        Alliance settings
      </p>
      <h1 class="mt-3 text-3xl font-bold">Kingdom</h1>
      <p class="mt-3 text-sm text-slate-400">
        {{ alliance.name }} references a first-class Kingshot kingdom. Changing this association is
        a privileged alliance-setting mutation and is audited.
      </p>

      <form class="mt-8" @submit.prevent="saveKingdom">
        <label class="block text-sm font-medium" for="kingdom-number">Kingdom number</label>
        <input
          id="kingdom-number"
          v-model="form.kingdom"
          :aria-describedby="kingdomDescription"
          :aria-invalid="form.errors.kingdom ? 'true' : undefined"
          class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
          inputmode="numeric"
          pattern="[1-9][0-9]*"
          type="text"
        />
        <p id="kingdom-number-help" class="mt-2 text-xs text-slate-500">
          Leave blank only when the alliance does not currently have a known kingdom association.
        </p>
        <p
          v-if="form.errors.kingdom"
          id="kingdom-number-error"
          class="mt-2 text-sm text-rose-300"
          role="alert"
        >
          {{ form.errors.kingdom }}
        </p>

        <button
          class="mt-6 rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
          :disabled="form.processing"
          type="submit"
        >
          Save kingdom
        </button>
      </form>
    </section>
  </main>
</template>
