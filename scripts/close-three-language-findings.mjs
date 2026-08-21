import fs from 'node:fs';

function replace(path, from, to) {
  const source = fs.readFileSync(path, 'utf8');
  if (!source.includes(from)) throw new Error(`${path}: expected source not found`);
  fs.writeFileSync(path, source.replace(from, to));
}

replace(
  'resources/js/pages/Intelligence/KingdomWatch/AllianceDossier.vue',
  `        <p class="mt-1 text-sm text-[var(--ks-text-secondary)]">\n          {{ intelligence.rows.length }} record(s) match the current filters. Zero is a recorded\n          value; “missing” means scouts have not recorded a value.\n        </p>\n`,
  '',
);

replace(
  'resources/js/pages/Intelligence/KingdomWatch/History.vue',
  `            placeholder="Leave blank if unknown"`,
  `            :placeholder="t('kingdomP7B.notSet')"`,
);

replace(
  'resources/js/pages/Public/Alliance/Show.vue',
  `        :alt="\`${'${'}alliance.name} banner\`"`,
  `        alt=""\n        aria-hidden="true"`,
);
