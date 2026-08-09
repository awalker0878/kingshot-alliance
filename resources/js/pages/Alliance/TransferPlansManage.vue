<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

type Plan = {
  id: string;
  label: string;
  homeKingdom: string;
  startsOn: string | null;
  endsOn: string | null;
  state: string;
  createdAt: string | null;
};

const props = defineProps<{
  alliance: { id: string; name: string; kingdom: string | null };
  plans: Plan[];
}>();

const createForm = useForm({
  label: '',
  starts_on: '',
  ends_on: '',
});

const transitionForm = useForm<Record<string, string>>({});

function createPlan(): void {
  createForm.post('/alliance/transfers', {
    preserveScroll: true,
    onSuccess: () => createForm.reset(),
  });
}

function transition(plan: Plan, action: 'open' | 'lock' | 'close' | 'cancel'): void {
  if (action === 'cancel' && !window.confirm(`Cancel transfer cycle “${plan.label}”?`)) {
    return;
  }

  transitionForm.post(`/alliance/transfers/${plan.id}/${action}`, {
    preserveScroll: true,
  });
}

function stateLabel(state: string): string {
  return state.charAt(0).toUpperCase() + state.slice(1);
}

function canOpen(plan: Plan): boolean {
  return plan.state === 'draft' && !props.plans.some((candidate) => candidate.state === 'open');
}
</script>

<template>
  <Head :title="`Manage transfers · ${alliance.name}`" />
  <main>
    <header>
      <Link href="/alliance/transfers">← Transfer planning</Link>
      <h1>Manage transfer cycles</h1>
      <p>{{ alliance.name }} · Kingdom {{ alliance.kingdom ?? 'not set' }}</p>
      <Link href="/alliance/roster/manage">Manage roster</Link>
    </header>

    <section>
      <h2>Create transfer cycle</h2>
      <p>
        New cycles begin in Draft and capture the alliance's current Kingdom as immutable planning
        context.
      </p>
      <form @submit.prevent="createPlan">
        <div>
          <label for="transfer-label">Cycle label</label>
          <input
            id="transfer-label"
            v-model="createForm.label"
            maxlength="160"
            required
            type="text"
          />
          <p v-if="createForm.errors.label">{{ createForm.errors.label }}</p>
        </div>
        <div>
          <label for="transfer-start">Start date</label>
          <input id="transfer-start" v-model="createForm.starts_on" type="date" />
          <p v-if="createForm.errors.starts_on">{{ createForm.errors.starts_on }}</p>
        </div>
        <div>
          <label for="transfer-end">End date</label>
          <input id="transfer-end" v-model="createForm.ends_on" type="date" />
          <p v-if="createForm.errors.ends_on">{{ createForm.errors.ends_on }}</p>
        </div>
        <button :disabled="createForm.processing" type="submit">Create draft</button>
      </form>
      <p v-if="createForm.errors.plan" role="alert">{{ createForm.errors.plan }}</p>
    </section>

    <section>
      <h2>Transfer cycles</h2>
      <p>
        The normal lifecycle is Draft → Open → Locked → Closed. Draft, Open, or Locked cycles may be
        cancelled.
      </p>
      <p v-if="transitionForm.errors.plan" role="alert">{{ transitionForm.errors.plan }}</p>

      <div v-if="plans.length" class="overflow-x-auto">
        <table>
          <thead>
            <tr>
              <th scope="col">Cycle</th>
              <th scope="col">Home kingdom</th>
              <th scope="col">Dates</th>
              <th scope="col">State</th>
              <th scope="col">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="plan in plans" :key="plan.id">
              <td>{{ plan.label }}</td>
              <td>{{ plan.homeKingdom }}</td>
              <td>{{ plan.startsOn ?? '—' }} → {{ plan.endsOn ?? '—' }}</td>
              <td>{{ stateLabel(plan.state) }}</td>
              <td>
                <button
                  v-if="plan.state === 'draft'"
                  :disabled="!canOpen(plan)"
                  type="button"
                  @click="transition(plan, 'open')"
                >
                  Open
                </button>
                <button
                  v-if="plan.state === 'open'"
                  type="button"
                  @click="transition(plan, 'lock')"
                >
                  Lock
                </button>
                <button
                  v-if="plan.state === 'locked'"
                  type="button"
                  @click="transition(plan, 'close')"
                >
                  Close
                </button>
                <button
                  v-if="['draft', 'open', 'locked'].includes(plan.state)"
                  type="button"
                  @click="transition(plan, 'cancel')"
                >
                  Cancel
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else>No transfer cycles have been created yet.</p>
    </section>
  </main>
</template>
