import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';
import ts from 'typescript';

const baseline = 'a5151e717020cfe00a32aeddcca6279f9c7042fe';
const readOld = (file) => execFileSync('git', ['show', baseline + ':' + file], { encoding: 'utf8' });

function getObject(source, variable) {
  const ast = ts.createSourceFile('catalogue.ts', source, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
  for (const stmt of ast.statements) {
    if (!ts.isVariableStatement(stmt)) continue;
    for (const dec of stmt.declarationList.declarations) {
      if (dec.name.getText(ast) !== variable) continue;
      let value = dec.initializer;
      while (value && (ts.isSatisfiesExpression(value) || ts.isAsExpression(value) || ts.isParenthesizedExpression(value))) value = value.expression;
      assert.ok(value && ts.isObjectLiteralExpression(value), 'expected object ' + variable);
      return value;
    }
  }
  throw new Error('missing object ' + variable);
}
function flatten(source, variable) {
  const object = getObject(source, variable);
  const leaves = {};
  const visit = (node, path = '') => {
    for (const item of node.properties) {
      assert.ok(ts.isPropertyAssignment(item), 'cannot silently ignore spread/computed overlay');
      const name = item.name;
      assert.ok(ts.isIdentifier(name) || ts.isStringLiteral(name) || ts.isNumericLiteral(name), 'nonliteral key');
      const key = path ? path + '.' + name.text : name.text;
      let value = item.initializer;
      while (ts.isSatisfiesExpression(value) || ts.isAsExpression(value)) value = value.expression;
      if (ts.isObjectLiteralExpression(value)) visit(value, key);
      else {
        assert.ok(ts.isStringLiteral(value) || ts.isNoSubstitutionTemplateLiteral(value), 'nonliteral message ' + key);
        leaves[key] = value.text;
      }
    }
  };
  visit(object);
  return leaves;
}
const transfer = 'resources/js/localization/transfer-evidence-labels.ts';
const english = 'resources/js/localization/messages/transfers/en.ts';
const originalBase = flatten(readOld(english), 'messages');
const originalOverlay = flatten(readOld(transfer), 'english');
const currentSource = readFileSync(english, 'utf8');
const movedOverlay = flatten(currentSource, 'transferEvidence');

const before = JSON.parse(readFileSync('.translation-pr1-baseline/effective-all-locales.json', 'utf8')).transfers.en;
const after = JSON.parse(readFileSync('.translation-pr3-current/effective-all-locales.json', 'utf8')).transfers.en;
const conflictsReport = JSON.parse(readFileSync('.translation-pr1-baseline/overlay-conflicts.json', 'utf8'));
const keys = Object.keys(originalOverlay);
assert.deepEqual(movedOverlay, originalOverlay, 'moved overlay keys/values must be identical');
const collisions = [];
let overridden = 0;
let sameValue = 0;
let added = 0;
for (const key of keys) {
  const hasBase = Object.hasOwn(originalBase, key);
  if (hasBase) {
    if (originalBase[key] === originalOverlay[key]) sameValue++;
    else overridden++;
    collisions.push({ key, originalBase: originalBase[key], preservedWinner: originalOverlay[key], kind: originalBase[key] === originalOverlay[key] ? 'same-value-overlap' : 'overridden' });
  } else added++;
  assert.equal(before[key], originalOverlay[key], 'original effective winner of ' + key);
  assert.equal(after[key], originalOverlay[key], 'new effective value of ' + key);
}
assert.deepEqual(Object.keys(after).sort(), Object.keys(before).sort(), 'all effective key ownership remains unchanged');
const reported = conflictsReport.mergesOrAdditions.filter((item) => item.domain === 'transfers');
assert.equal(reported.filter((item) => item.kind === 'overridden').length, overridden, 'reported changed-value collisions');
assert.equal(reported.filter((item) => item.kind === 'overlay-added').length, added, 'reported overlay additions');
const receiptKeys = keys.filter((key) => key.startsWith('receipts.')).sort();
assert.ok(receiptKeys.length, 'test must check actual receipt messages');
const movedReceipts = Object.keys(movedOverlay).filter((key) => key.startsWith('receipts.')).sort();
assert.deepEqual(movedReceipts, receiptKeys, 'receipt coverage has identical exact keys');
const evidence = {
  source: baseline, domain: 'transfers', overlay: transfer,
  overlayLeafCount: keys.length, collisionCount: collisions.length,
  changedWinnerCollisionCount: overridden, identicalValueCollisionCount: sameValue,
  overlayAdditionCount: added, baselineOverlayConflictReportCount: reported.length,
  receiptCodeCount: receiptKeys.length, receiptKeys,
  collisions, preservedEffectiveSha: 'see scripts/check-localization-pr3-parity.mjs',
};
writeFileSync('.translation-pr3-current/transfer-overlay-overlap.json', JSON.stringify(evidence, null, 2) + '\n');
console.log('PR3 OVERLAY COLLISIONS: ' + JSON.stringify({
  leafCount: keys.length, collisions: collisions.length, changedWinnerCollisions: overridden,
  sameValueCollisions: sameValue, added, receipts: receiptKeys.length, reported: reported.length,
}));
