import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const fixtures = JSON.parse(
  fs.readFileSync('resources/data/localization/semantic-regressions.json', 'utf8'),
);
const corrections = JSON.parse(fs.readFileSync(process.env.LOCALIZATION_SEMANTIC_REVIEWED_PATH ?? 'resources/data/localization/reviewed.json', 'utf8'));
const localeRoot = process.env.LOCALIZATION_SEMANTIC_LANG_ROOT ?? 'lang';
const failures = [];
let checked = 0;

for (const fixture of fixtures.cases) {
  if (fixture.catalogue !== 'server' || fixture.domain !== 'json') {
    failures.push('Unsupported regression fixture: ' + JSON.stringify(fixture));
    continue;
  }
  const file = path.join(localeRoot, fixture.locale + '.json');
  const catalogue = JSON.parse(fs.readFileSync(file, 'utf8'));
  const actual = catalogue[fixture.source];
  const preserved = corrections[fixture.source]?.[fixture.locale];
  checked++;
  if (actual !== fixture.expected) {
    failures.push(file + ': incorrect ' + fixture.id + '; expected approved correction');
  }
  if (preserved !== fixture.expected) {
    failures.push('reviewed.json: missing or stale correction for ' + fixture.id);
  }
}
if (failures.length) {
  console.error(failures.join('\n'));
  process.exitCode = 1;
} else {
  console.log('Semantic regression fixtures: ' + checked + ' approved server-language corrections retained.');
}
