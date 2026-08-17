<script setup lang="ts">defineProps<{ title?: string }>();</script>
<template><section class="ks-parchment"><header v-if="title" class="ks-parchment__header">{{ title }}</header><div class="ks-parchment__body"><slot /></div></section></template>
