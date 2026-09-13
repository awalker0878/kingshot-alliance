#!/usr/bin/env python3
"""Verify immutable progression facts while reporting fresh live-HTML acquisition hashes."""
from __future__ import annotations

import argparse
from datetime import datetime, timezone
import json
from pathlib import Path
import re
import subprocess
from typing import Any
from urllib.parse import urlsplit

RELEASE = Path('resources/data/progression/kingshot-2026-08-23-v2')
LIVE_HTML_KINDS = frozenset({'alliance_tech', 'academy_research', 'category_hub', 'factual_detail_page'})
SHA256 = re.compile(r'[a-f0-9]{64}')


def unique_object(pairs: list[tuple[str, Any]]) -> dict[str, Any]:
    value: dict[str, Any] = {}
    for key, item in pairs:
        if key in value:
            raise ValueError(f'Duplicate JSON field: {key}')
        value[key] = item
    return value


def source_lock(body: bytes) -> dict[str, Any]:
    lock = json.loads(body, object_pairs_hook=unique_object)
    if not isinstance(lock, dict) or not isinstance(lock.get('sources'), list) or not lock['sources']:
        raise ValueError('Source lock must declare a nonempty source inventory.')
    identities: set[tuple[str, str, str]] = set()
    for row in lock['sources']:
        if not isinstance(row, dict) or any(not isinstance(row.get(key), str) or not row[key] for key in ('source_id', 'url', 'sha256')):
            raise ValueError('Source lock contains an invalid acquisition record.')
        if 'dataset' in row and (not isinstance(row['dataset'], str) or not row['dataset']):
            raise ValueError('Source lock contains an invalid dataset identity.')
        if SHA256.fullmatch(row['sha256']) is None:
            raise ValueError('Source lock contains an invalid SHA-256.')
        identity = (row['source_id'], row.get('dataset', ''), row['url'])
        if identity in identities:
            raise ValueError('Source lock contains duplicate source identities.')
        identities.add(identity)
    return lock


def live_html(row: dict[str, Any]) -> bool:
    url = urlsplit(row['url'])
    return (
        row['source_id'] == 'kingshotdata'
        and row.get('kind') in LIVE_HTML_KINDS
        and url.scheme == 'https'
        and url.netloc == 'kingshotdata.com'
        and not url.query and not url.fragment
        and 'upstream_commit' not in row
    )


def compare_releases(baseline: dict[str, bytes], candidate: dict[str, bytes]) -> list[dict[str, str]]:
    if set(baseline) != set(candidate):
        raise ValueError('Regenerated release file inventory differs from the checked-in release.')
    if 'source-lock.json' not in baseline:
        raise ValueError('The checked-in source lock is missing.')
    for name, body in baseline.items():
        if name != 'source-lock.json' and candidate[name] != body:
            raise ValueError(f'Immutable factual release artifact changed: {name}')

    original = source_lock(baseline['source-lock.json'])
    regenerated = source_lock(candidate['source-lock.json'])
    before = original.pop('sources')
    after = regenerated.pop('sources')
    if original != regenerated or len(before) != len(after):
        raise ValueError('Source lock metadata or source inventory changed.')

    changes: list[dict[str, str]] = []
    for old, new in zip(before, after, strict=True):
        old_hash = old['sha256']
        new_hash = new['sha256']
        if {key: value for key, value in old.items() if key != 'sha256'} != {key: value for key, value in new.items() if key != 'sha256'}:
            raise ValueError('Source identity, order or factual acquisition metadata changed.')
        if old_hash == new_hash:
            continue
        if not live_html(old):
            raise ValueError(f'Pinned or structured source hash changed: {old["url"]}')
        changes.append({
            'source_id': old['source_id'], 'url': old['url'], 'kind': old['kind'],
            **({'dataset': old['dataset']} if 'dataset' in old else {}),
            'previous_sha256': old_hash, 'observed_sha256': new_hash,
        })
    return changes


def git(repo: Path, *arguments: str) -> bytes:
    return subprocess.check_output(['git', *arguments], cwd=repo)


def verify(repo: Path) -> dict[str, Any]:
    release = repo / RELEASE
    tracked = git(repo, 'ls-tree', '-r', '--name-only', '-z', 'HEAD', '--', RELEASE.as_posix()).decode().split('\0')
    baseline = {
        Path(name).relative_to(RELEASE).as_posix(): git(repo, 'show', f'HEAD:{name}')
        for name in tracked if name
    }
    candidate: dict[str, bytes] = {}
    for file in release.rglob('*'):
        if file.is_symlink():
            raise ValueError('Regenerated release cannot contain symbolic links.')
        if file.is_file():
            candidate[file.relative_to(release).as_posix()] = file.read_bytes()
    changes = compare_releases(baseline, candidate)
    return {
        'status': 'immutable_facts_unchanged',
        'checked_at': datetime.now(timezone.utc).isoformat(),
        'repository_head': git(repo, 'rev-parse', 'HEAD').decode().strip(),
        'release_path': RELEASE.as_posix(),
        'verified_files': len(baseline),
        'live_html_acquisition_changes': changes,
    }


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--report', type=Path, required=True)
    args = parser.parse_args()
    repo = Path(__file__).resolve().parents[2]
    report_path = args.report.resolve()
    if report_path.is_relative_to((repo / RELEASE).resolve()):
        parser.error('Acquisition evidence must be written outside the immutable release directory.')
    report = verify(repo)
    report_path.parent.mkdir(parents=True, exist_ok=True)
    report_path.write_text(json.dumps(report, indent=2) + '\n', encoding='utf-8')
    print(f'Compared {report["verified_files"]} release artifacts with exact factual files and pinned sources; '
          f'{len(report["live_html_acquisition_changes"])} live HTML acquisition hash changes recorded.')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
