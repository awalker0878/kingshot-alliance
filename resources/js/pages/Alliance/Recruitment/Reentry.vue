<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import RoomBanner from '@/components/game/RoomBanner.vue';
import AppButton from '@/components/ui/AppButton.vue';
import FormError from '@/components/ui/FormError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string };
  candidate: {
    id: string;
    name: string;
    email: string;
    stage: string;
    control: string;
    reason: string | null;
    reviewAt: string | null;
    setAt: string | null;
    blocking: boolean;
  };
  controls: string[];
}>();

const { t, formatDate } = useLocale();
const form = useForm({
  control: props.candidate.control,
  reason: props.candidate.reason ?? '',
  review_at: props.candidate.reviewAt?.slice(0, 16) ?? '',
});

function controlLabel(control: string): string {
  const key = `allianceExpansion.reentryControls.${control}`;
  const translated = t(key);
  return translated === key ? control.replaceAll('_', ' ') : translated;
}

function submit(): void {
  form.patch(`/alliance/recruitment/${props.candidate.id}/reentry`, { preserveScroll: true });
}
</script>

<template>
  <Head :title="`${t('allianceExpansion.recruitmentTitle')} · ${candidate.name}`" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <RoomBanner
      :eyebrow="t('allianceExpansion.recruitmentEyebrow')"
      :title="t('allianceExpansion.recruitmentTitle')"
      :subtitle="t('allianceExpansion.recruitmentSubtitle')"
      image="/images/kingshot/v4/recruitment-hall.svg"
      compact
    >
      <template #actions>
        <Link
          :href="`/alliance/recruitment/candidates/${candidate.id}`"
          class="ks-command-link"
          data-variant="secondary"
        >
          {{ t('navigation.recruitment') }}
        </Link>
        <Link href="/alliance/history" class="ks-command-link" data-variant="secondary">
          {{ t('allianceExpansion.navHistory') }}
        </Link>
      </template>
    </RoomBanner>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
      <form class="ks-surface p-5 sm:p-6" @submit.prevent="submit">
        <div>
          <label class="text-sm font-semibold" for="reentry-control">
            {{ t('allianceExpansion.reentryControl') }}
          </label>
          <select id="reentry-control" v-model="form.control" class="ks-input mt-2">
            <option v-for="control in controls" :key="control" :value="control">
              {{ controlLabel(control) }}
            </option>
          </select>
          <FormError :message="form.errors.control" />
        </div>

        <div class="mt-5">
          <label class="text-sm font-semibold" for="reentry-reason">
            {{ t('allianceExpansion.reason') }}
          </label>
          <textarea
            id="reentry-reason"
            v-model="form.reason"
            class="ks-input mt-2 min-h-32"
            maxlength="5000"
          />
          <FormError :message="form.errors.reason" />
        </div>

        <div class="mt-5">
          <label class="text-sm font-semibold" for="reentry-review-at">
            {{ t('allianceExpansion.reviewAt') }}
          </label>
          <input
            id="reentry-review-at"
            v-model="form.review_at"
            type="datetime-local"
            class="ks-input mt-2"
          />
          <FormError :message="form.errors.review_at" />
        </div>

        <div class="mt-6">
          <AppButton type="submit" :disabled="form.processing">
            {{ t('allianceExpansion.saveReentry') }}
          </AppButton>
        </div>
      </form>

      <aside class="ks-surface h-fit p-5">
        <p class="ks-kicker">{{ t('allianceExpansion.candidate') }}</p>
        <h2 class="ks-display mt-1 text-xl font-semibold">{{ candidate.name }}</h2>
        <p class="mt-1 text-sm text-[var(--ks-muted)]">{{ candidate.email }}</p>

        <dl class="mt-5 space-y-4 text-sm">
          <div>
            <dt class="text-[var(--ks-muted)]">{{ t('allianceExpansion.currentStage') }}</dt>
            <dd class="mt-1 font-semibold">{{ candidate.stage }}</dd>
          </div>
          <div>
            <dt class="text-[var(--ks-muted)]">{{ t('allianceExpansion.reentryControl') }}</dt>
            <dd class="mt-1 font-semibold">{{ controlLabel(candidate.control) }}</dd>
          </div>
          <div v-if="candidate.setAt">
            <dt class="text-[var(--ks-muted)]">{{ t('allianceExpansion.occurredAt') }}</dt>
            <dd class="mt-1">{{ formatDate(candidate.setAt) }}</dd>
          </div>
        </dl>

        <div class="mt-5">
          <span class="ks-status" :data-tone="candidate.blocking ? 'warning' : 'success'">
            {{
              candidate.blocking
                ? t('allianceExpansion.blocking')
                : t('allianceExpansion.notBlocking')
            }}
          </span>
        </div>
      </aside>
    </div>
  </AppLayout>
</template>
