import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const page = readFileSync('resources/js/pages/Operations/Events/Manage.vue', 'utf8');
const registry = readFileSync('resources/js/localization/registry.ts', 'utf8');
assert.match(page, /import \{ useLocale \} from ['"]@\/localization['"]/);
assert.doesNotMatch(page, /event-command-labels|eventCommandLabels/, 'Manage must not import the Event Command overlay directly');
assert.doesNotMatch(page, /localization\/messages\//, 'Manage must not import a catalogue directly');
assert.match(registry, /import\.meta\.glob<MessageModule>\('\.\/messages\/\*\/\*\.ts'\)/, 'Canonical catalogues must stay lazy');
assert.doesNotMatch(registry, /eager\s*:\s*true/, 'Do not eagerly bundle catalogues');
console.log('EVENT IMPORT GRAPH: Manage.vue uses useLocale; its canonical Event Command messages remain in lazy domain chunks.');
