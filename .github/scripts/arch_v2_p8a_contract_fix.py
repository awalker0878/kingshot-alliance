from pathlib import Path

path = Path('tests/Architecture/ArchitectureV2WorkflowTest.php')
source = path.read_text(encoding='utf-8')
old = '        self::assertStringNotContainsString("$lockedPlayer->forceFill([\'user_id\'", $source);'
new = "        self::assertStringNotContainsString('\\$lockedPlayer->forceFill([\\'user_id\\'', $source);"
if source.count(old) != 1:
    raise RuntimeError('P8A workflow contract source changed unexpectedly.')
path.write_text(source.replace(old, new, 1), encoding='utf-8')
