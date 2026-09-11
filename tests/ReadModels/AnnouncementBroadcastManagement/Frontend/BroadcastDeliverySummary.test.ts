import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import test from 'node:test';

const messages = new URL(
  '../../../../resources/js/localization/messages/content/',
  import.meta.url,
);

test('every content locale labels the bounded retry selection separately from the total', async () => {
  const locales = readdirSync(messages).filter((name) => name.endsWith('.ts'));
  assert.equal(locales.length, 17);
  for (const file of locales) {
    const { default: catalogue } = await import(new URL(file, messages).href);
    const label = catalogue.contentExperience.retryCandidateSummary;
    assert.equal(typeof label, 'string', `Missing retry selection label in ${file}`);
    assert.deepEqual(
      [...label.matchAll(/\{([^}]+)\}/g)].map((match) => match[1]).sort(),
      ['selected', 'total'],
      `The ${file} label must retain both independent values`,
    );
  }
});

test('manager presents server totals separately from the concrete retry selection', () => {
  const source = readFileSync(
    new URL(
      '../../../../resources/js/components/content/AnnouncementRunHistory.vue',
      import.meta.url,
    ),
    'utf8',
  );
  const types = readFileSync(
    new URL('../../../../resources/js/features/alliance-content/types.ts', import.meta.url),
    'utf8',
  );
  assert.ok(types.includes('retryCandidateCount: number;'));
  assert.ok(source.includes('selected: run.failedDeliveryIds.length'));
  assert.ok(source.includes('total: run.retryCandidateCount'));
  assert.ok(source.includes("t('contentExperience.retryCandidateSummary'"));
  assert.ok(source.includes('v-if="run.retryCandidateCount > run.failedDeliveryIds.length"'));
  assert.ok(source.includes('sent: run.deliveryCounts.sent ?? 0'));
});
