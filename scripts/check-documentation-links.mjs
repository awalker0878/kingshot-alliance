import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const root = process.cwd();
const documentationRoot = path.join(root, 'docs');
const markdownFiles = [];

function collectMarkdownFiles(directory) {
  for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
    const location = path.join(directory, entry.name);
    if (entry.isDirectory()) {
      collectMarkdownFiles(location);
    } else if (entry.isFile() && entry.name.endsWith('.md')) {
      markdownFiles.push(location);
    }
  }
}

function readableTarget(rawTarget) {
  const target = rawTarget.replace(/^<|>$/g, '');
  if (
    target === '' ||
    target.startsWith('#') ||
    target.startsWith('//') ||
    /^[a-z][a-z\d+.-]*:/i.test(target)
  ) {
    return null;
  }

  const withoutFragment = target.split('#', 1)[0].split('?', 1)[0];
  try {
    return decodeURIComponent(withoutFragment);
  } catch {
    return withoutFragment;
  }
}

function markdownBody(source) {
  return source
    .replace(/```[\s\S]*?```/g, '')
    .replace(/~~~[\s\S]*?~~~/g, '')
    .replace(/`[^`\n]*`/g, '');
}

function existingTarget(sourceFile, target) {
  const location = target.startsWith('/')
    ? path.resolve(root, `.${target}`)
    : path.resolve(path.dirname(sourceFile), target);

  return fs.existsSync(location);
}

collectMarkdownFiles(documentationRoot);

const failures = [];
for (const file of markdownFiles.sort()) {
  const source = markdownBody(fs.readFileSync(file, 'utf8'));
  const linkPattern = /!?\[[^\]]*\]\(([^)\s]+)(?:\s+["'][^"']*["'])?\)/g;

  for (const match of source.matchAll(linkPattern)) {
    const target = readableTarget(match[1]);
    if (target !== null && !existingTarget(file, target)) {
      failures.push(`${path.relative(root, file)} -> ${match[1]}`);
    }
  }
}

if (failures.length > 0) {
  console.error(`Broken documentation links:\n - ${failures.join('\n - ')}`);
  process.exit(1);
}

console.log(`Documentation links: ${markdownFiles.length} files checked.`);
