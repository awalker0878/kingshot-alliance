from pathlib import Path


tools = Path('resources/js/pages/Kingdom/Territory/Tools.vue')
text = tools.read_text()
old = "groups.value.push({ key: groupKey, label: 'Hive template' });"
new = "groups.value.push({ key: groupKey, label: t('territory.tools.hiveTemplate') });"
if old not in text and new not in text:
    raise SystemExit('Hive template group label target not found')
if old in text:
    tools.write_text(text.replace(old, new, 1))

for catalogue in Path('resources/js/localization/messages/territory').glob('*.ts'):
    source = catalogue.read_text()
    if "      hiveTemplate: 'Hive template'," in source:
        continue
    marker = "      reusableHiveTemplates: 'Reusable Hive templates',"
    if marker not in source:
        raise SystemExit(f'Hive template catalogue marker missing: {catalogue}')
    catalogue.write_text(source.replace(marker, "      hiveTemplate: 'Hive template',\n" + marker, 1))
