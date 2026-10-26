import fs from 'node:fs';
import path from 'node:path';
import ts from 'typescript';
import { compileTemplate, parse } from '@vue/compiler-sfc';

function literalKeys(node) {
  if (!node) return [];
  if (ts.isStringLiteral(node) || ts.isNoSubstitutionTemplateLiteral(node)) return [node.text];
  if (ts.isParenthesizedExpression(node)) return literalKeys(node.expression);
  if (ts.isConditionalExpression(node)) {
    return [...literalKeys(node.whenTrue), ...literalKeys(node.whenFalse)];
  }
  return [];
}

export function placeholders(message) {
  return [
    ...new Set([...message.matchAll(/\{([A-Za-z0-9_]+)\}/g)].map((match) => match[1])),
  ].sort();
}

/** Parse actual call expressions, excluding comments and strings that merely mention t(). */
export function translationContract(source, filename) {
  let code = source;
  if (filename.endsWith('.vue')) {
    const { descriptor, errors } = parse(source, { filename });
    if (errors.length) throw new Error(`${filename}: ${errors.join('; ')}`);
    const template = descriptor.template
      ? compileTemplate({
          source: descriptor.template.content,
          filename,
          id: 'localization-contract',
        })
      : { code: '', errors: [] };
    if (template.errors.length) throw new Error(`${filename}: ${template.errors.join('; ')}`);
    code = [descriptor.script?.content, descriptor.scriptSetup?.content, template.code].join('\n');
  }
  const ast = ts.createSourceFile(filename, code, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
  const imports = [];
  const calls = [];
  let dynamicCalls = 0;
  function visit(node) {
    if (ts.isImportDeclaration(node) && !node.importClause?.isTypeOnly)
      imports.push(node.moduleSpecifier.text);
    if (ts.isCallExpression(node)) {
      const callee = node.expression;
      if (callee.kind === ts.SyntaxKind.ImportKeyword)
        imports.push(...literalKeys(node.arguments[0]));
      const isTranslation =
        (ts.isIdentifier(callee) && callee.text === 't') ||
        (ts.isPropertyAccessExpression(callee) &&
          callee.name.text === 't' &&
          ts.isIdentifier(callee.expression) &&
          callee.expression.text === '_ctx');
      if (isTranslation) {
        const keys = literalKeys(node.arguments[0]);
        if (!keys.length) dynamicCalls += 1;
        const params = node.arguments[1];
        let parameterNames = params ? null : [];
        if (
          params &&
          ts.isObjectLiteralExpression(params) &&
          !params.properties.some(ts.isSpreadAssignment)
        ) {
          parameterNames = params.properties.map((property) => property.name?.text).filter(Boolean);
        }
        for (const key of keys) calls.push({ key, parameterNames, file: filename });
      }
    }
    ts.forEachChild(node, visit);
  }
  visit(ast);
  return { calls, imports, dynamicCalls };
}

/** Include static component/composable imports, but never import catalogue contents as UI. */
export function pageTranslationContracts(page, root) {
  const visited = new Set();
  const calls = [];
  let dynamicCalls = 0;
  function visit(file) {
    if (visited.has(file) || file.includes(`${path.sep}localization${path.sep}`)) return;
    visited.add(file);
    const contract = translationContract(fs.readFileSync(file, 'utf8'), path.relative(root, file));
    calls.push(...contract.calls);
    dynamicCalls += contract.dynamicCalls;
    for (const specifier of contract.imports) {
      const target = specifier.startsWith('@/')
        ? path.join(root, 'resources/js', specifier.slice(2))
        : specifier.startsWith('.')
          ? path.resolve(path.dirname(file), specifier)
          : null;
      if (!target) continue;
      const dependency = [
        target,
        `${target}.ts`,
        `${target}.vue`,
        path.join(target, 'index.ts'),
      ].find((candidate) => fs.existsSync(candidate) && fs.statSync(candidate).isFile());
      if (dependency && /\.(vue|ts)$/.test(dependency)) visit(dependency);
    }
  }
  visit(page);
  return { calls, dynamicCalls };
}
