import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const ts = require('typescript');
const { NodeTypes, parse } = require('@vue/compiler-dom');
const { parse: parseSfc } = require('@vue/compiler-sfc');
const root = process.cwd();
const pagesDirectory = path.join(root, 'resources/js/pages');
const invariantTokens = new Set([
  'api',
  'csv',
  'http',
  'https',
  'min',
  'nap',
  'r1',
  'r2',
  'r3',
  'r4',
  'r5',
  'sha',
  'sha256',
  'utc',
]);
const visibleAttributeNames = new Set([
  'alt',
  'aria-label',
  'description',
  'eyebrow',
  'help-text',
  'label',
  'placeholder',
  'subtitle',
  'title',
]);
const visiblePropertyNames = new Set([
  'description',
  'eyebrow',
  'helpText',
  'label',
  'placeholder',
  'subtitle',
  'title',
]);

function vueFiles(directory) {
  return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const target = path.join(directory, entry.name);
    if (entry.isDirectory()) return vueFiles(target);
    return entry.isFile() && entry.name.endsWith('.vue') ? [target] : [];
  });
}

function sfcBlocks(source, relative) {
  const { descriptor, errors } = parseSfc(source, { filename: relative });
  if (errors.length > 0) {
    const message = errors
      .map((error) => (error instanceof Error ? error.message : String(error)))
      .join('; ');
    throw new Error(`${relative}: Vue SFC could not be parsed: ${message}`);
  }

  return {
    template: descriptor.template?.content ?? '',
    script: descriptor.scriptSetup?.content ?? descriptor.script?.content ?? '',
  };
}

function normalized(value) {
  return value
    .replace(/&(?:amp|nbsp|middot|mdash|ndash);/g, ' ')
    .replace(/\$\{[^}]+}/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function isVisibleLanguage(value) {
  const text = normalized(value);
  if (text === '') return false;
  if (/^R[1-5](?:\s*[-–/]\s*R[1-5])*$/.test(text)) return false;

  const tokens = text.toLowerCase().match(/[a-z][a-z0-9-]*/g) ?? [];
  if (tokens.length === 0) return false;
  if (tokens.every((token) => invariantTokens.has(token))) return false;

  return tokens.some((token) => token.length >= 2);
}

function unwrapExpression(node) {
  let current = node;
  while (
    current &&
    (ts.isParenthesizedExpression(current) ||
      ts.isAsExpression(current) ||
      ts.isSatisfiesExpression(current) ||
      ts.isNonNullExpression(current))
  ) {
    current = current.expression;
  }
  return current;
}

function literalOutputStrings(node) {
  const value = unwrapExpression(node);
  if (!value) return [];

  if (ts.isStringLiteral(value) || ts.isNoSubstitutionTemplateLiteral(value)) {
    return isVisibleLanguage(value.text) ? [value.text] : [];
  }

  if (ts.isTemplateExpression(value)) {
    const text = [value.head.text, ...value.templateSpans.map((span) => span.literal.text)].join(' ');
    return isVisibleLanguage(text) ? [text] : [];
  }

  if (ts.isConditionalExpression(value)) {
    return [
      ...literalOutputStrings(value.whenTrue),
      ...literalOutputStrings(value.whenFalse),
    ];
  }

  if (ts.isBinaryExpression(value) && value.operatorToken.kind === ts.SyntaxKind.PlusToken) {
    return [...literalOutputStrings(value.left), ...literalOutputStrings(value.right)];
  }

  return [];
}

function literalStringsFromExpression(expression) {
  if (!expression || /\bt\s*\(/.test(expression)) return [];

  const source = ts.createSourceFile(
    'attribute-expression.ts',
    `const __value = (${expression});`,
    ts.ScriptTarget.Latest,
    true,
    ts.ScriptKind.TS,
  );
  if (source.parseDiagnostics.length > 0) return [];

  let initializer = null;
  source.forEachChild((node) => {
    if (!ts.isVariableStatement(node)) return;
    initializer = node.declarationList.declarations[0]?.initializer ?? null;
  });

  return literalOutputStrings(initializer);
}

function rawTemplateStrings(template, relative) {
  if (template.trim() === '') return [];

  let ast;
  try {
    ast = parse(template, { comments: false });
  } catch (error) {
    throw new Error(`${relative}: Vue template could not be parsed: ${error.message}`);
  }

  const values = [];
  function visit(node) {
    if (node.type === NodeTypes.TEXT) {
      if (isVisibleLanguage(node.content)) values.push(normalized(node.content));
      return;
    }

    if (node.type === NodeTypes.ELEMENT) {
      for (const prop of node.props) {
        if (prop.type === NodeTypes.ATTRIBUTE) {
          if (!visibleAttributeNames.has(prop.name) || !prop.value) continue;
          if (isVisibleLanguage(prop.value.content)) values.push(normalized(prop.value.content));
          continue;
        }

        if (
          prop.type === NodeTypes.DIRECTIVE &&
          prop.name === 'bind' &&
          prop.arg?.type === NodeTypes.SIMPLE_EXPRESSION &&
          prop.arg.isStatic &&
          visibleAttributeNames.has(prop.arg.content) &&
          prop.exp?.type === NodeTypes.SIMPLE_EXPRESSION
        ) {
          values.push(...literalStringsFromExpression(prop.exp.content).map(normalized));
        }
      }
    }

    if (Array.isArray(node.children)) {
      for (const child of node.children) visit(child);
    }
    if (node.type === NodeTypes.IF) {
      for (const branch of node.branches) visit(branch);
    }
    if (node.type === NodeTypes.IF_BRANCH && Array.isArray(node.children)) {
      for (const child of node.children) visit(child);
    }
    if (node.type === NodeTypes.FOR && Array.isArray(node.children)) {
      for (const child of node.children) visit(child);
    }
  }

  visit(ast);
  return values;
}

function rawScriptLabels(sourceText) {
  const source = ts.createSourceFile('page.ts', sourceText, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
  const values = [];

  function visit(node) {
    if (ts.isPropertyAssignment(node)) {
      const name = node.name.text ?? node.name.getText(source).replace(/^['"]|['"]$/g, '');
      const initializer = node.initializer;
      if (
        visiblePropertyNames.has(name) &&
        (ts.isStringLiteral(initializer) || ts.isNoSubstitutionTemplateLiteral(initializer)) &&
        isVisibleLanguage(initializer.text)
      ) {
        values.push(normalized(initializer.text));
      }
    }
    ts.forEachChild(node, visit);
  }

  visit(source);
  return values;
}

const failures = [];
const files = vueFiles(pagesDirectory);

for (const file of files) {
  const source = fs.readFileSync(file, 'utf8');
  const relative = path.relative(root, file).replaceAll('\\', '/');

  if (!source.includes("from '@/localization'")) {
    failures.push(`${relative}: missing localization import`);
    continue;
  }

  if (!/\buseLocale\(\)/.test(source)) {
    failures.push(`${relative}: missing useLocale() call`);
  }

  const blocks = sfcBlocks(source, relative);
  const rawStrings = [
    ...rawTemplateStrings(blocks.template, relative),
    ...rawScriptLabels(blocks.script),
  ];
  for (const value of [...new Set(rawStrings)]) {
    failures.push(`${relative}: visible copy must use localization: "${value}"`);
  }
}

if (failures.length > 0) {
  console.error('Every Inertia page must use localized visible copy:');
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}

console.log(`Page localization coverage: ${files.length} Vue pages checked with no raw visible copy.`);
