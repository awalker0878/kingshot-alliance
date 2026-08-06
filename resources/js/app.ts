import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h, type DefineComponent } from 'vue';

const appName = import.meta.env.VITE_APP_NAME ?? 'Kingshot Alliance';

createInertiaApp({
  title: (title) => (title ? `${title} · ${appName}` : appName),
  resolve: (name) => {
    const pages = import.meta.glob<DefineComponent>('./pages/**/*.vue', {
      eager: true,
      import: 'default',
    });
    const page = pages[`./pages/${name}.vue`];

    if (!page) {
      throw new Error(`Page not found: ${name}`);
    }

    return page;
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) }).use(plugin).mount(el);
  },
  progress: {
    color: '#67e8f9',
  },
});
