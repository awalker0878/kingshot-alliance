import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { runInNewContext } from 'node:vm';
import ts from 'typescript';

const baseline = 'a5151e717020cfe00a32aeddcca6279f9c7042fe';
const fixture = JSON.parse(readFileSync('resources/data/localization/locale-normalization-cases.json', 'utf8'));
const inputs = fixture.cases.map(({ input }) => input);
const phpFile = 'app/Shared/Infrastructure/Http/SupportedLocale.php';
const tsFile = 'resources/js/localization/locales.ts';
const fromGit = (file) => execFileSync('git', ['show', baseline + ':' + file], { encoding: 'utf8' });
const root = mkdtempSync(path.join(tmpdir(), 'kingshot-pr4-compat-'));

function tsResults(source) {
  const js = ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
  }).outputText;
  const exports = {};
  runInNewContext(js, { exports });
  return inputs.map((input) => exports.normalizeLocale(input));
}
function phpResults(file) {
  const program = String.raw`require $argv[1]; $inputs = json_decode(file_get_contents("php://stdin"), true, 512, JSON_THROW_ON_ERROR); $output = []; foreach ($inputs as $candidate) { $output[] = \App\Shared\Infrastructure\Http\SupportedLocale::normalize($candidate); } echo json_encode($output, JSON_THROW_ON_ERROR);`;
  const raw = execFileSync('php', ['-r', program, file], {
    input: JSON.stringify(inputs), encoding: 'utf8', maxBuffer: 1024 * 1024,
  });
  return JSON.parse(raw);
}
try {
  const originalPhpFile = path.join(root, 'SupportedLocale.php');
  writeFileSync(originalPhpFile, fromGit(phpFile));
  const oldTS = tsResults(fromGit(tsFile));
  const newTS = tsResults(readFileSync(tsFile, 'utf8'));
  const oldPHP = phpResults(originalPhpFile);
  const newPHP = phpResults(path.resolve(phpFile));
  const rows = inputs.map((input, i) => ({
    input, oldTS: oldTS[i], newTS: newTS[i],
    oldPHP: oldPHP[i], newPHP: newPHP[i],
    phpChanged: oldPHP[i] !== newPHP[i],
    tsChanged: oldTS[i] !== newTS[i],
    existingBaselineDivergence: oldTS[i] !== oldPHP[i],
    newDivergence: newTS[i] !== newPHP[i],
  }));
  for (let i = 0; i < rows.length; i++) {
    assert.equal(newTS[i], fixture.cases[i].expected, 'TS fixture ' + JSON.stringify(inputs[i]));
    assert.equal(newPHP[i], fixture.cases[i].expected, 'PHP fixture ' + JSON.stringify(inputs[i]));
    assert.equal(newTS[i], newPHP[i], 'cross-runtime difference for ' + JSON.stringify(inputs[i]));
  }
  const changes = rows.filter((row) => row.phpChanged || row.tsChanged);
  const report = {
    baseline,
    current: process.env.GITHUB_SHA ?? null,
    fixtureCount: rows.length,
    changedCases: changes,
    baselineDivergences: rows.filter((row) => row.existingBaselineDivergence),
    newDivergences: rows.filter((row) => row.newDivergence),
    notes: [
      'This is exhaustive for the enumerated fixture/probe inputs, not the infinite space of possible locale strings.',
      'PHP changes are an explicit compatibility decision and must not merge without domain-owner review.',
      'The client request-generation code, SupportedAllianceLocale and cookie/localStorage state are unchanged.',
    ],
  };
  writeFileSync('localization-pr4-compatibility-delta.json', JSON.stringify(report, null, 2) + '\n');
  console.log('PR4 COMPATIBILITY DELTA: ' + JSON.stringify({ fixtureCount: rows.length, changedCount: changes.length, changedCases: changes }));
  if (report.newDivergences.length) process.exitCode = 1;
} finally {
  rmSync(root, { recursive: true, force: true });
}
