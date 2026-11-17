import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs/promises';
import path from 'node:path';
import { createServer } from 'vite';
import ts from 'typescript';
import { format, resolveConfig } from 'prettier';
import { flattenMessages } from './lib/localization-completeness.mjs';

// Freeze the actual last loader with these overlays. Never infer the old winners
// from current source, or rewrite the six immutable effective fingerprints.
const sourceRevision = '355d975d3b1f37e0decb3bf80803596c0570eb6d';
const write = process.argv.includes('--write');
const output = '.translation-pr3-current';
const root = await fs.mkdtemp(path.resolve('.translation-overlay-source-'));
const archive = execFileSync('git', ['archive', sourceRevision, 'resources/js/localization'], { maxBuffer: 64 * 1024 * 1024 });
execFileSync('tar', ['-x', '-C', root], { input: archive });
const old = await createServer({ configFile: false, root, server: { middlewareMode: true, hmr: false }, appType: 'custom' });
const current = await createServer({ configFile: false, server: { middlewareMode: true, hmr: false }, appType: 'custom' });
const overlays = [
  ['assistant-gameworld-extension', 'assistantGameWorldExtension', ['assistant']],
  ['assistant-transfer-labels', 'assistantTransferLabels', ['assistant']],
  ['event-command-labels', 'eventCommandLabels', ['events']],
  ['progression-planner-labels', 'progressionPlannerLabels', ['progression']],
  ['territory-reconciliation-labels', 'territoryReconciliationLabels', ['territory']],
  ['alliance-capability-expansion-labels', 'allianceCapabilityExpansionLabels', ['core', 'alliance']],
  ['governance-capability-expansion-labels', 'governanceCapabilityExpansionLabels', ['core', 'kingdom']],
  ['gift-code-workspace-labels', 'giftCodeWorkspaceLabels', ['core']],
  ['governor-lifecycle-labels', 'governorLifecycleLabels', ['core', 'account']],
  ['intelligence-change-labels', 'intelligenceChangeLabels', ['alliance', 'assistant', 'kingdom']],
];

function literal(node) {
  if (ts.isObjectLiteralExpression(node)) {
    return Object.fromEntries(node.properties.map(property => {
      assert.ok(ts.isPropertyAssignment(property), 'Only literal catalogue properties may be materialized');
      assert.ok(ts.isIdentifier(property.name) || ts.isStringLiteral(property.name));
      return [property.name.text, literal(property.initializer)];
    }));
  }
  assert.ok(ts.isStringLiteral(node) || ts.isNoSubstitutionTemplateLiteral(node));
  return node.text;
}

async function materialize(domain, before, after) {
  const file = `resources/js/localization/messages/${domain}/en.ts`;
  const source = await fs.readFile(file, 'utf8');
  const ast = ts.createSourceFile(file, source, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
  const declaration = ast.statements.filter(ts.isVariableStatement)
    .flatMap(statement => [...statement.declarationList.declarations])
    .find(item => item.name.getText(ast) === 'messages');
  assert.ok(declaration?.initializer, `Missing messages object in ${file}`);
  let node = declaration.initializer;
  while (ts.isSatisfiesExpression(node) || ts.isAsExpression(node)) node = node.expression;
  const value = literal(node);
  for (const [key, winner] of Object.entries(after)) {
    if (before[key] === winner) continue;
    const segments = key.split('.');
    let target = value;
    for (const segment of segments.slice(0, -1)) {
      if (typeof target[segment] !== 'object') target[segment] = {};
      target = target[segment];
    }
    target[segments.at(-1)] = winner;
  }
  const updated = source.slice(0, node.getStart(ast)) + JSON.stringify(value, null, 2) + source.slice(node.end);
  await fs.writeFile(file, await format(updated, { ...await resolveConfig(file), filepath: file }));
}

try {
  const registry = await old.ssrLoadModule('/resources/js/localization/registry.ts');
  const { localeCodes } = await old.ssrLoadModule('/resources/js/localization/locales.ts');
  const { loadCatalogue } = await old.ssrLoadModule('/resources/js/localization/loader.ts');
  const steps = [];
  for (const [file, name, domains] of overlays) {
    const module = await old.ssrLoadModule(`/resources/js/localization/${file}.ts`);
    steps.push({ file, name, domains, messages: flattenMessages(module[name]('en')) });
  }
  const report = { sourceRevision, scopes: [], summary: {} };
  for (const domain of registry.localizationDomains) {
    const before = flattenMessages((await registry.importDomainCatalogue(domain, 'en')).default);
    const effective = { ...before };
    for (const step of steps.filter(item => item.domains.includes(domain))) {
      const entries = Object.entries(step.messages).map(([key, winner]) => {
        const previous = effective[key] ?? null;
        const kind = previous === null ? 'added' : previous === winner ? 'identical' : 'overridden';
        effective[key] = winner;
        return { key, previous, winner, kind };
      });
      report.scopes.push({ domain, overlay: step.file, entries });
    }
    // This also guards the independently enumerated intermediate merge order.
    assert.deepEqual(effective, flattenMessages(await loadCatalogue(domain, 'en')), `${domain}: trace differs from actual historical loader`);
    if (write && steps.some(step => step.domains.includes(domain))) await materialize(domain, before, effective);
    if (!write) {
      for (const locale of localeCodes) {
        const actual = flattenMessages((await current.ssrLoadModule(`/resources/js/localization/messages/${domain}/${locale}.ts`)).default);
        const expected = locale === 'en' ? effective : flattenMessages((await registry.importDomainCatalogue(domain, locale)).default);
        assert.deepEqual(actual, expected, `${domain}/${locale}: canonical catalogue changed effective messages`);
      }
    }
  }
  for (const kind of ['added', 'identical', 'overridden']) report.summary[kind] = report.scopes.flatMap(scope => scope.entries).filter(entry => entry.kind === kind).length;
  if (!write) {
    const debt = JSON.parse(await fs.readFile('resources/data/localization/structural-wording-debt.json', 'utf8'));
    assert.equal(debt.sourceRevision, sourceRevision);
    for (const entry of debt.entries) {
      const historical = report.scopes.find(scope => scope.domain === entry.domain && scope.overlay === entry.previousOverlay)?.entries.find(item => item.key === entry.key);
      assert.equal(historical?.winner, entry.value, 'Wording debt must originate from the actual old overlay: ' + entry.key);
    }
  }
  await fs.mkdir(output, { recursive: true });
  await fs.writeFile(path.join(output, 'remaining-overlay-inventory.json'), JSON.stringify(report, null, 2) + '\n');
  console.log(`Remaining overlay ${write ? 'materialization' : 'parity'}: ${report.scopes.length} ordered domain applications; ${JSON.stringify(report.summary)}.`);
} finally {
  await Promise.all([old.close(), current.close()]);
  await fs.rm(root, { recursive: true, force: true });
}
