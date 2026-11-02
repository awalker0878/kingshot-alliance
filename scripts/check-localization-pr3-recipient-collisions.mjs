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
const overlayFile = 'resources/js/localization/communications-recipient-delivery-labels.ts';
const targetFile = 'resources/js/localization/messages/account/en.ts';
const originalBase = flatten(readOld(targetFile), 'messages');
const originalOverlay = flatten(readOld(overlayFile), 'english');
const movedOverlay = flatten(readFileSync(targetFile, 'utf8'), 'notificationOverlay');
assert.deepEqual(movedOverlay, originalOverlay, 'exact notification keys and source values preserved');

const before = JSON.parse(readFileSync('.translation-pr1-baseline/effective-all-locales.json', 'utf8')).account.en;
const after = JSON.parse(readFileSync('.translation-pr3-current/effective-all-locales.json', 'utf8')).account.en;
const conflicts = JSON.parse(readFileSync('.translation-pr1-baseline/overlay-conflicts.json', 'utf8')).mergesOrAdditions;
const reported = conflicts.filter((x) => x.domain === 'account' && x.key.startsWith('notifications.'));
const keys = Object.keys(originalOverlay);
const collisions = [];
let overridden = 0, sameValue = 0, added = 0;
for (const key of keys) {
  const exists = Object.hasOwn(originalBase, key);
  if (exists) {
    const oldValue = originalBase[key];
    const winner = originalOverlay[key];
    if (oldValue === winner) sameValue++;
    else overridden++;
    collisions.push({ key, originalBase: oldValue, preservedWinner: winner, kind: oldValue === winner ? 'same-value' : 'overridden' });
  } else added++;
  assert.equal(before[key], originalOverlay[key], 'baseline runtime winner of ' + key);
  assert.equal(after[key], originalOverlay[key], 'post-move runtime winner of ' + key);
}
assert.deepEqual(Object.keys(after).sort(), Object.keys(before).sort(), 'full account effective keyspace unchanged');
assert.ok(overridden > 0, 'this slice MUST exercise different-value collisions');
assert.equal(reported.filter((x) => x.kind === 'overridden').length, overridden, 'baseline collision count and ownership');
assert.equal(reported.filter((x) => x.kind === 'overlay-added').length, added, 'baseline overlay additions');
const example = collisions.find((x) => x.key === 'notifications.eyebrow');
assert.deepEqual(example, {
  key: 'notifications.eyebrow',
  originalBase: 'Governor notifications',
  preservedWinner: 'Recipient delivery',
  kind: 'overridden',
});
const report = {
  baseline, domain: 'account', overlayFile, targetFile,
  leafCount: keys.length,
  overridden, sameValue, added,
  collisionCount: collisions.length,
  changedExample: example,
  collisions,
};
writeFileSync('.translation-pr3-current/recipient-overlay-overlap.json', JSON.stringify(report, null, 2) + '\n');
console.log('PR3 RECIPIENT DIFFERING COLLISIONS: ' + JSON.stringify({
  leafCount: keys.length, overridden, sameValue, added,
  key: example.key, original: example.originalBase, winner: example.preservedWinner,
}));

const review = readFileSync('docs/frontend/localization-pr3-recipient-collision-winners.md', 'utf8');
const expectedRows = collisions.filter((x) => x.kind === 'overridden')
  .map((x) => '| \`' + x.key + '\` | ' + x.originalBase + ' | ' + x.preservedWinner + ' |');
const publishedRows = review.split('\n').filter((line) => line.startsWith('| \`notifications.'));
assert.equal(expectedRows.length, 41, 'all changed-value collisions should be reviewed');
assert.deepEqual(publishedRows, expectedRows, 'committed reviewer inventory must equal the actual 41 baseline winners in runtime merge order');
console.log('PR3 COMMITTED RECIPIENT INVENTORY VERIFIED: 41 exact collision keys, original strings and preserved winners');
