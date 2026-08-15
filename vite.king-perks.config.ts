import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vite';

export default defineConfig({
  plugins: [vue()],
  build: {
    outDir: 'public/build-king-perks-check',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        manage: 'resources/js/pages/KingPerks/Manage.vue',
        my: 'resources/js/pages/KingPerks/My.vue',
      },
    },
  },
});
