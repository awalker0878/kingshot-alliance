import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const page = readFileSync('resources/js/pages/Operations/Events/Manage.vue', 'utf8');
const loader = readFileSync('resources/js/localization/loader.ts', 'utf8');
assert.match(page, /import \{ useLocale \} from ['"]@\/localization['"]/);
assert.doesNotMatch(page, /event-command-labels|eventCommandLabels/, 'Manage must not import the Event Command overlay directly');
assert.match(loader, /await import\(['"]\.\/event-command-labels['"]\)/, 'Event Command overlay must remain dynamically imported');
assert.doesNotMatch(loader, /import \{ eventCommandLabels \} from/, 'Do not make the Event Command overlay eager');
console.log('PR3 EVENT IMPORT GRAPH: Manage.vue imports useLocale; event-command-labels is a dynamic loader import. Largest-page Vite bytes are separately compared to baseline.');
