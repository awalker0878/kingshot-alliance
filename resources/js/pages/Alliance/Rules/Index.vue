<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import RoomBanner from '@/components/game/RoomBanner.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type RulesDocument = {
  id: string;
  body: string;
  locale: string;
  updatedAt: string | null;
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { name: string; slug: string };
  canManageContent: boolean;
  rules: RulesDocument | null;
}>();

const { t, formatDate } = useLocale();
const form = useForm({
  body: props.rules?.body ?? '',
  locale: props.rules?.locale ?? 'en',
});

function save(): void {
  form.put('/alliance/rules', {
    preserveScroll: true,
    onSuccess: () => form.clearErrors(),
  });
}

function updated(value: string | null | undefined): string {
  return value ? formatDate(value, { dateStyle: 'long', timeStyle: 'short' }) : '';
}
</script>

<template>
  <Head :title="`${t('contentExperience.rulesTitle')} · ${alliance.name}`" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <RoomBanner
      :eyebrow="t('contentExperience.eyebrow')"
      :title="t('contentExperience.rulesTitle')"
      :subtitle="t('contentExperience.rulesSubtitle', { alliance: alliance.name })"
      image="/images/kingshot/v4/noticeboard.svg"
      compact
    >
      <template #actions>
        <Link href="/alliance/content" class="ks-command-link">
          ← {{ t('contentExperience.hubTitle') }}
        </Link>
      </template>
    </RoomBanner>

    <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(20rem,.65fr)]">
      <section class="ks-surface p-5 sm:p-7" aria-labelledby="alliance-rules-heading">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="ks-kicker">{{ t('contentExperience.rulesEyebrow') }}</p>
            <h1 id="alliance-rules-heading" class="ks-display mt-1 text-3xl font-semibold">
              {{ t('contentExperience.rulesTitle') }}
            </h1>
          </div>
          <span v-if="rules" class="ks-chip">{{ rules.locale }}</span>
        </div>

        <div
          v-if="rules"
          class="mt-6 text-sm leading-7 whitespace-pre-wrap text-[var(--ks-text-secondary)] sm:text-base"
        >
          {{ rules.body }}
        </div>
        <div v-else class="ks-fantasy-empty mt-6">
          {{ t('contentExperience.rulesEmpty') }}
        </div>

        <p v-if="rules?.updatedAt" class="mt-5 text-xs text-[var(--ks-muted)]">
          {{ t('contentExperience.rulesUpdated', { date: updated(rules.updatedAt) }) }}
        </p>
      </section>

      <aside
        v-if="canManageContent"
        class="ks-surface p-5 sm:p-6"
        aria-labelledby="edit-rules-heading"
      >
        <p class="ks-kicker">{{ t('contentExperience.rulesManageEyebrow') }}</p>
        <h2 id="edit-rules-heading" class="ks-display mt-1 text-2xl font-semibold">
          {{ rules ? t('contentExperience.rulesEdit') : t('contentExperience.rulesAdd') }}
        </h2>
        <p class="mt-2 text-sm leading-6 text-[var(--ks-muted)]">
          {{ t('contentExperience.rulesHelp') }}
        </p>

        <form class="mt-5 space-y-4" :aria-busy="form.processing" @submit.prevent="save">
          <div>
            <label for="rules-locale" class="text-xs font-semibold">
              {{ t('contentExperience.locale') }}
            </label>
            <input
              id="rules-locale"
              v-model="form.locale"
              class="ks-input mt-1.5"
              maxlength="16"
              autocomplete="off"
              :aria-invalid="form.errors.locale ? 'true' : 'false'"
              :aria-describedby="form.errors.locale ? 'rules-locale-error' : undefined"
            />
            <p
              v-if="form.errors.locale"
              id="rules-locale-error"
              class="mt-1 text-sm text-[var(--ks-danger)]"
              role="alert"
            >
              {{ form.errors.locale }}
            </p>
          </div>

          <div>
            <label for="rules-body" class="text-xs font-semibold">
              {{ t('contentExperience.rulesBody') }}
            </label>
            <textarea
              id="rules-body"
              v-model="form.body"
              class="ks-input mt-1.5 min-h-64 resize-y"
              maxlength="10000"
              :placeholder="t('contentExperience.rulesPlaceholder')"
              :aria-invalid="form.errors.body ? 'true' : 'false'"
              :aria-describedby="
                form.errors.body ? 'rules-body-error rules-body-help' : 'rules-body-help'
              "
            />
            <div
              id="rules-body-help"
              class="mt-1 flex justify-between gap-3 text-xs text-[var(--ks-muted)]"
            >
              <span>{{ t('contentExperience.rulesBodyHelp') }}</span>
              <span>{{ form.body.length }}/10000</span>
            </div>
            <p
              v-if="form.errors.body"
              id="rules-body-error"
              class="mt-1 text-sm text-[var(--ks-danger)]"
              role="alert"
            >
              {{ form.errors.body }}
            </p>
          </div>

          <button
            type="submit"
            class="ks-command-button w-full justify-center"
            :disabled="form.processing"
          >
            {{
              form.processing
                ? t('contentExperience.rulesSaving')
                : t('contentExperience.rulesSave')
            }}
          </button>
        </form>
      </aside>
    </div>
  </AppLayout>
</template>
