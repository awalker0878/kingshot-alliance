<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

import ActionNotice from '@/components/ui/ActionNotice.vue';
import { useLocale } from '@/localization';

type Reaction = 'like' | 'dislike';

type ReactionSummary = {
  likes: number;
  dislikes: number;
  current: Reaction | null;
};

const props = defineProps<{
  contentId: string;
  reactions: ReactionSummary;
}>();

const { t, formatNumber } = useLocale();
const processing = ref(false);
const mutationError = ref<string | null>(null);

function toggle(reaction: Reaction): void {
  if (processing.value) return;

  mutationError.value = null;
  processing.value = true;
  const url = `/alliance/content/${props.contentId}/reaction`;
  const options = {
    preserveScroll: true,
    preserveState: true,
    onError: () => {
      mutationError.value = t('contentExperience.reactionFailed');
    },
    onFinish: () => {
      processing.value = false;
    },
  };

  if (props.reactions.current === reaction) {
    router.delete(url, options);
    return;
  }

  router.put(url, { reaction }, options);
}
</script>

<template>
  <div class="space-y-2">
    <div
      class="flex flex-wrap items-center gap-2"
      role="group"
      :aria-label="t('contentExperience.noticeReactions')"
      :aria-busy="processing"
    >
      <button
        type="button"
        class="ks-command-button min-h-10 min-w-20 gap-2"
        data-variant="secondary"
        :data-selected="reactions.current === 'like' ? 'true' : 'false'"
        :aria-pressed="reactions.current === 'like'"
        :aria-label="
          t(
            reactions.current === 'like'
              ? 'contentExperience.removeLikeCountLabel'
              : 'contentExperience.likeCountLabel',
            { count: reactions.likes },
          )
        "
        :disabled="processing"
        @click="toggle('like')"
      >
        <span aria-hidden="true">👍</span>
        <span>{{ t('contentExperience.like') }}</span>
        <strong>{{ formatNumber(reactions.likes) }}</strong>
        <span v-if="reactions.current === 'like'" aria-hidden="true">✓</span>
      </button>

      <button
        type="button"
        class="ks-command-button min-h-10 min-w-20 gap-2"
        data-variant="secondary"
        :data-selected="reactions.current === 'dislike' ? 'true' : 'false'"
        :aria-pressed="reactions.current === 'dislike'"
        :aria-label="
          t(
            reactions.current === 'dislike'
              ? 'contentExperience.removeDislikeCountLabel'
              : 'contentExperience.dislikeCountLabel',
            { count: reactions.dislikes },
          )
        "
        :disabled="processing"
        @click="toggle('dislike')"
      >
        <span aria-hidden="true">👎</span>
        <span>{{ t('contentExperience.dislike') }}</span>
        <strong>{{ formatNumber(reactions.dislikes) }}</strong>
        <span v-if="reactions.current === 'dislike'" aria-hidden="true">✓</span>
      </button>
    </div>

    <ActionNotice :message="mutationError" tone="danger" />
  </div>
</template>
