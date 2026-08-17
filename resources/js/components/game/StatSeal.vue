<script setup lang="ts">
withDefaults(defineProps<{ label: string; value: string | number; icon?: string; tone?: 'gold' | 'teal' | 'stone' }>(), { icon: '◆', tone: 'gold' });
</script>
<template><div class="ks-stat-seal" :data-tone="tone"><span class="ks-stat-seal__icon">{{ icon }}</span><span><strong>{{ value }}</strong><small>{{ label }}</small></span></div></template>
