<script setup lang="ts">
import { computed } from 'vue';

import type { AllianceAnalysis, PlanAlliance, PlanObject } from '../engine/types';
import { useLocale } from '@/localization';

const props = defineProps<{
  alliances: PlanAlliance[];
  objects: PlanObject[];
  analysis: Record<string, AllianceAnalysis>;
}>();

const { t, formatNumber } = useLocale();
const objectByKey = computed(() => new Map(props.objects.map((object) => [object.key, object])));

function objectLabel(key: string): string {
  const object = objectByKey.value.get(key);
  if (!object) return key;
  return object.external_player_name || object.label || t(`territory.types.${object.type}`);
}
</script>

<template>
  <section class="ks-surface p-4" aria-labelledby="march-analysis-heading">
    <p class="ks-kicker">{{ t('territory.marchAnalysisEyebrow') }}</p>
    <h2 id="march-analysis-heading" class="ks-display mt-1 text-xl font-semibold">
      {{ t('territory.marchAnalysisTitle') }}
    </h2>
    <p class="mt-2 text-xs leading-5 text-[var(--ks-muted)]">
      {{ t('territory.marchAnalysisHelp') }}
    </p>

    <div v-for="alliance in alliances" :key="alliance.key" class="mt-4">
      <template v-if="analysis[alliance.key]?.marches.length">
        <h3 class="font-semibold">{{ alliance.display_name }}</h3>
        <div class="mt-2 overflow-x-auto">
          <table class="w-full min-w-[28rem] text-xs">
            <thead>
              <tr class="text-[var(--ks-muted)]">
                <th class="p-2 text-start">{{ t('territory.governor') }}</th>
                <th class="p-2 text-start">{{ t('territory.bearTrap') }}</th>
                <th class="p-2 text-end">{{ t('territory.distanceTiles') }}</th>
                <th class="p-2 text-end">{{ t('territory.estimatedMarch') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="march in analysis[alliance.key]?.marches ?? []"
                :key="march.city_key"
                class="border-t border-[var(--ks-border)]"
              >
                <td class="p-2">{{ objectLabel(march.city_key) }}</td>
                <td class="p-2">{{ objectLabel(march.trap_key) }}</td>
                <td class="p-2 text-end">{{ formatNumber(march.distance_tiles) }}</td>
                <td class="p-2 text-end">
                  {{
                    march.estimated_seconds === null
                      ? t('territory.noMarchAssumption')
                      : t('territory.secondsShort', {
                          seconds: formatNumber(march.estimated_seconds),
                        })
                  }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>

    <p
      v-if="!alliances.some((alliance) => analysis[alliance.key]?.marches.length)"
      class="mt-3 text-xs text-[var(--ks-muted)]"
    >
      {{ t('territory.noMarchRows') }}
    </p>
  </section>
</template>
