<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import PublicLayout from '@/layouts/PublicLayout.vue';
import { useLocale } from '@/localization';

type Question = {
  id: string;
  prompt: string;
  helpText: string | null;
  type: 'short_text' | 'long_text' | 'select' | 'multi_select' | 'checkbox';
  options: string[];
  required: boolean;
};

const props = defineProps<{
  alliance: { name: string; slug: string; kingdom: string | null };
  application: {
    open: boolean;
    mode: string;
    title: string;
    introduction: string | null;
    token: string | null;
  };
  questions: Question[];
  attribution: { source: string | null };
  prefill: { name: string; email: string; emailLocked: boolean };
  submitted: boolean;
}>();

const { t } = useLocale();

const initialAnswers: Record<string, string | boolean | string[]> = {};
for (const question of props.questions) {
  initialAnswers[question.id] =
    question.type === 'multi_select' ? [] : question.type === 'checkbox' ? false : '';
}

const form = useForm({
  full_name: props.prefill.name,
  email: props.prefill.email,
  contact_handle: '',
  source: props.attribution.source ?? '',
  application_token: props.application.token,
  answers: initialAnswers,
});

const attributionLabels: Record<string, string> = {
  'recruitment-board': t('publicRecruitment.attributionBoard'),
  'alliance-public-page': t('publicRecruitment.attributionAlliancePage'),
  'alliance-share': t('publicRecruitment.attributionShareLink'),
  'bot-command': t('publicRecruitment.attributionBotCommand'),
};

function attributionLabel(source: string): string {
  return attributionLabels[source] ?? source.replaceAll('-', ' ');
}

function submit(): void {
  form.post(`/alliances/${props.alliance.slug}/apply`, {
    preserveScroll: true,
  });
}

function formError(key: string): string | undefined {
  return (form.errors as Record<string, string | undefined>)[key];
}

function answerError(questionId: string): string | undefined {
  return formError(`answers.${questionId}`);
}

function stringAnswer(questionId: string): string {
  const value = form.answers[questionId];
  return typeof value === 'string' ? value : '';
}

function setStringAnswer(questionId: string, event: Event): void {
  const target = event.target;
  if (
    target instanceof HTMLInputElement ||
    target instanceof HTMLTextAreaElement ||
    target instanceof HTMLSelectElement
  ) {
    form.answers[questionId] = target.value;
  }
}
</script>

