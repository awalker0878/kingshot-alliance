<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import AppLayout from '../../layouts/AppLayout.vue';
import { useLocale } from '../../localization';

type CurrentDiplomacy = {
  exists: boolean;
  state: string;
  effectiveAt: string | null;
  reviewAt: string | null;
  expiresAt: string | null;
  needsReview: boolean;
  terms: string | null;
  rationale: string | null;
  lastActorName: string | null;
};

type DiplomacyTransition = {
  id: string;
  fromState: string;
  toState: string;
  effectiveAt: string;
  reviewAt: string | null;
  expiresAt: string | null;
  terms: string | null;
  rationale: string | null;
  actorName: string | null;
  recordedAt: string;
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string; kingdom: string | null };
  tracking: {
    id: string;
    name: string;
    tag: string | null;
    state: string;
    kingdom: string;
    contextCurrent: boolean;
  };
  states: string[];
  current: CurrentDiplomacy;
  historyLimit: number;
  history: DiplomacyTransition[];
}>();

const { t, formatDate } = useLocale();

function toLocalInput(value: string | null, fallbackNow = false): string {
  if (value === null && !fallbackNow) return '';
  const date = value === null ? new Date() : new Date(value);
  return new Date(date.getTime() - date.getTimezoneOffset() * 60_000).toISOString().slice(0, 16);
}

function toIso(value: string): string {
  return new Date(value).toISOString();
}

function toNullableIso(value: string): string | null {
  return value.trim() === '' ? null : toIso(value);
}

function date(value: string | null): string {
  return value
    ? formatDate(value, { dateStyle: 'medium', timeStyle: 'short' })
    : t('kingdomP7B.notSet');
}

function stateLabel(value: string): string {
  if (value === 'unknown') return t('kingdomP7B.unknown');
  if (value === 'neutral') return t('kingdomP7B.neutral');
  if (value === 'friendly') return t('kingdomP7B.friendly');
  if (value === 'nap') return t('kingdomP7B.nap');
  if (value === 'ally') return t('kingdomP7B.ally');
  if (value === 'rival') return t('kingdomP7B.rival');
  return value.replaceAll('_', ' ');
}

function stateTone(value: string): string {
  if (value === 'ally' || value === 'friendly')
    return 'border-green-400/25 bg-green-500/10 text-green-200';
  if (value === 'rival') return 'border-red-400/25 bg-red-500/10 text-red-200';
  if (value === 'nap') return 'border-purple-400/25 bg-purple-500/10 text-purple-200';
  if (value === 'neutral') return 'border-blue-400/25 bg-blue-500/10 text-blue-200';
  return 'border-[var(--ks-border)] bg-white/5 text-[var(--ks-text-secondary)]';
}

const form = useForm({
  state: props.current.state,
  effective_at: toLocalInput(props.current.effectiveAt, true),
  review_at: toLocalInput(props.current.reviewAt),
  expires_at: toLocalInput(props.current.expiresAt),
  terms: props.current.terms ?? '',
  rationale: props.current.rationale ?? '',
});

function diplomacyError(): string | undefined {
  return (form.errors as Record<string, string | undefined>).diplomacy;
}

function submitTransition(): void {
  form
    .transform((data) => ({
      ...data,
      effective_at: toIso(data.effective_at),
      review_at: toNullableIso(data.review_at),
      expires_at: toNullableIso(data.expires_at),
      terms: data.terms.trim() === '' ? null : data.terms,
      rationale: data.rationale.trim() === '' ? null : data.rationale,
    }))
    .post(`/alliance/kingdom-alliances/${props.tracking.id}/diplomacy/transitions`, {
      preserveScroll: true,
    });
}
</script>

