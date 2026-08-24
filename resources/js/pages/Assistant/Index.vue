<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

type Classification = 'operational_fact' | 'game_fact' | 'alliance_strategy' | 'observation';
type SourceType = 'event' | 'roster' | 'alliance_content' | 'observation' | 'game_fact';
type AssistantStatus =
  | 'answered'
  | 'ambiguous'
  | 'not_found'
  | 'unsupported'
  | 'validation_error'
  | 'unavailable';
type AssistantPrompt = 'swordland_roster' | 'next_event' | 'bear_hunt_guide';
type PromptOption = { id: AssistantPrompt; label: string };

type Evidence = {
  id: string;
  sourceType: SourceType;
  sourceId: string;
  title: string;
  classification: Classification;
  statement: string;
  occurredAt: string | null;
  updatedAt: string | null;
  href: string | null;
  metadata: Record<string, boolean | number | string | null>;
};

type Citation = Omit<Evidence, 'statement' | 'metadata' | 'id'> & { evidenceId: string };
type Ambiguity = { title: string; startsAt: string; occurrenceId: string };

type AssistantResponse = {
  intent: string;
  status: AssistantStatus;
  messageKey: string;
  messageParameters: Record<string, boolean | number | string | null>;
  classifications: Classification[];
  evidence: Evidence[];
  citations: Citation[];
  ambiguity: Ambiguity[] | null;
  suggestedQuestions: string[];
};

type Turn = {
  id: number;
  question: string;
  response: AssistantResponse;
};

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { name: string };
  maxQuestionLength: number;
}>();

const { t, formatDate } = useLocale();
const question = ref('');
const busy = ref(false);
const turns = ref<Turn[]>([]);
const requestError = ref<string | null>(null);
let turnId = 0;

const defaultPromptIds: AssistantPrompt[] = ['swordland_roster', 'next_event', 'bear_hunt_guide'];

const canSubmit = computed(
  () =>
    !busy.value &&
    question.value.trim().length >= 2 &&
    question.value.length <= props.maxQuestionLength,
);

const suggestedQuestions = computed<PromptOption[]>(() => {
  const latest = turns.value.at(-1)?.response.suggestedQuestions ?? [];
  const ids = latest.filter(isPrompt);
  const prompts = ids.length > 0 ? ids : defaultPromptIds;

  return prompts.map((id) => ({ id, label: promptLabel(id) }));
});

function promptLabel(prompt: AssistantPrompt): string {
  if (prompt === 'swordland_roster') return t('assistant.prompts.swordland');
  if (prompt === 'next_event') return t('assistant.prompts.nextEvent');
  return t('assistant.prompts.bearGuide');
}

function isPrompt(value: string): value is AssistantPrompt {
  return defaultPromptIds.includes(value as AssistantPrompt);
}

function csrfToken(): string | null {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? null;
}

async function submit(prompt: AssistantPrompt | null = null): Promise<void> {
  const value = question.value.trim();
  if (value.length < 2 || value.length > props.maxQuestionLength || busy.value) return;

  busy.value = true;
  requestError.value = null;

  try {
    const response = await fetch('/assistant/ask', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken() ?? '',
      },
      body: JSON.stringify(prompt ? { question: value, prompt } : { question: value }),
    });

    if (response.redirected) {
      window.location.assign(response.url);
      return;
    }

    if (response.status === 429) {
      turns.value.push({
        id: ++turnId,
        question: value,
        response: syntheticResponse('unavailable', 'assistant.answers.rateLimited'),
      });
      question.value = '';
      return;
    }

    const body = (await response.json()) as AssistantResponse;
    if (!response.ok && !isAssistantResponse(body)) {
      throw new Error('Invalid Assistant response');
    }

    turns.value.push({ id: ++turnId, question: value, response: body });
    question.value = '';
  } catch {
    requestError.value = t('assistant.answers.unavailable');
  } finally {
    busy.value = false;
  }
}

function usePrompt(prompt: PromptOption): void {
  question.value = prompt.label;
  void submit(prompt.id);
}

function onQuestionKeydown(event: KeyboardEvent): void {
  if (event.key !== 'Enter' || event.shiftKey) return;
  event.preventDefault();
  void submit();
}

function isAssistantResponse(value: unknown): value is AssistantResponse {
  if (!value || typeof value !== 'object') return false;
  const candidate = value as Partial<AssistantResponse>;
  return typeof candidate.messageKey === 'string' && typeof candidate.status === 'string';
}

function syntheticResponse(status: AssistantStatus, messageKey: string): AssistantResponse {
  return {
    intent: 'unsupported',
    status,
    messageKey,
    messageParameters: {},
    classifications: [],
    evidence: [],
    citations: [],
    ambiguity: null,
    suggestedQuestions: [],
  };
}

