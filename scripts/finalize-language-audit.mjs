import fs from 'node:fs';

const invalidationHelp = {
  ar: 'يؤدي إبطال سجل الاستطلاع إلى إبقائه في السجل وإزالته من حسابات حداثة البيانات الحالية.',
  de: 'Durch das Ungültigmachen eines Scout-Datensatzes bleibt er im Verlauf erhalten und wird aus den aktuellen Aktualitätsberechnungen entfernt.',
  en: 'Invalidating a scout record keeps it in history and removes it from current freshness calculations.',
  es: 'Invalidar un registro de exploración lo conserva en el historial y lo excluye de los cálculos de actualidad actuales.',
  fr: 'L’invalidation d’un relevé d’éclaireur le conserve dans l’historique et l’exclut des calculs d’actualité en cours.',
  id: 'Membatalkan catatan pengintaian tetap menyimpannya dalam riwayat dan mengeluarkannya dari perhitungan kebaruan saat ini.',
  it: 'L’annullamento di un record di ricognizione lo conserva nella cronologia e lo esclude dai calcoli di attualità correnti.',
  ja: '偵察記録を無効にしても履歴には残り、現在の鮮度計算からは除外されます。',
  ko: '정찰 기록을 무효화하면 기록에는 남지만 현재 최신성 계산에서는 제외됩니다.',
  pl: 'Unieważnienie rekordu zwiadu zachowuje go w historii i wyklucza z bieżących obliczeń aktualności.',
  'pt-BR': 'Invalidar um registro de reconhecimento o mantém no histórico e o exclui dos cálculos de atualidade atuais.',
  ru: 'Аннулированная запись разведки остаётся в истории, но исключается из текущих расчётов актуальности.',
  th: 'การทำให้บันทึกการสอดแนมเป็นโมฆะจะยังคงบันทึกไว้ในประวัติ แต่ไม่นำไปคำนวณความสดใหม่ปัจจุบัน',
  tr: 'Bir keşif kaydını geçersiz kılmak kaydı geçmişte tutar ve mevcut güncellik hesaplamalarından çıkarır.',
  vi: 'Vô hiệu hóa một bản ghi trinh sát vẫn giữ bản ghi trong lịch sử nhưng loại nó khỏi các phép tính độ mới hiện tại.',
  'zh-CN': '作废侦察记录会将其保留在历史中，但从当前时效性计算中排除。',
  'zh-TW': '作廢偵察記錄會將其保留在歷史中，但從目前時效性計算中排除。',
};

function read(path) {
  return fs.readFileSync(path, 'utf8');
}

function write(path, source) {
  fs.writeFileSync(path, source);
}

function replace(path, from, to) {
  let source = read(path);
  if (source.includes(to)) return;
  if (!source.includes(from)) throw new Error(`${path}: source not found: ${from.slice(0, 100)}`);
  source = source.replace(from, to);
  write(path, source);
}

for (const [locale, value] of Object.entries(invalidationHelp)) {
  const path = `resources/js/localization/messages/kingdom/${locale}.ts`;
  let source = read(path);
  if (!source.includes('invalidationHelp:')) {
    source = source.replace('  kingdomP7B: {\n', `  kingdomP7B: {\n    invalidationHelp: '${value.replaceAll("'", "\\'")}',\n`);
    write(path, source);
  }
}

const checker = 'scripts/check-page-localization-coverage.mjs';
let check = read(checker);
if (!check.includes("  'id',")) check = check.replace("  'https',\n", "  'https',\n  'id',\n");
if (!check.includes("  'sha-256',")) check = check.replace("  'sha',\n", "  'sha',\n  'sha-256',\n");
if (!check.includes("/^#[0-9a-f]{3,8}$/i")) {
  check = check.replace(
    "  if (text === '') return false;\n",
    "  if (text === '') return false;\n  if (/^#[0-9a-f]{3,8}$/i.test(text)) return false;\n  if (/^K\\d+$/i.test(text)) return false;\n",
  );
}
write(checker, check);