<template>
  <Head
    :title="`${t('kingdomP7B.diplomacyTitle', { alliance: tracking.name })} · ${alliance.name}`"
  />

  <AppLayout :user="user" :alliance-name="alliance.name" :has-active-alliance="true">
    <header class="flex flex-wrap items-start justify-between gap-5">
      <div class="max-w-3xl">
        <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
          {{ t('kingdomP7B.eyebrow') }}
        </p>
        <h1 class="ks-display mt-2 text-3xl font-bold sm:text-4xl">
          {{ t('kingdomP7B.diplomacyTitle', { alliance: tracking.name }) }}
        </h1>
        <p class="mt-3 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('kingdomP7B.diplomacySubtitle', { kingdom: tracking.kingdom }) }}
        </p>
      </div>
      <nav class="flex flex-wrap gap-2" aria-label="Diplomacy workspace">
        <Link
          class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold"
          :href="`/alliance/kingdom-alliances/${tracking.id}/diplomacy/contacts`"
          >{{ t('kingdomP7B.contacts') }}</Link
        >
        <Link
          class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold"
          :href="`/alliance/kingdom-alliances/${tracking.id}/history`"
          >{{ t('kingdomP7B.observationHistory') }}</Link
        >
        <Link
          class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2 text-sm font-semibold"
          href="/alliance/kingdom-alliances/manage"
          >{{ t('kingdomP7B.trackingWorkspace') }}</Link
        >
      </nav>
    </header>

    <section class="mt-6 grid gap-4 xl:grid-cols-[0.85fr_1.6fr]">
      <article class="ks-surface p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="ks-display text-xl font-semibold">
            {{ t('kingdomP7B.currentRelationship') }}
          </h2>
          <span
            class="rounded-full border px-2.5 py-1 text-xs font-bold uppercase"
            :class="stateTone(current.state)"
            >{{ stateLabel(current.state) }}</span
          >
        </div>
        <dl class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
          <div>
            <dt class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
              {{ t('kingdomP7B.effective') }}
            </dt>
            <dd class="mt-1 text-sm">{{ date(current.effectiveAt) }}</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
              {{ t('kingdomP7B.review') }}
            </dt>
            <dd class="mt-1 text-sm">{{ date(current.reviewAt) }}</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
              {{ t('kingdomP7B.expiry') }}
            </dt>
            <dd class="mt-1 text-sm">{{ date(current.expiresAt) }}</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
              {{ t('kingdomP7B.reviewStatus') }}
            </dt>
            <dd class="mt-1 text-sm">
              <span
                v-if="current.needsReview"
                class="rounded-full border border-amber-400/25 bg-amber-500/10 px-2 py-1 text-xs font-semibold text-amber-200"
                >{{ t('kingdomP7B.reviewDue') }}</span
              >
              <span v-else>{{ t('kingdomP7B.noReviewDue') }}</span>
            </dd>
          </div>
          <div>
            <dt class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
              {{ t('kingdomP7B.lastChangedBy') }}
            </dt>
            <dd class="mt-1 text-sm">
              {{ current.lastActorName ?? t('kingdomP7B.noTransition') }}
            </dd>
          </div>
        </dl>
        <div
          v-if="current.terms || current.rationale"
          class="mt-5 space-y-4 border-t border-[var(--ks-border)] pt-4"
        >
          <div v-if="current.terms">
            <p class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
              {{ t('kingdomP7B.terms') }}
            </p>
            <p class="mt-1 text-sm whitespace-pre-wrap text-[var(--ks-text-secondary)]">
              {{ current.terms }}
            </p>
          </div>
          <div v-if="current.rationale">
            <p class="text-xs font-semibold text-[var(--ks-text-muted)] uppercase">
              {{ t('kingdomP7B.rationale') }}
            </p>
            <p class="mt-1 text-sm whitespace-pre-wrap text-[var(--ks-text-secondary)]">
              {{ current.rationale }}
            </p>
          </div>
        </div>
      </article>

      <article class="ks-surface p-5">
        <h2 class="ks-display text-xl font-semibold">{{ t('kingdomP7B.recordState') }}</h2>
        <p class="mt-2 text-sm leading-6 text-[var(--ks-text-secondary)]">
          {{ t('kingdomP7B.recordStateHelp') }}
        </p>
        <p
          v-if="tracking.state !== 'active' || !tracking.contextCurrent"
          class="mt-4 rounded-[var(--ks-radius-sm)] border border-amber-400/25 bg-amber-500/10 p-3 text-sm text-amber-100"
        >
          {{ t('kingdomP7B.readOnlyHistorical') }}
        </p>

        <form class="mt-5 grid gap-4 md:grid-cols-2" @submit.prevent="submitTransition">
          <div>
            <label class="text-sm font-semibold" for="diplomacy-state">{{
              t('kingdomP7B.relationshipState')
            }}</label>
            <select
              id="diplomacy-state"
              v-model="form.state"
              class="ks-input mt-2 w-full"
              :disabled="tracking.state !== 'active' || !tracking.contextCurrent"
            >
              <option v-for="state in states" :key="state" :value="state">
                {{ stateLabel(state) }}
              </option>
            </select>
            <p v-if="form.errors.state" class="mt-1 text-sm text-red-300">
              {{ form.errors.state }}
            </p>
          </div>
          <div>
            <label class="text-sm font-semibold" for="diplomacy-effective">{{
              t('kingdomP7B.effectiveTime')
            }}</label>
            <input
              id="diplomacy-effective"
              v-model="form.effective_at"
              class="ks-input mt-2 w-full"
              :disabled="tracking.state !== 'active' || !tracking.contextCurrent"
              required
              type="datetime-local"
            />
            <p v-if="form.errors.effective_at" class="mt-1 text-sm text-red-300">
              {{ form.errors.effective_at }}
            </p>
          </div>
          <div>
            <label class="text-sm font-semibold" for="diplomacy-review">{{
              t('kingdomP7B.reviewTime')
            }}</label>
            <input
              id="diplomacy-review"
              v-model="form.review_at"
              class="ks-input mt-2 w-full"
              :disabled="tracking.state !== 'active' || !tracking.contextCurrent"
              type="datetime-local"
            />
            <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
              {{ t('kingdomP7B.reviewAdvisory') }}
            </p>
            <p v-if="form.errors.review_at" class="mt-1 text-sm text-red-300">
              {{ form.errors.review_at }}
            </p>
          </div>
          <div>
            <label class="text-sm font-semibold" for="diplomacy-expiry">{{
              t('kingdomP7B.expiryTime')
            }}</label>
            <input
              id="diplomacy-expiry"
              v-model="form.expires_at"
              class="ks-input mt-2 w-full"
              :disabled="tracking.state !== 'active' || !tracking.contextCurrent"
              type="datetime-local"
            />
            <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
              {{ t('kingdomP7B.expiryAdvisory') }}
            </p>
            <p v-if="form.errors.expires_at" class="mt-1 text-sm text-red-300">
              {{ form.errors.expires_at }}
            </p>
          </div>
          <div>
            <label class="text-sm font-semibold" for="diplomacy-terms">{{
              t('kingdomP7B.privateTerms')
            }}</label>
            <textarea
              id="diplomacy-terms"
              v-model="form.terms"
              class="ks-input mt-2 min-h-28 w-full"
              :disabled="tracking.state !== 'active' || !tracking.contextCurrent"
              maxlength="5000"
            />
            <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
              {{ t('kingdomP7B.privateTermsHelp') }}
            </p>
            <p v-if="form.errors.terms" class="mt-1 text-sm text-red-300">
              {{ form.errors.terms }}
            </p>
          </div>
          <div>
            <label class="text-sm font-semibold" for="diplomacy-rationale">{{
              t('kingdomP7B.privateRationale')
            }}</label>
            <textarea
              id="diplomacy-rationale"
              v-model="form.rationale"
              class="ks-input mt-2 min-h-28 w-full"
              :disabled="tracking.state !== 'active' || !tracking.contextCurrent"
              maxlength="5000"
            />
            <p class="mt-1 text-xs text-[var(--ks-text-muted)]">
              {{ t('kingdomP7B.privateRationaleHelp') }}
            </p>
            <p v-if="form.errors.rationale" class="mt-1 text-sm text-red-300">
              {{ form.errors.rationale }}
            </p>
          </div>
          <div class="md:col-span-2">
            <p v-if="diplomacyError()" class="mb-3 text-sm text-red-300">{{ diplomacyError() }}</p>
            <button
              class="rounded-[var(--ks-radius-sm)] bg-[var(--ks-blue)] px-4 py-2 font-semibold text-white disabled:opacity-50"
              :disabled="form.processing || tracking.state !== 'active' || !tracking.contextCurrent"
              type="submit"
            >
              {{ t('kingdomP7B.recordTransition') }}
            </button>
          </div>
        </form>
      </article>
    </section>

    <section class="ks-surface mt-6 p-5">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 class="ks-display text-xl font-semibold">{{ t('kingdomP7B.transitionHistory') }}</h2>
          <p class="mt-2 max-w-3xl text-sm text-[var(--ks-text-secondary)]">
            {{ t('kingdomP7B.transitionHistoryHelp') }}
          </p>
        </div>
        <p class="text-xs text-[var(--ks-text-muted)]">
          {{ t('kingdomP7B.historyLimit', { count: historyLimit }) }}
        </p>
      </div>

      <div v-if="history.length" class="mt-5 grid gap-3 lg:hidden">
        <article
          v-for="item in history"
          :key="item.id"
          class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/10 p-4"
        >
          <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="font-semibold">
              {{ stateLabel(item.fromState) }} → {{ stateLabel(item.toState) }}
            </p>
            <span class="text-xs text-[var(--ks-text-muted)]">{{ date(item.recordedAt) }}</span>
          </div>
          <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
            <div>
              <dt class="text-xs text-[var(--ks-text-muted)]">{{ t('kingdomP7B.effective') }}</dt>
              <dd>{{ date(item.effectiveAt) }}</dd>
            </div>
            <div>
              <dt class="text-xs text-[var(--ks-text-muted)]">{{ t('kingdomP7B.review') }}</dt>
              <dd>{{ date(item.reviewAt) }}</dd>
            </div>
            <div>
              <dt class="text-xs text-[var(--ks-text-muted)]">{{ t('kingdomP7B.expiry') }}</dt>
              <dd>{{ date(item.expiresAt) }}</dd>
            </div>
            <div>
              <dt class="text-xs text-[var(--ks-text-muted)]">{{ t('kingdomP7B.attribution') }}</dt>
              <dd>{{ item.actorName ?? t('kingdomP7B.unavailableActor') }}</dd>
            </div>
          </dl>
          <p
            v-if="item.terms"
            class="mt-3 text-sm whitespace-pre-wrap text-[var(--ks-text-secondary)]"
          >
            {{ item.terms }}
          </p>
          <p
            v-if="item.rationale"
            class="mt-2 text-sm whitespace-pre-wrap text-[var(--ks-text-muted)]"
          >
            {{ item.rationale }}
          </p>
        </article>
      </div>

      <div v-if="history.length" class="mt-5 hidden overflow-x-auto lg:block">
        <table class="min-w-full text-left text-sm">
          <caption class="sr-only">
            {{
              t('kingdomP7B.transitionHistory')
            }}
          </caption>
          <thead
            class="border-b border-[var(--ks-border)] text-xs font-semibold text-[var(--ks-text-muted)] uppercase"
          >
            <tr>
              <th class="px-3 py-3">{{ t('kingdomP7B.transition') }}</th>
              <th class="px-3 py-3">{{ t('kingdomP7B.effectiveReview') }}</th>
              <th class="px-3 py-3">{{ t('kingdomP7B.privateContext') }}</th>
              <th class="px-3 py-3">{{ t('kingdomP7B.attribution') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[var(--ks-border)]">
            <tr v-for="item in history" :key="item.id">
              <td class="px-3 py-4 font-semibold">
                {{ stateLabel(item.fromState) }} → {{ stateLabel(item.toState) }}
              </td>
              <td class="px-3 py-4 text-[var(--ks-text-secondary)]">
                <p>{{ date(item.effectiveAt) }}</p>
                <p class="mt-1 text-xs">{{ t('kingdomP7B.review') }} {{ date(item.reviewAt) }}</p>
                <p class="mt-1 text-xs">{{ t('kingdomP7B.expiry') }} {{ date(item.expiresAt) }}</p>
              </td>
              <td class="max-w-md px-3 py-4 text-[var(--ks-text-secondary)]">
                <p v-if="item.terms" class="whitespace-pre-wrap">{{ item.terms }}</p>
                <p
                  v-if="item.rationale"
                  class="mt-2 text-xs whitespace-pre-wrap text-[var(--ks-text-muted)]"
                >
                  {{ item.rationale }}
                </p>
                <span v-if="!item.terms && !item.rationale">—</span>
              </td>
              <td class="px-3 py-4 text-[var(--ks-text-secondary)]">
                <p>{{ item.actorName ?? t('kingdomP7B.unavailableActor') }}</p>
                <p class="mt-1 text-xs text-[var(--ks-text-muted)]">{{ date(item.recordedAt) }}</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p
        v-else
        class="mt-5 rounded-[var(--ks-radius-sm)] border border-dashed border-[var(--ks-border)] p-4 text-sm text-[var(--ks-text-muted)]"
      >
        {{ t('kingdomP7B.noHistory') }}
      </p>
    </section>
  </AppLayout>
</template>
