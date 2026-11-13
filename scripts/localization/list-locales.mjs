import { readLocaleRegistry, translatedLocaleCodes } from './locale-registry.mjs';

const registry = readLocaleRegistry();
const codes = process.argv.includes('--translated')
  ? translatedLocaleCodes(registry)
  : registry.locales.map(({ code }) => code);
console.log(process.argv.includes('--lines') ? codes.join('\n') : JSON.stringify(codes));