<template>
  <Head :title="`${application.title} · ${alliance.name}`" />

  <PublicLayout>
    <section class="relative isolate overflow-hidden border-b border-[var(--ks-border)]">
      <img
        class="absolute inset-0 -z-20 h-full w-full object-cover opacity-42"
        src="/images/kingshot/v4/recruitment-hall.svg"
        alt=""
        aria-hidden="true"
      />
      <div
        class="absolute inset-0 -z-10 bg-[linear-gradient(90deg,rgba(4,8,9,.98),rgba(5,14,15,.88),rgba(5,11,20,.78))]"
      />
      <div class="mx-auto max-w-7xl px-5 py-10 sm:py-14 lg:px-8 lg:py-16">
        <Link
          class="inline-flex items-center gap-2 text-sm font-semibold text-[var(--ks-gold)] transition hover:text-[var(--ks-gold-strong)]"
          :href="`/alliances/${alliance.slug}`"
        >
          <span aria-hidden="true">←</span>
          {{ t('publicContent.backToAlliance') }} · {{ alliance.name }}
        </Link>

        <div class="mt-8 grid gap-8 lg:grid-cols-[0.82fr_1.18fr] lg:gap-12">
          <aside class="lg:pt-4">
            <p class="text-xs font-bold tracking-[0.2em] text-[var(--ks-gold)] uppercase">
              {{ t('navigation.recruitment') }}
            </p>
            <h1 class="ks-display mt-3 text-4xl font-semibold tracking-tight sm:text-5xl">
              {{ application.title }}
            </h1>
            <p
              v-if="alliance.kingdom"
              class="mt-4 text-sm font-semibold text-[var(--ks-text-secondary)]"
            >
              {{ t('publicAlliance.kingdom') }} {{ alliance.kingdom }}
            </p>
            <p
              v-if="application.introduction"
              class="mt-6 text-base leading-8 whitespace-pre-line text-[var(--ks-text-secondary)]"
            >
              {{ application.introduction }}
            </p>

            <div class="ks-surface mt-8 p-5">
              <p class="text-xs font-bold tracking-[0.16em] text-[var(--ks-text-muted)] uppercase">
                {{ t('publicAlliance.recruitment') }}
              </p>
              <p class="mt-2 text-lg font-bold">
                {{
                  application.open
                    ? t('publicAlliance.statusOpen')
                    : application.mode === 'invitation'
                      ? t('publicAlliance.statusInvitationOnly')
                      : t('publicAlliance.statusClosed')
                }}
              </p>
            </div>
          </aside>

          <div class="ks-surface-gold p-6 sm:p-8 lg:p-9">
            <div
              v-if="submitted"
              class="rounded-[var(--ks-radius-md)] border border-emerald-700/60 bg-emerald-950/30 p-5"
              role="status"
            >
              <h2 class="text-lg font-bold text-emerald-100">
                {{ t('publicRecruitment.receivedTitle') }}
              </h2>
              <p class="mt-2 leading-7 text-emerald-100/80">
                {{ t('publicRecruitment.receivedBody') }}
              </p>
            </div>

            <div
              v-else-if="!application.open"
              class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-bg)] p-5"
            >
              <h2 class="text-lg font-bold">{{ t('publicRecruitment.closedTitle') }}</h2>
              <p class="mt-2 leading-7 text-[var(--ks-text-muted)]">
                {{ t('publicRecruitment.closedBody') }}
              </p>
            </div>

            <form v-else class="space-y-7" @submit.prevent="submit">
              <div class="grid gap-5 sm:grid-cols-2">
                <div>
                  <label class="text-sm font-semibold" for="recruitment-name">{{
                    t('auth.register.name')
                  }}</label>
                  <input
                    id="recruitment-name"
                    v-model="form.full_name"
                    class="ks-input mt-2"
                    maxlength="160"
                    required
                    autocomplete="name"
                  />
                  <p v-if="form.errors.full_name" class="mt-2 text-sm text-rose-300" role="alert">
                    {{ form.errors.full_name }}
                  </p>
                </div>
                <div>
                  <label class="text-sm font-semibold" for="recruitment-email">{{
                    t('auth.register.email')
                  }}</label>
                  <input
                    id="recruitment-email"
                    v-model="form.email"
                    class="ks-input mt-2 disabled:cursor-not-allowed disabled:opacity-60"
                    type="email"
                    maxlength="320"
                    required
                    autocomplete="email"
                    :disabled="prefill.emailLocked"
                  />
                  <p v-if="form.errors.email" class="mt-2 text-sm text-rose-300" role="alert">
                    {{ form.errors.email }}
                  </p>
                </div>
              </div>

              <div class="grid gap-5 sm:grid-cols-2">
                <div>
                  <label class="text-sm font-semibold" for="recruitment-handle">
                    {{ t('publicRecruitment.contactHandle') }}
                  </label>
                  <input
                    id="recruitment-handle"
                    v-model="form.contact_handle"
                    class="ks-input mt-2"
                    maxlength="160"
                    :placeholder="t('publicRecruitment.optional')"
                  />
                </div>
                <div v-if="attribution.source">
                  <p class="text-sm font-semibold">{{ t('publicRecruitment.source') }}</p>
                  <div
                    class="mt-2 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-black/20 px-3.5 py-2.5 text-sm text-[var(--ks-text-secondary)]"
                  >
                    {{ attributionLabel(attribution.source) }}
                  </div>
                  <p class="mt-2 text-xs leading-5 text-[var(--ks-text-muted)]">
                    {{ t('publicRecruitment.attributionHelp') }}
                  </p>
                </div>
                <div v-else>
                  <label class="text-sm font-semibold" for="recruitment-source">
                    {{ t('publicRecruitment.source') }}
                  </label>
                  <input
                    id="recruitment-source"
                    v-model="form.source"
                    class="ks-input mt-2"
                    maxlength="120"
                    :placeholder="t('publicRecruitment.sourcePlaceholder')"
                  />
                </div>
              </div>

              <section
                v-if="questions.length"
                class="space-y-6 border-t border-[var(--ks-border)] pt-7"
                aria-labelledby="recruitment-questions-heading"
              >
                <h2 id="recruitment-questions-heading" class="ks-display text-2xl font-semibold">
                  {{ t('publicRecruitment.questions') }}
                </h2>

                <div v-for="question in questions" :key="question.id">
                  <fieldset
                    v-if="question.type === 'multi_select'"
                    class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[rgba(5,11,20,0.42)] p-4"
                  >
                    <legend class="px-1 font-semibold">
                      {{ question.prompt
                      }}<span v-if="question.required" aria-hidden="true"> *</span>
                    </legend>
                    <p v-if="question.helpText" class="mt-1 text-sm text-[var(--ks-text-muted)]">
                      {{ question.helpText }}
                    </p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                      <label
                        v-for="option in question.options"
                        :key="option"
                        class="flex items-center gap-3 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2.5 text-sm"
                      >
                        <input
                          v-model="form.answers[question.id]"
                          type="checkbox"
                          :value="option"
                        />
                        <span>{{ option }}</span>
                      </label>
                    </div>
                    <p
                      v-if="answerError(question.id)"
                      class="mt-2 text-sm text-rose-300"
                      role="alert"
                    >
                      {{ answerError(question.id) }}
                    </p>
                  </fieldset>

                  <label
                    v-else-if="question.type === 'checkbox'"
                    class="flex gap-3 rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[rgba(5,11,20,0.42)] p-4"
                  >
                    <input
                      v-model="form.answers[question.id]"
                      class="mt-1"
                      type="checkbox"
                      :required="question.required"
                    />
                    <span>
                      <span class="font-semibold">{{ question.prompt }}</span>
                      <span
                        v-if="question.helpText"
                        class="mt-1 block text-sm leading-6 text-[var(--ks-text-muted)]"
                      >
                        {{ question.helpText }}
                      </span>
                      <span
                        v-if="answerError(question.id)"
                        class="mt-1 block text-sm text-rose-300"
                        role="alert"
                      >
                        {{ answerError(question.id) }}
                      </span>
                    </span>
                  </label>

                  <div v-else>
                    <label class="font-semibold" :for="`question-${question.id}`">
                      {{ question.prompt
                      }}<span v-if="question.required" aria-hidden="true"> *</span>
                    </label>
                    <p
                      v-if="question.helpText"
                      class="mt-1 text-sm leading-6 text-[var(--ks-text-muted)]"
                    >
                      {{ question.helpText }}
                    </p>
                    <textarea
                      v-if="question.type === 'long_text'"
                      :id="`question-${question.id}`"
                      :value="stringAnswer(question.id)"
                      class="mt-2 min-h-32 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3.5 py-2.5 transition outline-none hover:border-[var(--ks-border-strong)] focus:border-[var(--ks-blue)]"
                      :required="question.required"
                      @input="setStringAnswer(question.id, $event)"
                    />
                    <select
                      v-else-if="question.type === 'select'"
                      :id="`question-${question.id}`"
                      :value="stringAnswer(question.id)"
                      class="mt-2 w-full rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3.5 py-2.5"
                      :required="question.required"
                      @change="setStringAnswer(question.id, $event)"
                    >
                      <option value="">{{ t('publicRecruitment.chooseOption') }}</option>
                      <option v-for="option in question.options" :key="option" :value="option">
                        {{ option }}
                      </option>
                    </select>
                    <input
                      v-else
                      :id="`question-${question.id}`"
                      :value="stringAnswer(question.id)"
                      class="ks-input mt-2"
                      :required="question.required"
                      @input="setStringAnswer(question.id, $event)"
                    />
                    <p
                      v-if="answerError(question.id)"
                      class="mt-2 text-sm text-rose-300"
                      role="alert"
                    >
                      {{ answerError(question.id) }}
                    </p>
                  </div>
                </div>
              </section>

              <p v-if="formError('application')" class="text-sm text-rose-300" role="alert">
                {{ formError('application') }}
              </p>
              <p v-if="formError('application_token')" class="text-sm text-rose-300" role="alert">
                {{ formError('application_token') }}
              </p>

              <button
                class="w-full rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-5 py-3 font-bold text-[var(--ks-ink)] transition hover:bg-[var(--ks-gold-strong)] disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
                type="submit"
                :disabled="form.processing"
              >
                {{
                  form.processing
                    ? t('publicRecruitment.submitting')
                    : t('publicRecruitment.submit')
                }}
              </button>
            </form>
          </div>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>
