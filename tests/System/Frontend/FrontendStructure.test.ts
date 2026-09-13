import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import test from 'node:test';
import { verifyFrontendStructure } from '../../../scripts/check-frontend-structure.mjs';

function fixture(change: (root: string, write: (file: string, text: string) => void) => void) {
  const root = mkdtempSync(path.join(tmpdir(), 'frontend-structure-'));
  const write = (file: string, text: string) => {
    const target = path.join(root, file);
    mkdirSync(path.dirname(target), { recursive: true });
    writeFileSync(target, text);
  };
  try {
    write('resources/js/pages/Dashboard/Home.vue', '<template>Home</template>');
    write('resources/js/pages/Platform/Index.vue', '<template>Platform administration</template>');
    write('app/Home.php', "<?php Inertia::render('Dashboard/Home');");
    write(
      'tsconfig.json',
      JSON.stringify({
        compilerOptions: {
          strict: true,
          noImplicitAny: true,
          noUncheckedIndexedAccess: true,
          exactOptionalPropertyTypes: true,
          noImplicitReturns: true,
        },
      }),
    );
    change(root, write);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
}

test('current Home and technical Platform destinations are valid presentation roots', () =>
  fixture((root) => {
    assert.deepEqual(verifyFrontendStructure(root), []);
  }));

test('a backend namespace cannot become a frontend page root', () =>
  fixture((root, write) => {
    write('resources/js/pages/ReadModels/Index.vue', '<template>Wrong owner</template>');
    assert.match(verifyFrontendStructure(root).join('\n'), /Unexpected Inertia page root/);
  }));

test('a controller cannot render a missing page or a file outside the page tree', () =>
  fixture((root, write) => {
    write('resources/js/private.vue', '<template>Private</template>');
    write(
      'app/Home.php',
      "<?php Inertia::render('../private'); Inertia::render('Dashboard/Missing');",
    );
    assert.equal(
      verifyFrontendStructure(root).filter((failure: string) => failure.includes('Inertia target'))
        .length,
      2,
    );
  }));

test('runtime artwork must exist and reference-only artwork cannot become a runtime dependency', () =>
  fixture((root, write) => {
    write(
      'resources/js/pages/Dashboard/Home.vue',
      '<template><img src="/images/kingshot/missing.svg" /></template>',
    );
    assert.equal(verifyFrontendStructure(root).length, 1);
    write('public/images/kingshot/missing.svg', '<svg/>');
    assert.deepEqual(verifyFrontendStructure(root), []);
    write('resources/js/art.ts', "export const art = 'docs/frontend/reference/concept.png';");
    assert.match(verifyFrontendStructure(root).join('\n'), /Reference artwork used at runtime/);
  }));

test('missing strict compiler guarantees cannot silently weaken the type gate', () =>
  fixture((root, write) => {
    write('tsconfig.json', JSON.stringify({ compilerOptions: { strict: false } }));
    assert.equal(verifyFrontendStructure(root).length, 5);
  }));
