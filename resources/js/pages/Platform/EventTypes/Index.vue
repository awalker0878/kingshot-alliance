<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import RoomBanner from '@/components/game/RoomBanner.vue';
import StatSeal from '@/components/game/StatSeal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type EventProfile = {
  id: string;
  canonical_key: string;
  name_key: string;
  verification_state: 'candidate' | 'verified' | 'conflicting' | 'unsupported';
  profile_state: 'disabled' | 'enabled';
  profile_enabled: boolean;
  source: {
    label: string;
    reference: string;
    observed_at: string | null;
    game_version_boundary: string | null;
  } | null;
  workflow_dimensions: string[];
};

type ScopeRow = {
  id: string;
  scope: 'player' | 'alliance' | 'kingdom';
  active: boolean;
  viewPermission: string;
  createPermission: string;
  managePermission: string;
};

type EventTypeRow = {
  id: string;
  slug: string;
  nameKey: string;
  descriptionKey: string | null;
  category: string;
  iconKey: string | null;
  active: boolean;
  system: boolean;
  profile: EventProfile;
  scopes: ScopeRow[];
};

const props = defineProps<{
  user: { name: string; email: string };
  eventTypes: EventTypeRow[];
  scopeOptions: string[];
  verificationStateOptions: string[];
  workflowDimensionOptions: string[];
}>();

const { t } = useLocale();

const verifiedCount = computed(
  () => props.eventTypes.filter((type) => type.profile.verification_state === 'verified').length,
);
const enabledCount = computed(
  () => props.eventTypes.filter((type) => type.profile.profile_enabled).length,
);
const candidateCount = computed(
  () => props.eventTypes.filter((type) => type.profile.verification_state === 'candidate').length,
);

function scopeLabel(scope: string): string {
  return t(`events.scope.${scope}`);
}

function readable(value: string): string {
  return t(`events.profile.values.${value}`);
}
</script>

<template>
  <Head :title="t('events.catalogue.title')" />
  <AppLayout :user="props.user">
    <RoomBanner
      :eyebrow="t('events.catalogue.eyebrow')"
      :title="t('events.catalogue.title')"
      :subtitle="t('events.catalogue.description')"
      image="/images/kingshot/v4/event-command.svg"
      compact
    >
      <template #actions>
        <Link href="/platform" class="ks-command-link" data-variant="secondary">
          ← {{ t('events.catalogue.back') }}
        </Link>
      </template>
    </RoomBanner>

    <section
      class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
      :aria-label="t('events.catalogue.title')"
    >
      <StatSeal :label="t('events.catalogue.title')" :value="props.eventTypes.length" icon="✦" />
      <StatSeal
        :label="t('events.profile.verifiedIdentities')"
        :value="verifiedCount"
        icon="✓"
        tone="teal"
      />
      <StatSeal
        :label="t('events.profile.enabledProfiles')"
        :value="enabledCount"
        icon="◆"
        tone="stone"
      />
      <StatSeal :label="t('events.profile.candidateIdentities')" :value="candidateCount" icon="?" />
    </section>

    <div class="mt-5 space-y-5">
      <article v-for="type in props.eventTypes" :key="type.id" class="ks-surface-gold p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="text-xl font-semibold">{{ t(type.nameKey) }}</h2>
              <span class="ks-chip">{{ readable(type.category) }}</span>
              <span class="ks-chip">{{ readable(type.profile.verification_state) }}</span>
              <span class="ks-chip">{{ readable(type.profile.profile_state) }}</span>
            </div>
            <p
              v-if="type.descriptionKey"
              class="mt-2 max-w-3xl text-sm text-[var(--ks-text-muted)]"
            >
              {{ t(type.descriptionKey) }}
            </p>
            <p
              v-if="!type.profile.profile_enabled"
              class="mt-3 text-sm text-[var(--ks-text-muted)]"
              role="status"
            >
              {{ t('events.profile.disabledExplanation') }}
            </p>
          </div>
          <code class="text-xs text-[var(--ks-text-muted)]">{{ type.slug }}</code>
        </div>

        <div v-if="type.profile.source" class="ks-surface mt-4 p-4 text-sm">
          <div class="font-semibold">{{ t('events.profile.sourceProvenance') }}</div>
          <dl class="mt-2 grid gap-2 sm:grid-cols-2">
            <div>
              <dt class="text-xs text-[var(--ks-text-muted)]">{{ t('events.profile.source') }}</dt>
              <dd>{{ type.profile.source.label }}</dd>
            </div>
            <div>
              <dt class="text-xs text-[var(--ks-text-muted)]">
                {{ t('events.profile.reference') }}
              </dt>
              <dd class="break-all">{{ type.profile.source.reference }}</dd>
            </div>
            <div>
              <dt class="text-xs text-[var(--ks-text-muted)]">
                {{ t('events.profile.observed') }}
              </dt>
              <dd>{{ type.profile.source.observed_at ?? t('events.profile.values.unknown') }}</dd>
            </div>
            <div>
              <dt class="text-xs text-[var(--ks-text-muted)]">
                {{ t('events.profile.gameVersionBoundary') }}
              </dt>
              <dd>
                {{
                  type.profile.source.game_version_boundary ?? t('events.profile.values.unknown')
                }}
              </dd>
            </div>
          </dl>
        </div>

        <section class="mt-4" :aria-label="t('events.profile.workflowDimensions')">
          <h3 class="text-sm font-semibold">{{ t('events.profile.workflowDimensions') }}</h3>
          <div v-if="type.profile.workflow_dimensions.length" class="mt-2 flex flex-wrap gap-2">
            <span
              v-for="dimension in type.profile.workflow_dimensions"
              :key="dimension"
              class="ks-chip"
            >
              {{ readable(dimension) }}
            </span>
          </div>
          <p v-else class="mt-2 text-sm text-[var(--ks-text-muted)]">
            {{ t('events.profile.noWorkflowDimensions') }}
          </p>
        </section>

        <section
          class="mt-4"
          :aria-label="t('events.profile.scopesFor', { event: t(type.nameKey) })"
        >
          <h3 class="text-sm font-semibold">{{ t('events.profile.applicationScopes') }}</h3>
          <div class="mt-2 grid gap-3 lg:grid-cols-3">
            <div v-for="scope in type.scopes" :key="scope.id" class="ks-surface p-3 text-sm">
              <div class="flex items-center justify-between gap-2">
                <strong>{{ scopeLabel(scope.scope) }}</strong>
                <span class="ks-chip">{{
                  scope.active
                    ? t('events.profile.values.active')
                    : t('events.profile.values.inactive')
                }}</span>
              </div>
              <dl class="mt-2 space-y-1 text-xs text-[var(--ks-text-muted)]">
                <div>
                  <dt class="inline font-semibold">{{ t('events.profile.view') }}:</dt>
                  <dd class="inline break-all">{{ scope.viewPermission }}</dd>
                </div>
                <div>
                  <dt class="inline font-semibold">{{ t('events.profile.create') }}:</dt>
                  <dd class="inline break-all">{{ scope.createPermission }}</dd>
                </div>
                <div>
                  <dt class="inline font-semibold">{{ t('events.profile.manage') }}:</dt>
                  <dd class="inline break-all">{{ scope.managePermission }}</dd>
                </div>
              </dl>
            </div>
          </div>
        </section>
      </article>
    </div>
  </AppLayout>
</template>
