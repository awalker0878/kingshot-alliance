import assert from 'node:assert/strict';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';

import { resolvePackFile } from '../../../../scripts/kingdom-map-art-pack.mjs';

test('artwork pack paths reject absolute syntax from every supported host family', () => {
  const root = mkdtempSync(join(tmpdir(), 'kingdom-map-portable-path-'));

  try {
    assert.throws(() => resolvePackFile(root, '/etc/passwd'), /must be relative/);
    assert.throws(() => resolvePackFile(root, 'C:/art/banner.png'), /must be relative/);
    assert.throws(() => resolvePackFile(root, 'C:\\art\\banner.png'), /must be relative/);
    assert.throws(() => resolvePackFile(root, '\\\\server\\share\\banner.png'), /must be relative/);
    assert.throws(() => resolvePackFile(root, '../banner.png'), /escapes the pack directory/);
    assert.equal(resolvePackFile(root, 'nested/banner.png'), join(root, 'nested/banner.png'));
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});
