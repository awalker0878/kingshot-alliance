import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const root = process.cwd();
const pagesDirectory = path.join(root, 'resources/js/pages');

function vueFiles(directory) {
  return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const target = path.join(directory, entry.name);
    if (entry.isDirectory()) return vueFiles(target);
    return entry.isFile() && entry.name.endsWith('.vue') ? [target] : [];
  });
}

const failures = [];
const files = vueFiles(pagesDirectory);

for (const file of files) {
  const source = fs.readFileSync(file, 'utf8');
  const relative = path.relative(root, file);

  if (!source.includes("from '@/localization'")) {
    failures.push(`${relative}: missing localization import`);
    continue;
  }

  if (!/\buseLocale\(\)/.test(source)) {
    failures.push(`${relative}: missing useLocale() call`);
  }
}

if (failures.length > 0) {
  console.error('Every Inertia page must use the application localization service:');
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}

console.log(`Page localization coverage: ${files.length} Vue pages checked.`);
