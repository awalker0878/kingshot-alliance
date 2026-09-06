import type { DefineComponent } from 'vue';

const pages = import.meta.glob<DefineComponent>('./pages/**/*.vue', { import: 'default' });

export async function resolvePage(name: string): Promise<DefineComponent> {
  const page = pages[`./pages/${name}.vue`];
  if (!page) throw new Error(`Page not found: ${name}`);

  return page();
}
