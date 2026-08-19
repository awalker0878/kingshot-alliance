import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vite';

export default defineConfig({
  plugins: [vue()],
  build: {
    outDir: 'public/build-king-perks-check',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        appointments: 'resources/js/pages/Kingdom/RoyalCourt/Appointments.vue',
        myAppointments: 'resources/js/pages/Kingdom/RoyalCourt/MyAppointments.vue',
      },
    },
  },
});
