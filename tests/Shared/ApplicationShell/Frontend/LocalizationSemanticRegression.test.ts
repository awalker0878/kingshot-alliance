import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import test from 'node:test';

const originals: Record<string, string> = {
  'ar-kingdom-max-four': 'يمكن أن تحتوي المملكة على أكثر من أربعة أشخاص لنفس الحساب.',
  'ar-plan-max-5000': 'ويمكن أن تحتوي الخطة على أكثر من 5000 من الأشياء المخططة.',
  'ar-blocker-summary': 'ويلزم تقديم موجز للمحول.',
  'ar-effective-admin': 'يجب على المملكة أن تحتفظ على الأقل بمملكة واحدة فعالة.',
};
const cases = JSON.parse(readFileSync('resources/data/localization/semantic-regressions.json', 'utf8')).cases as
  Array<{ id: string; locale: string; source: string; expected: string }>;
const corrected = JSON.parse(readFileSync('lang/ar.json', 'utf8')) as Record<string, string>;

test('all four original Arabic errors are caught independently by the production semantic checker', () => {
  const root = mkdtempSync(path.join(tmpdir(), 'kingshot-semantic-mutation-'));
  mkdirSync(path.join(root, 'lang'));
  try {
    for (const item of cases) {
      assert.equal(corrected[item.source], item.expected, item.id + ' corrected baseline');
      assert.ok(originals[item.id], item.id + ' original string provided');
      const modified = { ...corrected, [item.source]: originals[item.id] };
      writeFileSync(path.join(root, 'lang', 'ar.json'), JSON.stringify(modified));
      const result = spawnSync(process.execPath, ['scripts/check-localization-semantics.mjs'], {
        encoding: 'utf8',
        env: { ...process.env, LOCALIZATION_SEMANTIC_LANG_ROOT: path.join(root, 'lang') },
      });
      assert.notEqual(result.status, 0, item.id + ' unexpectedly passed with its original faulty text');
      assert.match(result.stderr, new RegExp(item.id), item.id + ' should be reported in failure diagnostics');
    }
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});
