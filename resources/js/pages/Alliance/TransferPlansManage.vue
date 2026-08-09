<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

type Plan = {
  id: string;
  label: string;
  homeKingdom: string;
  startsOn: string | null;
  endsOn: string | null;
  state: string;
  createdAt: string | null;
};

type Group = {
  id: string;
  name: string;
  direction: 'incoming' | 'outgoing';
  destinationKingdom: string | null;
  state: 'active' | 'archived';
  coordinator: { name: string } | null;
  coordinatorMembershipId: string | null;
  managerNotes: string | null;
};

type ParticipantGroup = {
  name: string;
  direction: 'incoming' | 'outgoing';
  destinationKingdom: string | null;
  coordinator: { name: string } | null;
};

type Participant = {
  id: string;
  direction: 'staying' | 'outgoing' | 'incoming';
  name: string;
  gamePlayerId: string | null;
  rosterEntryId: string | null;
  transferGroupId: string | null;
  sourceKingdom: string | null;
  destinationKingdom: string | null;
  membership: { id: string; name: string; email: string } | null;
  group: ParticipantGroup | null;
  managerNotes: string | null;
  withdrawnAt: string | null;
};

type RosterOption = {
  id: string;
  name: string;
  gamePlayerId: string | null;
  membershipId: string | null;
};

type MembershipOption = {
  id: string;
  name: string;
  email: string;
};

const props = defineProps<{
  alliance: { id: string; name: string; kingdom: string | null };
  plans: Plan[];
  mutablePlan: Plan | null;
  groups: Group[];
  participants: Participant[];
  rosterOptions: RosterOption[];
  memberships: MembershipOption[];
}>();

const createForm = useForm({
  label: '',
  starts_on: '',
  ends_on: '',
});
const transitionForm = useForm<Record<string, string>>({});
const groupForm = useForm({
  name: '',
  direction: 'incoming' as 'incoming' | 'outgoing',
  destination_kingdom: '',
  coordinator_membership_id: '',
  manager_notes: '',
});
const participantForm = useForm({
  direction: 'staying',
  roster_entry_id: '',
  name: '',
  game_player_id: '',
  membership_id: '',
  source_kingdom: '',
  destination_kingdom: '',
  manager_notes: '',
});
const createPlanError = computed(() => (createForm.errors as Record<string, string>).plan);
const groupError = computed(() => (groupForm.errors as Record<string, string>).group);
const participantError = computed(
  () => (participantForm.errors as Record<string, string>).participant,
);
const activeGroups = computed(() => props.groups.filter((group) => group.state === 'active'));

const groupDrafts = reactive(
  Object.fromEntries(
    props.groups.map((group) => [
      group.id,
      {
        name: group.name,
        direction: group.direction,
        destination_kingdom: group.destinationKingdom ?? '',
        coordinator_membership_id: group.coordinatorMembershipId ?? '',
        manager_notes: group.managerNotes ?? '',
      },
    ]),
  ) as Record<
    string,
    {
      name: string;
      direction: 'incoming' | 'outgoing';
      destination_kingdom: string;
      coordinator_membership_id: string;
      manager_notes: string;
    }
  >,
);

const groupAssignments = reactive(
  Object.fromEntries(
    props.participants.map((participant) => [participant.id, participant.transferGroupId ?? '']),
  ) as Record<string, string>,
);

const drafts = reactive(
  Object.fromEntries(
    props.participants.map((participant) => [
      participant.id,
      {
        direction: participant.direction,
        roster_entry_id: participant.rosterEntryId ?? '',
        name: participant.name,
        game_player_id: participant.gamePlayerId ?? '',
        membership_id: participant.membership?.id ?? '',
        source_kingdom: participant.sourceKingdom ?? '',
        destination_kingdom: participant.destinationKingdom ?? '',
        manager_notes: participant.managerNotes ?? '',
      },
    ]),
  ) as Record<
    string,
    {
      direction: 'staying' | 'outgoing' | 'incoming';
      roster_entry_id: string;
      name: string;
      game_player_id: string;
      membership_id: string;
      source_kingdom: string;
      destination_kingdom: string;
      manager_notes: string;
    }
  >,
);

