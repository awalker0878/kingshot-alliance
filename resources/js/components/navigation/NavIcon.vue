<script setup lang="ts">
type NavIconName =
  | 'dashboard'
  | 'alliance'
  | 'events'
  | 'roster'
  | 'recruitment'
  | 'content'
  | 'contributions'
  | 'kingdom'
  | 'transfers'
  | 'giftCodes'
  | 'integrations'
  | 'profile'
  | 'logout'
  | 'menu'
  | 'close';

defineProps<{
  name: NavIconName;
}>();
</script>

<template>
  <svg
    aria-hidden="true"
    class="h-5 w-5 shrink-0"
    fill="none"
    viewBox="0 0 24 24"
    stroke="currentColor"
    stroke-width="1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
  >
    <template v-if="name === 'dashboard'">
      <path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z" />
    </template>
    <template v-else-if="name === 'alliance'">
      <path d="M12 3 19 6v5c0 4.5-2.9 7.8-7 10-4.1-2.2-7-5.5-7-10V6l7-3Z" />
      <path d="m9 12 2 2 4-4" />
    </template>
    <template v-else-if="name === 'events'">
      <path d="M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z" />
      <path d="M8 3v4M16 3v4M4 10h16M8 14h2M14 14h2M8 17h2" />
    </template>
    <template v-else-if="name === 'roster'">
      <path d="M16 20v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2" />
      <circle cx="9.5" cy="7" r="3" />
      <path d="M16 4.5a3 3 0 0 1 0 5.8M21 20v-2a4 4 0 0 0-3-3.65" />
    </template>
    <template v-else-if="name === 'recruitment'">
      <circle cx="9" cy="8" r="3" />
      <path d="M3 20v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2M18 8v6M15 11h6" />
    </template>
    <template v-else-if="name === 'content'">
      <path d="M5 4h10a3 3 0 0 1 3 3v13H8a3 3 0 0 1-3-3V4Z" />
      <path d="M8 8h7M8 12h7M8 16h4" />
    </template>
    <template v-else-if="name === 'contributions'">
      <path d="M12 3v18M5 8h14M7 8c0 4 2 7 5 9 3-2 5-5 5-9M8 4h8" />
    </template>
    <template v-else-if="name === 'kingdom'">
      <path d="M4 20h16M6 20V9l3 2 3-5 3 5 3-2v11M9 20v-4h6v4" />
      <path d="M7 6h2M15 6h2" />
    </template>
    <template v-else-if="name === 'transfers'">
      <path d="M7 7h12l-3-3M19 7l-3 3M17 17H5l3 3M5 17l3-3" />
    </template>
    <template v-else-if="name === 'giftCodes'">
      <path d="M4 10h16v10H4zM3 7h18v3H3zM12 7v13M12 7H8.5A2.5 2.5 0 1 1 11 4.5L12 7ZM12 7h3.5A2.5 2.5 0 1 0 13 4.5L12 7Z" />
    </template>
    <template v-else-if="name === 'integrations'">
      <path d="M8.5 15.5 15.5 8.5M7 12l-2 2a4 4 0 0 0 6 6l2-2M17 12l2-2a4 4 0 0 0-6-6l-2 2" />
    </template>
    <template v-else-if="name === 'profile'">
      <circle cx="12" cy="8" r="4" />
      <path d="M4 21a8 8 0 0 1 16 0" />
    </template>
    <template v-else-if="name === 'logout'">
      <path d="M10 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5M14 8l4 4-4 4M18 12H8" />
    </template>
    <template v-else-if="name === 'menu'">
      <path d="M4 7h16M4 12h16M4 17h16" />
    </template>
    <template v-else>
      <path d="M5 5l14 14M19 5 5 19" />
    </template>
  </svg>
</template>
