from __future__ import annotations

import hashlib
import json
import re
import subprocess
from pathlib import Path

ROOT = Path.cwd()
MIGRATIONS_IMPORT = "use Illuminate\\Foundation\\Testing\\DatabaseMigrations;"
MIGRATIONS_USE = "    use DatabaseMigrations;"
TRUNCATION_IMPORT = "use Illuminate\\Foundation\\Testing\\DatabaseTruncation;"
TRUNCATION_USE = "    use DatabaseTruncation;"


def git(*args: str) -> str:
    return subprocess.check_output(["git", *args], cwd=ROOT).decode().strip()


def blob_sha(content: bytes) -> str:
    header = b"blob " + str(len(content)).encode() + b"\0"
    return hashlib.sha1(header + content).hexdigest()


# These patterns identify tests whose schema lifecycle may be part of the behavior
# under test. They stay on DatabaseMigrations until reviewed individually.
BLOCKERS: list[tuple[str, re.Pattern[str]]] = [
    (
        "schema facade or builder",
        re.compile(r"Illuminate\\\\Support\\\\Facades\\\\Schema|(?<![A-Za-z0-9_])Schema::|getSchemaBuilder\\s*\\("),
    ),
    (
        "migration/schema artisan command",
        re.compile(r"artisan\\s*\\([^\\n]*(?:migrate|db:wipe|schema:)", re.IGNORECASE),
    ),
    (
        "database refresh hook/state override",
        re.compile(r"RefreshDatabaseState|beforeRefreshingDatabase|afterRefreshingDatabase"),
    ),
    (
        "explicit DDL",
        re.compile(
            r"\\b(?:create|alter|drop)\\s+(?:table|schema|database|index|type|sequence)\\b",
            re.IGNORECASE,
        ),
    ),
]

source_commit = git("rev-parse", "HEAD")
all_test_files = sorted((ROOT / "tests").rglob("*Test.php"))
remaining_migration_files: list[Path] = []
for path in all_test_files:
    source = path.read_text()
    if MIGRATIONS_IMPORT in source and MIGRATIONS_USE in source:
        remaining_migration_files.append(path)

# The migration baseline recorded 50 DatabaseMigrations classes. One measured hot
# spot was already moved to DatabaseTruncation, leaving 49 here: 47 Integration
# contracts plus two explicitly retained Feature contracts.
assert len(remaining_migration_files) == 49, [
    path.relative_to(ROOT).as_posix() for path in remaining_migration_files
]

integration_candidates = [
    path for path in remaining_migration_files
    if path.is_relative_to(ROOT / "tests" / "Integration")
]
feature_migrations = [
    path for path in remaining_migration_files
    if path.is_relative_to(ROOT / "tests" / "Feature")
]
assert len(integration_candidates) == 47, len(integration_candidates)
assert sorted(path.name for path in feature_migrations) == [
    "GoogleAuthenticationV3Test.php",
    "PasswordProofThrottleV3Test.php",
], [path.relative_to(ROOT).as_posix() for path in feature_migrations]

converted: list[dict[str, object]] = []
retained: list[dict[str, object]] = []

for path in integration_candidates:
    original = path.read_bytes()
    source = original.decode()
    reasons = [label for label, pattern in BLOCKERS if pattern.search(source)]
    relative = path.relative_to(ROOT).as_posix()

    if reasons:
        retained.append({
            "path": relative,
            "source_blob": blob_sha(original),
            "reasons": reasons,
        })
        continue

    assert source.count(MIGRATIONS_IMPORT) == 1, relative
    assert source.count(MIGRATIONS_USE) == 1, relative
    updated = source.replace(MIGRATIONS_IMPORT, TRUNCATION_IMPORT, 1)
    updated = updated.replace(MIGRATIONS_USE, TRUNCATION_USE, 1)
    assert MIGRATIONS_IMPORT not in updated and MIGRATIONS_USE not in updated, relative
    assert updated.count(TRUNCATION_IMPORT) == 1 and updated.count(TRUNCATION_USE) == 1, relative
    path.write_text(updated)

    final = updated.encode()
    converted.append({
        "path": relative,
        "source_blob": blob_sha(original),
        "result_blob": blob_sha(final),
        "reason": "Committed-state Integration contract with no schema-mutation pattern; preserve real database semantics while truncating data between tests.",
    })

# A low conversion count means the repository drifted or the blocker rules are too
# broad. Fail instead of silently publishing a weak optimization.
assert len(converted) >= 40, (len(converted), retained)

manifest = {
    "source_commit": source_commit,
    "framework": "laravel/framework v13.30.1",
    "source_reset": "Illuminate\\Foundation\\Testing\\DatabaseMigrations",
    "target_reset": "Illuminate\\Foundation\\Testing\\DatabaseTruncation",
    "integration_candidates": len(integration_candidates),
    "converted": converted,
    "retained_database_migrations": retained,
    "feature_database_migrations_unchanged": [
        path.relative_to(ROOT).as_posix() for path in feature_migrations
    ],
    "behavioral_changes": False,
    "tests_executed": False,
}
manifest_path = ROOT / "docs" / "codebase" / "test-database-reset-migration.json"
manifest_path.write_text(json.dumps(manifest, indent=2) + "\n")

# The preparation program is not part of the proposed repository state.
(ROOT / ".github" / "pr163-optimize-database-resets.py").unlink()

print(json.dumps({
    "integration_candidates": len(integration_candidates),
    "converted": len(converted),
    "retained": len(retained),
    "tests_executed": False,
}))
