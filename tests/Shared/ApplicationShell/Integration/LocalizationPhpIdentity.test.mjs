import assert from 'node:assert/strict';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import test from 'node:test';
import { readPhpCatalogue } from '../../../../scripts/export-localization-php-keys.mjs';

test('PHP key identities survive reordering and duplicate wording without rebinding', () => {
  const root = mkdtempSync(path.join(tmpdir(), 'php-message-keys-'));
  const file = path.join(root, 'accounts.php');
  try {
    writeFileSync(
      file,
      "<?php return ['reset' => ['action' => 'Continue'], 'verify' => ['action' => 'Continue']];",
    );
    const before = readPhpCatalogue(file, 'accounts');
    writeFileSync(
      file,
      "<?php return ['verify' => ['action' => 'Continue'], 'reset' => ['action' => 'Continue']];",
    );
    assert.deepEqual(readPhpCatalogue(file, 'accounts'), before);
    writeFileSync(
      file,
      "<?php return ['verify' => ['action' => 'Verify now'], 'reset' => ['action' => 'Continue']];",
    );
    assert.deepEqual(readPhpCatalogue(file, 'accounts'), {
      'accounts.verify.action': 'Verify now',
      'accounts.reset.action': 'Continue',
    });
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});

test('PHP evaluates its own escaping and rejects dotted identity collisions', () => {
  const root = mkdtempSync(path.join(tmpdir(), 'php-message-keys-'));
  const file = path.join(root, 'accounts.php');
  try {
    writeFileSync(
      file,
      String.raw`<?php return ['mail' => ['body' => 'It\'s \\ safe', 'empty' => []]];`,
    );
    assert.deepEqual(readPhpCatalogue(file, 'accounts'), { 'accounts.mail.body': "It's \\ safe" });
    writeFileSync(file, "<?php return ['a.b' => 'one', 'a' => ['b' => 'two']];");
    assert.throws(() => readPhpCatalogue(file, 'accounts'), /Ambiguous PHP catalogue key/);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});
