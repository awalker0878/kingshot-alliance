import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const pageRoots = new Set([
  'Accounts',
  'Alliance',
  'Assistant',
  'Dashboard',
  'Intelligence',
  'Kingdom',
  'Operations',
  'Platform',
  'Public',
]);

function files(directory, extensions) {
  if (!fs.existsSync(directory)) return [];
  return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const item = path.join(directory, entry.name);
    return entry.isDirectory()
      ? files(item, extensions)
      : extensions.includes(path.extname(item))
        ? [item]
        : [];
  });
}

export function verifyFrontendStructure(root) {
  const failures = [];
  const pages = path.join(root, 'resources/js/pages');
  if (!fs.existsSync(pages)) failures.push('The Inertia page directory is missing.');
  else {
    for (const entry of fs.readdirSync(pages, { withFileTypes: true })) {
      if (entry.isDirectory() ? !pageRoots.has(entry.name) : entry.name.endsWith('.vue')) {
        failures.push(`Unexpected Inertia page root: ${entry.name}`);
      }
    }
  }
  for (const file of [
    ...files(path.join(root, 'app'), ['.php']),
    ...files(path.join(root, 'routes'), ['.php']),
  ]) {
    const source = fs.readFileSync(file, 'utf8');
    for (const match of source.matchAll(/Inertia::render\(\s*['"]([^'"]+)['"]/g)) {
      const target = path.resolve(pages, `${match[1]}.vue`);
      const relative = path.relative(pages, target);
      if (relative.startsWith('..') || path.isAbsolute(relative) || !fs.existsSync(target)) {
        failures.push(
          `Missing or invalid Inertia target ${match[1]} in ${path.relative(root, file)}`,
        );
      }
    }
  }
  for (const file of files(path.join(root, 'resources/js'), ['.vue', '.ts'])) {
    const source = fs.readFileSync(file, 'utf8');
    if (source.includes('docs/frontend/reference'))
      failures.push(`Reference artwork used at runtime: ${path.relative(root, file)}`);
    for (const match of source.matchAll(
      /\/images\/kingshot\/([A-Za-z0-9_./-]+\.(?:svg|png|webp|jpe?g|avif))/g,
    )) {
      const artwork = path.resolve(root, 'public/images/kingshot', match[1]);
      const relative = path.relative(path.join(root, 'public/images/kingshot'), artwork);
      if (relative.startsWith('..') || !fs.existsSync(artwork))
        failures.push(
          `Missing or invalid runtime artwork ${match[1]} in ${path.relative(root, file)}`,
        );
    }
  }
  const configPath = path.join(root, 'tsconfig.json');
  if (!fs.existsSync(configPath)) failures.push('The TypeScript configuration is missing.');
  else {
    const config = JSON.parse(fs.readFileSync(configPath, 'utf8'));
    for (const flag of [
      'strict',
      'noImplicitAny',
      'noUncheckedIndexedAccess',
      'exactOptionalPropertyTypes',
      'noImplicitReturns',
    ]) {
      if (config.compilerOptions?.[flag] !== true)
        failures.push(`TypeScript ${flag} must be enabled.`);
    }
  }
  return [...new Set(failures)].sort();
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const failures = verifyFrontendStructure(process.cwd());
  if (failures.length) {
    console.error(
      `Frontend structure check failed:\n${failures.map((failure) => `- ${failure}`).join('\n')}`,
    );
    process.exitCode = 1;
  } else
    console.log(
      'Frontend structure: current page roots, static Inertia targets, runtime artwork and strict TypeScript verified.',
    );
}