function createPlan(): void {
  createForm.post('/alliance/transfers', {
    preserveScroll: true,
    onSuccess: () => createForm.reset(),
  });
}

function transition(plan: Plan, action: 'open' | 'lock' | 'close' | 'cancel'): void {
  if (action === 'cancel' && !window.confirm(`Cancel transfer cycle “${plan.label}”?`)) return;

  transitionForm.post(`/alliance/transfers/${plan.id}/${action}`, { preserveScroll: true });
}

function createGroup(): void {
  if (props.mutablePlan === null) return;

  groupForm.post(`/alliance/transfers/${props.mutablePlan.id}/groups`, {
    preserveScroll: true,
    onSuccess: () => groupForm.reset(),
  });
}

function saveGroup(group: Group): void {
  if (props.mutablePlan === null || group.state !== 'active') return;

  router.patch(
    `/alliance/transfers/${props.mutablePlan.id}/groups/${group.id}`,
    groupDrafts[group.id],
    {
      preserveScroll: true,
    },
  );
}

function archiveGroup(group: Group): void {
  if (props.mutablePlan === null || group.state !== 'active') return;
  if (!window.confirm(`Archive transfer group “${group.name}”?`)) return;

  router.post(
    `/alliance/transfers/${props.mutablePlan.id}/groups/${group.id}/archive`,
    {},
    { preserveScroll: true },
  );
}

function createParticipant(): void {
  if (props.mutablePlan === null) return;

  participantForm.post(`/alliance/transfers/${props.mutablePlan.id}/participants`, {
    preserveScroll: true,
    onSuccess: () => participantForm.reset(),
  });
}

function saveParticipant(participant: Participant): void {
  if (props.mutablePlan === null) return;

  router.patch(
    `/alliance/transfers/${props.mutablePlan.id}/participants/${participant.id}`,
    drafts[participant.id],
    { preserveScroll: true },
  );
}

function assignParticipantGroup(participant: Participant): void {
  if (props.mutablePlan === null || participant.withdrawnAt !== null) return;

  router.patch(
    `/alliance/transfers/${props.mutablePlan.id}/participants/${participant.id}/group`,
    { transfer_group_id: groupAssignments[participant.id] || null },
    { preserveScroll: true },
  );
}

function withdrawParticipant(participant: Participant): void {
  if (props.mutablePlan === null || participant.withdrawnAt !== null) return;
  if (!window.confirm(`Withdraw ${participant.name} from this transfer cycle?`)) return;

  router.post(
    `/alliance/transfers/${props.mutablePlan.id}/participants/${participant.id}/withdraw`,
    {},
    { preserveScroll: true },
  );
}

function stateLabel(state: string): string {
  return state.charAt(0).toUpperCase() + state.slice(1);
}

function canOpen(plan: Plan): boolean {
  return plan.state === 'draft' && !props.plans.some((candidate) => candidate.state === 'open');
}

function isRosterBound(direction: string): boolean {
  return direction === 'staying' || direction === 'outgoing';
}

function compatibleGroups(participant: Participant): Group[] {
  if (participant.direction === 'staying') return [];

  return activeGroups.value.filter((group) => {
    if (group.direction !== participant.direction) return false;
    if (group.direction === 'outgoing' && group.destinationKingdom !== null) {
      return group.destinationKingdom === participant.destinationKingdom;
    }

    return true;
  });
}
</script>

