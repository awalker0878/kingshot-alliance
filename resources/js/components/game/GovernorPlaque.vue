<script setup lang="ts">
import { useLocale } from '@/localization';

const { t } = useLocale();

defineProps<{
  name: string;
  rank?: string | null;
  alliance?: string | null;
  kingdom?: number | null;
}>();
</script>
<template>
  <div class="ks-governor-plaque">
    <div class="ks-governor-plaque__portrait">♞</div>
    <div>
      <small>{{ t('giftCodes.workspace.governor') }}</small
      ><strong>{{ name }}</strong
      ><span
        ><template v-if="rank">{{ rank }}</template
        ><template v-if="alliance"> · {{ alliance }}</template
        ><template v-if="kingdom"> · K{{ kingdom }}</template></span
      >
    </div>
  </div>
</template>
