<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

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
  prefill: { name: string; email: string; emailLocked: boolean };
  submitted: boolean;
}>();

const initialAnswers: Record<string, string | boolean | string[]> = {};
for (const question of props.questions) {
  initialAnswers[question.id] =
    question.type === 'multi_select' ? [] : question.type === 'checkbox' ? false : '';
}

const form = useForm({
  full_name: props.prefill.name,
  email: props.prefill.email,
  contact_handle: '',
  source: '',
  application_token: props.application.token,
  answers: initialAnswers,
});

function submit(): void {
  form.post(`/alliances/${props.alliance.slug}/apply`, {
    preserveScroll: true,
  });
}

function answerError(questionId: string): string | undefined {
  return form.errors[`answers.${questionId}` as keyof typeof form.errors];
}
</script>

<template>
  <Head :title="`${application.title} · ${alliance.name}`" />

  <main class="min-h-screen bg-slate-950 text-slate-100">
    <div class="mx-auto max-w-3xl px-5 py-10 sm:px-6 lg:py-14">
      <Link
        class="text-sm font-semibold text-cyan-300 hover:text-cyan-200"
        :href="`/alliances/${alliance.slug}`"
      >
        ← {{ alliance.name }}
      </Link>

      <section class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/70 p-6 sm:p-8">
        <p class="text-sm font-semibold tracking-[0.18em] text-cyan-300 uppercase">Recruitment</p>
        <h1 class="mt-2 text-3xl font-bold sm:text-4xl">{{ application.title }}</h1>
        <p v-if="alliance.kingdom" class="mt-2 text-sm text-slate-400">
          Kingdom {{ alliance.kingdom }}
        </p>
        <p v-if="application.introduction" class="mt-5 whitespace-pre-line text-slate-300">
          {{ application.introduction }}
        </p>

        <div
          v-if="submitted"
          class="mt-6 rounded-xl border border-emerald-800 bg-emerald-950/30 p-5"
          role="status"
        >
          <h2 class="font-semibold text-emerald-100">Application received</h2>
          <p class="mt-1 text-sm text-emerald-200/80">
            Your application was submitted successfully. The alliance recruitment team can now
            review it.
          </p>
        </div>

        <div v-else-if="!application.open" class="mt-6 rounded-xl border border-slate-700 p-5">
          <h2 class="font-semibold">Applications are currently closed</h2>
          <p class="mt-2 text-sm text-slate-400">
            Check the alliance public page later for recruitment updates.
          </p>
        </div>

        <form v-else class="mt-8 space-y-6" @submit.prevent="submit">
          <div class="grid gap-5 sm:grid-cols-2">
            <div>
              <label class="text-sm font-medium" for="recruitment-name">Name</label>
              <input
                id="recruitment-name"
                v-model="form.full_name"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
                maxlength="160"
                required
                autocomplete="name"
              />
              <p v-if="form.errors.full_name" class="mt-1 text-sm text-rose-300" role="alert">
                {{ form.errors.full_name }}
              </p>
            </div>
            <div>
              <label class="text-sm font-medium" for="recruitment-email">Email</label>
              <input
                id="recruitment-email"
                v-model="form.email"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 disabled:text-slate-400"
                type="email"
                maxlength="320"
                required
                autocomplete="email"
                :disabled="prefill.emailLocked"
              />
              <p v-if="form.errors.email" class="mt-1 text-sm text-rose-300" role="alert">
                {{ form.errors.email }}
              </p>
            </div>
          </div>

          <div class="grid gap-5 sm:grid-cols-2">
            <div>
              <label class="text-sm font-medium" for="recruitment-handle"
                >Game/contact handle</label
              >
              <input
                id="recruitment-handle"
                v-model="form.contact_handle"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
                maxlength="160"
                placeholder="Optional"
              />
            </div>
            <div>
              <label class="text-sm font-medium" for="recruitment-source"
                >How did you hear about us?</label
              >
              <input
                id="recruitment-source"
                v-model="form.source"
                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
                maxlength="120"
                placeholder="Discord, referral, in-game, etc."
              />
            </div>
          </div>

          <section
            v-if="questions.length"
            class="space-y-6"
            aria-labelledby="recruitment-questions-heading"
          >
            <h2 id="recruitment-questions-heading" class="text-xl font-semibold">
              Application questions
            </h2>

            <div v-for="question in questions" :key="question.id">
              <fieldset
                v-if="question.type === 'multi_select'"
                class="rounded-xl border border-slate-800 p-4"
              >
                <legend class="px-1 font-medium">
                  {{ question.prompt }}<span v-if="question.required" aria-hidden="true"> *</span>
                </legend>
                <p v-if="question.helpText" class="mt-1 text-sm text-slate-400">
                  {{ question.helpText }}
                </p>
                <div class="mt-3 grid gap-2">
                  <label
                    v-for="option in question.options"
                    :key="option"
                    class="flex items-center gap-2 text-sm"
                  >
                    <input v-model="form.answers[question.id]" type="checkbox" :value="option" />
                    <span>{{ option }}</span>
                  </label>
                </div>
                <p v-if="answerError(question.id)" class="mt-2 text-sm text-rose-300" role="alert">
                  {{ answerError(question.id) }}
                </p>
              </fieldset>

              <label
                v-else-if="question.type === 'checkbox'"
                class="flex gap-3 rounded-xl border border-slate-800 p-4"
              >
                <input
                  v-model="form.answers[question.id]"
                  type="checkbox"
                  :required="question.required"
                />
                <span>
                  <span class="font-medium">{{ question.prompt }}</span>
                  <span v-if="question.helpText" class="mt-1 block text-sm text-slate-400">{{
                    question.helpText
                  }}</span>
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
                <label class="font-medium" :for="`question-${question.id}`">
                  {{ question.prompt }}<span v-if="question.required" aria-hidden="true"> *</span>
                </label>
                <p v-if="question.helpText" class="mt-1 text-sm text-slate-400">
                  {{ question.helpText }}
                </p>
                <textarea
                  v-if="question.type === 'long_text'"
                  :id="`question-${question.id}`"
                  v-model="form.answers[question.id]"
                  class="mt-2 min-h-32 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
                  :required="question.required"
                />
                <select
                  v-else-if="question.type === 'select'"
                  :id="`question-${question.id}`"
                  v-model="form.answers[question.id]"
                  class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
                  :required="question.required"
                >
                  <option value="">Choose an option</option>
                  <option v-for="option in question.options" :key="option" :value="option">
                    {{ option }}
                  </option>
                </select>
                <input
                  v-else
                  :id="`question-${question.id}`"
                  v-model="form.answers[question.id]"
                  class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"
                  :required="question.required"
                />
                <p v-if="answerError(question.id)" class="mt-1 text-sm text-rose-300" role="alert">
                  {{ answerError(question.id) }}
                </p>
              </div>
            </div>
          </section>

          <p v-if="form.errors.application" class="text-sm text-rose-300" role="alert">
            {{ form.errors.application }}
          </p>
          <p v-if="form.errors.application_token" class="text-sm text-rose-300" role="alert">
            {{ form.errors.application_token }}
          </p>

          <button
            class="rounded-lg bg-cyan-300 px-5 py-3 font-semibold text-slate-950 disabled:opacity-60"
            type="submit"
            :disabled="form.processing"
          >
            Submit application
          </button>
        </form>
      </section>
    </div>
  </main>
</template>