<template>
  <Head :title="`Manage transfers · ${alliance.name}`" />
  <main>
    <header>
      <Link href="/alliance/transfers">← Transfer planning</Link>
      <h1>Manage transfers</h1>
      <p>{{ alliance.name }} · Kingdom {{ alliance.kingdom ?? 'not set' }}</p>
      <Link href="/alliance/roster/manage">Manage roster</Link>
    </header>

    <section>
      <h2>Create transfer cycle</h2>
      <p>
        New cycles begin in Draft and capture the alliance's current Kingdom as planning context.
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
        </div>
        <div>
          <label for="transfer-end">End date</label>
          <input id="transfer-end" v-model="createForm.ends_on" type="date" />
        </div>
        <button :disabled="createForm.processing" type="submit">Create draft</button>
      </form>
      <p v-if="createPlanError" role="alert">{{ createPlanError }}</p>
    </section>

    <section>
      <h2>Transfer cycles</h2>
      <p>
        The normal lifecycle is Draft → Open → Locked → Closed. Draft, Open, or Locked may be
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

    <section v-if="mutablePlan">
      <h2>Transfer groups · {{ mutablePlan.label }}</h2>
      <p>
        Groups coordinate incoming or outgoing players with compatible destinations. A coordinator
        is workflow responsibility only and receives no additional permissions.
      </p>

      <form @submit.prevent="createGroup">
        <div>
          <label for="group-name">Group name</label>
          <input id="group-name" v-model="groupForm.name" maxlength="160" required type="text" />
          <p v-if="groupForm.errors.name">{{ groupForm.errors.name }}</p>
        </div>
        <div>
          <label for="group-direction">Direction</label>
          <select id="group-direction" v-model="groupForm.direction">
            <option value="incoming">Incoming</option>
            <option value="outgoing">Outgoing</option>
          </select>
        </div>
        <div v-if="groupForm.direction === 'outgoing'">
          <label for="group-destination">Destination Kingdom</label>
          <input
            id="group-destination"
            v-model="groupForm.destination_kingdom"
            inputmode="numeric"
            type="text"
          />
          <p>Leave blank while the outgoing destination is undecided.</p>
        </div>
        <p v-else>Incoming destination is fixed to Kingdom {{ mutablePlan.homeKingdom }}.</p>
        <div>
          <label for="group-coordinator">Coordinator</label>
          <select id="group-coordinator" v-model="groupForm.coordinator_membership_id">
            <option value="">Unassigned</option>
            <option v-for="membership in memberships" :key="membership.id" :value="membership.id">
              {{ membership.name }} · {{ membership.email }}
            </option>
          </select>
        </div>
        <div>
          <label for="group-notes">Manager notes</label>
          <textarea id="group-notes" v-model="groupForm.manager_notes" maxlength="5000" rows="3" />
        </div>
        <button :disabled="groupForm.processing" type="submit">Create group</button>
        <p v-if="groupError" role="alert">{{ groupError }}</p>
        <p v-if="groupForm.hasErrors" role="alert">Correct the group fields above and try again.</p>
      </form>

      <div v-if="groups.length" class="overflow-x-auto">
        <table>
          <thead>
            <tr>
              <th scope="col">Group</th>
              <th scope="col">Direction / destination</th>
              <th scope="col">Coordinator</th>
              <th scope="col">Manager notes</th>
              <th scope="col">State</th>
              <th scope="col">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="group in groups" :key="group.id">
              <td>
                <label :for="`group-name-${group.id}`">Name</label>
                <input
                  :id="`group-name-${group.id}`"
                  v-model="groupDrafts[group.id].name"
                  :disabled="group.state !== 'active'"
                  maxlength="160"
                  type="text"
                />
              </td>
              <td>
                <label :for="`group-direction-${group.id}`">Direction</label>
                <select
                  :id="`group-direction-${group.id}`"
                  v-model="groupDrafts[group.id].direction"
                  :disabled="group.state !== 'active'"
                >
                  <option value="incoming">Incoming</option>
                  <option value="outgoing">Outgoing</option>
                </select>
                <template v-if="groupDrafts[group.id].direction === 'outgoing'">
                  <label :for="`group-destination-${group.id}`">Destination Kingdom</label>
                  <input
                    :id="`group-destination-${group.id}`"
                    v-model="groupDrafts[group.id].destination_kingdom"
                    :disabled="group.state !== 'active'"
                    inputmode="numeric"
                    type="text"
                  />
                </template>
                <span v-else>Kingdom {{ mutablePlan.homeKingdom }}</span>
              </td>
              <td>
                <label :for="`group-coordinator-${group.id}`">Coordinator</label>
                <select
                  :id="`group-coordinator-${group.id}`"
                  v-model="groupDrafts[group.id].coordinator_membership_id"
                  :disabled="group.state !== 'active'"
                >
                  <option value="">Unassigned</option>
                  <option
                    v-for="membership in memberships"
                    :key="membership.id"
                    :value="membership.id"
                  >
                    {{ membership.name }}
                  </option>
                </select>
              </td>
              <td>
                <label :for="`group-notes-${group.id}`">Manager notes</label>
                <textarea
                  :id="`group-notes-${group.id}`"
                  v-model="groupDrafts[group.id].manager_notes"
                  :disabled="group.state !== 'active'"
                  maxlength="5000"
                  rows="2"
                />
              </td>
              <td>{{ stateLabel(group.state) }}</td>
              <td>
                <button v-if="group.state === 'active'" type="button" @click="saveGroup(group)">
                  Save
                </button>
                <button v-if="group.state === 'active'" type="button" @click="archiveGroup(group)">
                  Archive
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else>No transfer groups have been created yet.</p>
    </section>

    <section v-if="mutablePlan">
      <h2>Participants · {{ mutablePlan.label }}</h2>
      <p>
        Draft and Open cycles may be edited. Incoming players may exist before roster or site
        membership.
      </p>

      <form @submit.prevent="createParticipant">
        <div>
          <label for="participant-direction">Direction</label>
          <select id="participant-direction" v-model="participantForm.direction">
            <option value="staying">Staying</option>
            <option value="outgoing">Outgoing</option>
            <option value="incoming">Incoming</option>
          </select>
        </div>
        <div v-if="isRosterBound(participantForm.direction)">
          <label for="participant-roster">Roster player</label>
          <select id="participant-roster" v-model="participantForm.roster_entry_id" required>
            <option value="">Select roster player</option>
            <option v-for="entry in rosterOptions" :key="entry.id" :value="entry.id">
              {{ entry.name }}
            </option>
          </select>
        </div>
        <div v-if="participantForm.direction === 'outgoing'">
          <label for="participant-destination">Destination Kingdom</label>
          <input
            id="participant-destination"
            v-model="participantForm.destination_kingdom"
            inputmode="numeric"
            type="text"
          />
          <p>Leave blank while the destination is undecided.</p>
        </div>
        <template v-if="participantForm.direction === 'incoming'">
          <div>
            <label for="participant-name">Incoming player name</label>
            <input
              id="participant-name"
              v-model="participantForm.name"
              maxlength="160"
              type="text"
            />
          </div>
          <div>
            <label for="participant-game-id">Game player ID</label>
            <input
              id="participant-game-id"
              v-model="participantForm.game_player_id"
              maxlength="100"
              type="text"
            />
          </div>
          <div>
            <label for="participant-source">Source Kingdom</label>
            <input
              id="participant-source"
              v-model="participantForm.source_kingdom"
              inputmode="numeric"
              type="text"
            />
          </div>
          <div>
            <label for="participant-membership">Site membership</label>
            <select id="participant-membership" v-model="participantForm.membership_id">
              <option value="">Not linked</option>
              <option v-for="membership in memberships" :key="membership.id" :value="membership.id">
                {{ membership.name }}
              </option>
            </select>
          </div>
        </template>
        <div>
          <label for="participant-notes">Manager notes</label>
          <textarea
            id="participant-notes"
            v-model="participantForm.manager_notes"
            maxlength="5000"
            rows="3"
          />
        </div>
        <button :disabled="participantForm.processing" type="submit">Add participant</button>
        <p v-if="participantError" role="alert">{{ participantError }}</p>
        <p v-if="participantForm.hasErrors" role="alert">
          Correct the participant fields above and try again.
        </p>
      </form>

      <div v-if="participants.length" class="overflow-x-auto">
        <table>
          <thead>
            <tr>
              <th scope="col">Player</th>
              <th scope="col">Direction / destination</th>
              <th scope="col">Identity / linkage</th>
              <th scope="col">Group</th>
              <th scope="col">Manager notes</th>
              <th scope="col">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="participant in participants" :key="participant.id">
              <td>
                <template v-if="participant.rosterEntryId">{{ participant.name }}</template>
                <template v-else>
                  <label :for="`incoming-name-${participant.id}`">Incoming name</label>
                  <input
                    :id="`incoming-name-${participant.id}`"
                    v-model="drafts[participant.id].name"
                    maxlength="160"
                    type="text"
                  />
                </template>
                <span v-if="participant.withdrawnAt"> Withdrawn</span>
              </td>
              <td>
                <template v-if="participant.rosterEntryId">
                  <label :for="`direction-${participant.id}`">Direction</label>
                  <select
                    :id="`direction-${participant.id}`"
                    v-model="drafts[participant.id].direction"
                    :disabled="participant.withdrawnAt !== null"
                  >
                    <option value="staying">Staying</option>
                    <option value="outgoing">Outgoing</option>
                  </select>
                  <template v-if="drafts[participant.id].direction === 'outgoing'">
                    <label :for="`destination-${participant.id}`">Destination Kingdom</label>
                    <input
                      :id="`destination-${participant.id}`"
                      v-model="drafts[participant.id].destination_kingdom"
                      :disabled="participant.withdrawnAt !== null"
                      inputmode="numeric"
                      type="text"
                    />
                  </template>
                </template>
                <template v-else>
                  Incoming → Kingdom {{ mutablePlan.homeKingdom }}
                  <label :for="`source-${participant.id}`">Source Kingdom</label>
                  <input
                    :id="`source-${participant.id}`"
                    v-model="drafts[participant.id].source_kingdom"
                    :disabled="participant.withdrawnAt !== null"
                    inputmode="numeric"
                    type="text"
                  />
                </template>
              </td>
              <td>
                <template v-if="participant.rosterEntryId">
                  Roster-linked
                  <input v-model="drafts[participant.id].roster_entry_id" type="hidden" />
                </template>
                <template v-else>
                  <label :for="`game-id-${participant.id}`">Game player ID</label>
                  <input
                    :id="`game-id-${participant.id}`"
                    v-model="drafts[participant.id].game_player_id"
                    :disabled="participant.withdrawnAt !== null"
                    maxlength="100"
                    type="text"
                  />
                  <label :for="`membership-${participant.id}`">Site membership</label>
                  <select
                    :id="`membership-${participant.id}`"
                    v-model="drafts[participant.id].membership_id"
                    :disabled="participant.withdrawnAt !== null"
                  >
                    <option value="">Not linked</option>
                    <option
                      v-for="membership in memberships"
                      :key="membership.id"
                      :value="membership.id"
                    >
                      {{ membership.name }}
                    </option>
                  </select>
                </template>
              </td>
              <td>
                <template v-if="participant.direction === 'staying'">
                  Staying participants cannot be assigned to moving groups.
                </template>
                <template v-else>
                  <label :for="`group-${participant.id}`">Transfer group</label>
                  <select
                    :id="`group-${participant.id}`"
                    v-model="groupAssignments[participant.id]"
                    :disabled="participant.withdrawnAt !== null"
                  >
                    <option value="">Unassigned</option>
                    <option
                      v-for="group in compatibleGroups(participant)"
                      :key="group.id"
                      :value="group.id"
                    >
                      {{ group.name }}
                    </option>
                  </select>
                  <button
                    v-if="participant.withdrawnAt === null"
                    type="button"
                    @click="assignParticipantGroup(participant)"
                  >
                    Save group
                  </button>
                </template>
              </td>
              <td>
                <label :for="`notes-${participant.id}`">Manager notes</label>
                <textarea
                  :id="`notes-${participant.id}`"
                  v-model="drafts[participant.id].manager_notes"
                  :disabled="participant.withdrawnAt !== null"
                  maxlength="5000"
                  rows="2"
                />
              </td>
              <td>
                <button
                  v-if="participant.withdrawnAt === null"
                  type="button"
                  @click="saveParticipant(participant)"
                >
                  Save
                </button>
                <button
                  v-if="participant.withdrawnAt === null"
                  type="button"
                  @click="withdrawParticipant(participant)"
                >
                  Withdraw
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else>No participants have been added yet.</p>
    </section>

    <section v-else>
      <h2>Participants and groups</h2>
      <p>Create a Draft cycle or keep a cycle Open to edit transfer groups and participants.</p>
    </section>
  </main>
</template>
