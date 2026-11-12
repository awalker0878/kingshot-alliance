import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import test from 'node:test';

const compiler = path.resolve('scripts/localization/compile-catalogues.mjs');

function compileFixture(invalid: boolean) {
  const root = mkdtempSync(path.join(tmpdir(), 'localization-compilation-'));
  const put = (file: string, value: unknown) => {
    mkdirSync(path.dirname(path.join(root, file)), { recursive: true });
    writeFileSync(path.join(root, file), typeof value === 'string' ? value : JSON.stringify(value));
  };
  const source = {
    corrected: 'Save',
    contextual: 'Open',
    recovered: 'Close',
    changed: 'New text',
    drafted: 'Cancel',
  };
  put('input/input.json', {
    baselineRef: 'immutable-fixture',
    locales: ['fr'],
    backend: ['Email'],
    accounts: ['Hello :name'],
  });
  put('input/effective.json', {
    account: {
      en: source,
      fr: { corrected: 'Conserver', contextual: 'Ouvert', recovered: 'Close', changed: 'New text' },
    },
  });
  put('input/base.json', {
    account: {
      en: { ...source, changed: 'Old text' },
      fr: {
        corrected: 'Ancienne correction',
        contextual: 'Ouvrir',
        recovered: 'Fermer',
        changed: 'Ancien texte',
      },
    },
  });
  put('resources/data/localization/reviewed.json', {
    Save: { fr: 'Sauvegarder' },
    Open: { fr: 'Ouvrir globalement' },
    Close: { fr: 'Clore' },
    'New text': { fr: 'Nouveau texte' },
    Email: { fr: 'Courriel' },
    'Hello :name': { fr: 'Bonjour :name' },
  });
  put('resources/data/localization/reviewed-keys.json', {
    'account:corrected': { fr: invalid ? 'Enregistrer {missing}' : 'Enregistrer' },
  });
  put('resources/data/localization/unchanged.json', { common: [], locales: {} });
  put('lang/en/accounts.php', "<?php return ['greeting' => 'Hello :name',];\n");
  put(
    'drafts/fr.jsonl',
    [...Object.values(source), 'Email', 'Hello :name']
      .map((en) => JSON.stringify({ en, translation: en === 'Cancel' ? 'Annuler' : 'Brouillon' }))
      .join('\n'),
  );
  put('resources/js/localization/messages/account/fr.ts', 'original UI sentinel');
  put('lang/fr.json', 'original server sentinel');
  put('lang/fr/accounts.php', 'original mail sentinel');
  const result = spawnSync(process.execPath, [compiler, 'input', 'drafts'], {
    cwd: root,
    encoding: 'utf8',
  });
  return { root, result, read: (file: string) => readFileSync(path.join(root, file), 'utf8') };
}

test('the real compiler preserves contextual corrections and recovers only unchanged sources', () => {
  const fixture = compileFixture(false);
  try {
    assert.equal(fixture.result.status, 0, fixture.result.stderr);
    const ui = fixture.read('resources/js/localization/messages/account/fr.ts');
    for (const [key, expected] of Object.entries({
      corrected: 'Enregistrer',
      contextual: 'Ouvert',
      recovered: 'Fermer',
      changed: 'Nouveau texte',
      drafted: 'Annuler',
    }))
      assert.ok(ui.includes(JSON.stringify(key) + ': ' + JSON.stringify(expected)), key);
    assert.deepEqual(JSON.parse(fixture.read('lang/fr.json')), { Email: 'Courriel' });
    assert.ok(fixture.read('lang/fr/accounts.php').includes('Bonjour :name'));
  } finally {
    rmSync(fixture.root, { recursive: true, force: true });
  }
});

test('a correction with invalid placeholders fails the whole compile without partial writes', () => {
  const fixture = compileFixture(true);
  try {
    assert.equal(fixture.result.status, 1);
    assert.match(fixture.result.stderr, /No application files changed/);
    const failures = JSON.parse(fixture.read('drafts/compile-report.json')).failures;
    assert.ok(
      failures.some(
        (failure: { key: string; reason: string }) =>
          failure.key === 'corrected' && failure.reason === 'parameters',
      ),
    );
    assert.equal(
      fixture.read('resources/js/localization/messages/account/fr.ts'),
      'original UI sentinel',
    );
    assert.equal(fixture.read('lang/fr.json'), 'original server sentinel');
    assert.equal(fixture.read('lang/fr/accounts.php'), 'original mail sentinel');
  } finally {
    rmSync(fixture.root, { recursive: true, force: true });
  }
});