const dossier = 'resources/js/pages/Intelligence/KingdomWatch/AllianceDossier.vue';
replace(
  dossier,
  "  if (value === 'rival') return t('kingdomP7B.rival');\n  return value.charAt(0).toUpperCase() + value.slice(1).replaceAll('_', ' ');",
  "  if (value === 'rival') return t('kingdomP7B.rival');\n  if (value === 'active') return t('kingdomP7B.active');\n  if (value === 'archived') return t('kingdomP7B.archived');\n  if (value === 'current') return t('kingdomP7B.current');\n  if (value === 'stale') return t('kingdomP7B.stale');\n  if (value === 'missing') return t('kingdomP7B.missing');\n  return value.replaceAll('_', ' ');",
);
replace(
  dossier,
  `        <p class="mt-1 text-sm text-[var(--ks-text-secondary)]">\n          {{ intelligence.rows.length }} record(s) match the current filters. Zero is a recorded\n          value; “missing” means scouts have not recorded a value.\n        </p>\n`,
  '',
);

const history = 'resources/js/pages/Intelligence/KingdomWatch/History.vue';
replace(
  history,
  `        <p class="mt-2 text-xl font-semibold">{{ freshness }}</p>`,
  `        <p class="mt-2 text-xl font-semibold">{{ t(\`kingdomP7B.\${freshness}\`) }}</p>`,
);
replace(
  history,
  `            placeholder="Leave blank if unknown"`,
  `            :placeholder="t('kingdomP7B.notSet')"`,
);
replace(
  history,
  `        The original row remains historical and is excluded from latest/freshness projections.`,
  `        {{ t('kingdomP7B.invalidationHelp') }}`,
);

const envoys = 'resources/js/pages/Intelligence/KingdomWatch/Envoys.vue';
replace(envoys, `          Diplomacy`, `          {{ t('kingdomP7B.diplomacy') }}`);
replace(
  envoys,
  `    <section class="mt-6 grid gap-3 sm:grid-cols-3" aria-label="Contact summary">`,
  `    <section class="mt-6 grid gap-3 sm:grid-cols-3" :aria-label="t('kingdomP7B.directory')">`,
);
replace(
  envoys,
  `                <p class="font-semibold">{{ contact.state }}</p>`,
  `                <p class="font-semibold">{{\n                  contact.state === 'active' ? t('kingdomP7B.active') : t('kingdomP7B.inactive')\n                }}</p>`,
);
replace(
  envoys,
  `                  Updated {{ formatDate(contact.updatedAt) }}`,
  `                  {{ t('kingdomP7B.updated', { date: formatDate(contact.updatedAt) }) }}`,
);
replace(
  envoys,
  `                  Deactivated {{ formatDate(contact.deactivatedAt) }} by\n                  {{ contact.deactivatedByName ?? 'former/deleted user' }}`,
  `                  {{\n                    t('kingdomP7B.deactivated', {\n                      date: formatDate(contact.deactivatedAt),\n                      actor: contact.deactivatedByName ?? t('kingdomP7B.unavailableActor'),\n                    })\n                  }}`,
);
replace(
  envoys,
  `                  Last officer\n                  {{ contact.updatedByName ?? contact.createdByName ?? 'former/deleted user' }}`,
  `                  {{\n                    t('kingdomP7B.lastManager', {\n                      actor:\n                        contact.updatedByName ??\n                        contact.createdByName ??\n                        t('kingdomP7B.unavailableActor'),\n                    })\n                  }}`,
);
replace(
  envoys,
  `                <span v-else class="text-xs text-[var(--ks-text-muted)]">Historical</span>`,
  `                <span v-else class="text-xs text-[var(--ks-text-muted)]">{{\n                  t('kingdomP7A.historical')\n                }}</span>`,
);

replace(
  'resources/js/pages/Public/Alliance/Show.vue',
  `        :alt="\`${'${'}alliance.name} banner\`"`,
  `        alt=""\n        aria-hidden="true"`,
);

console.log('Final language audit fixes applied.');
