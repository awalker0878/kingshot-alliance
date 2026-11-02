import assert from 'node:assert/strict';
import { readFileSync, writeFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import ts from 'typescript';

const fixture = JSON.parse(readFileSync('resources/data/localization/locale-normalization-cases.json', 'utf8'));
const trace = JSON.parse(readFileSync('localization-pr4-http-candidate-diagnostics.json', 'utf8'));
const source = readFileSync('resources/js/localization/locales.ts', 'utf8');
const compiled = ts.transpileModule(source, {
  compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
}).outputText;
const exports = {};
runInNewContext(compiled, { exports });
const cases = fixture.realisticBrowserHeaders;
assert.equal(trace.realisticBrowserResults.length, cases.length, 'HTTP parser missing real browser fixtures');
const rows = cases.map((entry, index) => {
  const http = trace.realisticBrowserResults[index];
  assert.equal(http.header, entry.header, 'HTTP fixture order changed');
  const client = exports.selectFirstSupportedLocale(entry.navigatorLanguages);
  assert.equal(client, entry.expected, entry.header + ': browser preference mismatch');
  assert.equal(http.selected, entry.expected, entry.header + ': HTTP result mismatch');
  assert.equal(http.selected, client, entry.header + ': HTTP/client mismatch');
  return {
    header: entry.header,
    navigatorLanguages: entry.navigatorLanguages,
    serverCandidates: http.getLanguages,
    browser: client, server: http.selected,
    sameResult: true,
  };
});
const report = { count: rows.length, divergent: 0, scope: 'eight synthetic realistic browser fixtures, not production traffic', rows };
writeFileSync('localization-pr4-realistic-browser-parity.json', JSON.stringify(report, null, 2) + '\n');
console.log('PR4 REALISTIC BROWSER PARITY: ' + JSON.stringify({ matched: rows.length, divergent: 0, rows }));
