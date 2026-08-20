import { parse as parseTemplate, NodeTypes } from '@vue/compiler-dom';
import { parse as parseSfc } from '@vue/compiler-sfc';
import { readdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';

const sourceRoot = path.resolve('resources/js');
const failures = [];

async function vueFiles(directory) {
  const entries = await readdir(directory, { withFileTypes: true });
  const files = await Promise.all(
    entries.map(async (entry) => {
      const resolved = path.join(directory, entry.name);
      if (entry.isDirectory()) return vueFiles(resolved);
      return entry.isFile() && entry.name.endsWith('.vue') ? [resolved] : [];
    }),
  );

  return files.flat();
}

function lineFor(source, offset) {
  return source.slice(0, offset).split('\n').length;
}

function inspectTemplate(source, filename, template) {
  const ast = parseTemplate(template.content, { comments: false });

  function visit(node) {
    if (node.type === NodeTypes.ELEMENT && node.tag === 'ConfirmActionDialog') {
      const spreadBinding = node.props.some(
        (prop) => prop.type === NodeTypes.DIRECTIVE && prop.name === 'bind' && prop.arg === undefined,
      );
      const names = new Set(
        node.props.flatMap((prop) => {
          if (prop.type === NodeTypes.ATTRIBUTE) return [prop.name];
          if (
            prop.type === NodeTypes.DIRECTIVE &&
            prop.arg?.type === NodeTypes.SIMPLE_EXPRESSION &&
            (prop.name === 'bind' || prop.name === 'on')
          ) {
            return [prop.arg.content.toLowerCase()];
          }
          return [];
        }),
      );
      const required = ['id', 'open', 'title', 'description', 'confirm-label', 'cancel-label', 'busy'];

      if (!spreadBinding) {
        for (const name of required) {
          if (!names.has(name)) {
            failures.push(
              `${filename}:${lineFor(source, template.loc.start.offset + node.loc.start.offset)} ConfirmActionDialog is missing ${name}.`,
            );
          }
        }
      }

      for (const event of ['confirm', 'cancel']) {
        if (!names.has(event)) {
          failures.push(
            `${filename}:${lineFor(source, template.loc.start.offset + node.loc.start.offset)} ConfirmActionDialog is missing @${event}.`,
          );
        }
      }
    }

    if ('children' in node && Array.isArray(node.children)) {
      for (const child of node.children) visit(child);
    }
  }

  visit(ast);
}

for (const filename of await vueFiles(sourceRoot)) {
  const source = await readFile(filename, 'utf8');
  const relative = path.relative(process.cwd(), filename);
  const { descriptor, errors } = parseSfc(source, { filename: relative });

  for (const error of errors) failures.push(`${relative}: ${String(error)}`);

  const script = [descriptor.script?.content, descriptor.scriptSetup?.content]
    .filter(Boolean)
    .join('\n');
  const nativeConfirmation = /\b(?:window|globalThis)\.confirm\s*\(/g;

  for (const match of script.matchAll(nativeConfirmation)) {
    const scriptOffset = descriptor.scriptSetup?.loc.start.offset ?? descriptor.script?.loc.start.offset ?? 0;
    failures.push(
      `${relative}:${lineFor(source, scriptOffset + (match.index ?? 0))} browser confirmation APIs are prohibited; use ConfirmActionDialog.`,
    );
  }

  if (descriptor.template) inspectTemplate(source, relative, descriptor.template);
}

if (failures.length > 0) {
  console.error('Accessible confirmation check failed:\n');
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}

console.log('Accessible confirmation check passed.');