function localizedParameters(response: AssistantResponse): Record<string, string | number> {
  const result: Record<string, string | number> = {};

  for (const [key, value] of Object.entries(response.messageParameters)) {
    if (value === null || typeof value === 'boolean') {
      result[key] = value === null ? t('assistant.notRecorded') : String(value);
      continue;
    }
    if (key === 'startsAt' && typeof value === 'string') {
      result[key] = formatDate(value, { dateStyle: 'medium', timeStyle: 'short' });
      continue;
    }
    result[key] = value;
  }

  return result;
}

function answerText(response: AssistantResponse): string {
  return t(response.messageKey, localizedParameters(response));
}

function classificationLabel(classification: Classification): string {
  return t(`assistant.classifications.${classification}`);
}

function sourceTypeLabel(sourceType: SourceType): string {
  return t(`assistant.sources.${sourceType}`);
}

function freshness(citation: Citation): string | null {
  const value = citation.updatedAt ?? citation.occurredAt;
  return value ? formatDate(value, { dateStyle: 'medium', timeStyle: 'short' }) : null;
}
</script>

<template>
  <Head :title="t('assistant.title')" />

  <AppLayout
    :user="props.user"
    :player-alliance-name="props.alliance.name"
    :has-player-alliance="true"
  >
    <main id="main-content" class="mx-auto w-full max-w-5xl px-4 py-6 sm:px-6 lg:py-10">
      <section
        class="overflow-hidden rounded-[var(--ks-radius-lg)] border border-[var(--ks-border)] bg-[var(--ks-panel)] shadow-2xl"
      >
        <div
          class="border-b border-[var(--ks-border)] bg-[linear-gradient(135deg,rgba(18,60,56,.92),rgba(6,18,18,.96))] px-5 py-6 sm:px-8 sm:py-8"
        >
          <p class="text-xs font-extrabold tracking-[.18em] text-[var(--ks-gold)] uppercase">
            {{ t('assistant.eyebrow') }}
          </p>
          <h1
            class="mt-2 text-3xl font-[var(--ks-font-display)] font-semibold text-[var(--ks-gold-bright)] sm:text-4xl"
          >
            {{ t('assistant.title') }}
          </h1>
          <p class="mt-3 max-w-3xl text-sm leading-6 text-[var(--ks-muted)] sm:text-base">
            {{ t('assistant.subtitle') }}
          </p>
          <p class="mt-3 text-xs font-semibold text-[var(--ks-text)]">
            {{ t('assistant.authorizationHint') }}
          </p>
        </div>

        <div class="space-y-6 p-4 sm:p-6 lg:p-8">
          <div
            v-if="turns.length === 0"
            class="rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-surface)] p-5"
          >
            <h2 class="text-lg font-bold text-[var(--ks-text)]">{{ t('assistant.tryAsking') }}</h2>
            <div class="mt-4 grid gap-2 sm:grid-cols-2">
              <button
                v-for="prompt in suggestedQuestions"
                :key="prompt.id"
                type="button"
                class="min-h-11 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-panel)] px-4 py-3 text-start text-sm font-semibold text-[var(--ks-text)] transition hover:border-[var(--ks-gold-dark)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--ks-gold)]"
                @click="usePrompt(prompt)"
              >
                {{ prompt.label }}
              </button>
            </div>
          </div>

          <ol v-else class="space-y-5" :aria-label="t('assistant.conversation')">
            <li v-for="turn in turns" :key="turn.id" class="space-y-3">
              <div
                class="ms-auto max-w-3xl rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[rgba(226,180,77,.08)] px-4 py-3"
              >
                <p class="text-xs font-extrabold tracking-[.12em] text-[var(--ks-gold)] uppercase">
                  {{ t('assistant.youAsked') }}
                </p>
                <p class="mt-1 text-sm whitespace-pre-wrap text-[var(--ks-text)]">
                  {{ turn.question }}
                </p>
              </div>

              <article
                class="max-w-4xl rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[var(--ks-surface)] p-4 sm:p-5"
              >
                <div class="flex flex-wrap items-center gap-2">
                  <span
                    v-for="classification in turn.response.classifications"
                    :key="classification"
                    class="rounded-full border border-[var(--ks-border)] bg-[var(--ks-panel)] px-2.5 py-1 text-xs font-bold text-[var(--ks-gold-bright)]"
                  >
                    {{ classificationLabel(classification) }}
                  </span>
                </div>

                <p class="mt-3 text-base leading-7 whitespace-pre-wrap text-[var(--ks-text)]">
                  {{ answerText(turn.response) }}
                </p>

                <div
                  v-if="turn.response.ambiguity?.length"
                  class="mt-4 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-panel)] p-3"
                >
                  <h3 class="text-sm font-bold text-[var(--ks-text)]">
                    {{ t('assistant.possibleEvents') }}
                  </h3>
                  <ul class="mt-2 space-y-2">
                    <li
                      v-for="candidate in turn.response.ambiguity"
                      :key="candidate.occurrenceId"
                      class="flex flex-wrap items-center justify-between gap-2 text-sm"
                    >
                      <span
                        >{{ candidate.title }} ·
                        {{
                          formatDate(candidate.startsAt, {
                            dateStyle: 'medium',
                            timeStyle: 'short',
                          })
                        }}</span
                      >
                      <Link
                        :href="`/events/${candidate.occurrenceId}`"
                        class="font-bold text-[var(--ks-gold-bright)] underline underline-offset-4"
                      >
                        {{ t('assistant.openEvent') }}
                      </Link>
                    </li>
                  </ul>
                </div>

                <section
                  v-if="turn.response.citations.length"
                  class="mt-5 border-t border-[var(--ks-border)] pt-4"
                  :aria-label="t('assistant.sourcesHeading')"
                >
                  <h3
                    class="text-xs font-extrabold tracking-[.15em] text-[var(--ks-muted)] uppercase"
                  >
                    {{ t('assistant.sourcesHeading') }}
                  </h3>
                  <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                    <li
                      v-for="citation in turn.response.citations"
                      :key="citation.evidenceId"
                      class="rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-panel)] p-3"
                    >
                      <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="font-extrabold text-[var(--ks-gold)]">{{
                          sourceTypeLabel(citation.sourceType)
                        }}</span>
                        <span class="text-[var(--ks-muted)]">{{
                          classificationLabel(citation.classification)
                        }}</span>
                      </div>
                      <component
                        :is="citation.href ? Link : 'span'"
                        :href="citation.href ?? undefined"
                        class="mt-1 block text-sm font-bold text-[var(--ks-text)]"
                        :class="
                          citation.href
                            ? 'underline decoration-[var(--ks-gold-dark)] underline-offset-4'
                            : ''
                        "
                      >
                        {{ citation.title }}
                      </component>
                      <p v-if="freshness(citation)" class="mt-1 text-xs text-[var(--ks-muted)]">
                        {{ t('assistant.sourceTime', { time: freshness(citation) ?? '' }) }}
                      </p>
                    </li>
                  </ul>
                </section>
              </article>
            </li>
          </ol>

          <p
            v-if="requestError"
            role="alert"
            class="rounded-[var(--ks-radius-sm)] border border-red-400/40 bg-red-950/30 px-4 py-3 text-sm font-semibold text-red-100"
          >
            {{ requestError }}
          </p>

          <form
            class="sticky bottom-3 rounded-[var(--ks-radius-md)] border border-[var(--ks-border)] bg-[rgba(5,14,14,.96)] p-3 shadow-2xl backdrop-blur"
            @submit.prevent="submit"
          >
            <label for="assistant-question" class="text-sm font-bold text-[var(--ks-text)]">{{
              t('assistant.questionLabel')
            }}</label>
            <textarea
              id="assistant-question"
              v-model="question"
              rows="3"
              :maxlength="props.maxQuestionLength"
              :placeholder="t('assistant.questionPlaceholder')"
              class="mt-2 w-full resize-y rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] bg-[var(--ks-surface)] px-3 py-3 text-base text-[var(--ks-text)] outline-none placeholder:text-[var(--ks-muted)] focus:border-[var(--ks-gold)] focus:ring-2 focus:ring-[var(--ks-gold-soft)]"
              :aria-describedby="requestError ? 'assistant-request-status' : undefined"
              @keydown="onQuestionKeydown"
            />
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
              <p class="text-xs text-[var(--ks-muted)]">
                {{
                  t('assistant.inputHint', { count: question.length, max: props.maxQuestionLength })
                }}
              </p>
              <button
                type="submit"
                :disabled="!canSubmit"
                class="min-h-11 rounded-[var(--ks-radius-sm)] bg-[var(--ks-gold)] px-5 py-2.5 text-sm font-extrabold text-[#181108] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--ks-gold-bright)] disabled:cursor-not-allowed disabled:opacity-40"
              >
                {{ busy ? t('assistant.asking') : t('assistant.ask') }}
              </button>
            </div>
            <p id="assistant-request-status" class="sr-only" aria-live="polite">
              {{ busy ? t('assistant.asking') : (requestError ?? '') }}
            </p>
          </form>
        </div>
      </section>
    </main>
  </AppLayout>
</template>
