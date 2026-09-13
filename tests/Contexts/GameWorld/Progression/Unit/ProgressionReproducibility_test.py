from __future__ import annotations

import copy
import importlib.util
import json
import subprocess
import tempfile
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[5]
spec = importlib.util.spec_from_file_location('verify_regenerated', ROOT / 'scripts/progression/verify_regenerated.py')
assert spec is not None and spec.loader is not None
verifier = importlib.util.module_from_spec(spec)
spec.loader.exec_module(verifier)


class ProgressionReproducibilityTest(unittest.TestCase):
    def setUp(self):
        self.lock = {'schema_version': 1, 'observed_at': '2026-08-23', 'sources': [
            {'source_id': 'kingshotdata', 'dataset': 'heroes_tables', 'url': 'https://kingshotdata.com/heroes/example/',
             'kind': 'factual_detail_page', 'sha256': 'a' * 64},
            {'source_id': 'g2384-kingshot-data', 'dataset': 'hero_skills', 'url': 'https://raw.githubusercontent.com/owner/repo/commit/file',
             'upstream_commit': '1' * 40, 'sha256': 'b' * 64},
        ]}
        self.baseline = {'facts.json': b'{"damage":100}', 'release.json': b'{"version":"reviewed"}',
                         'source-lock.json': self.encode(self.lock)}

    @staticmethod
    def encode(value):
        return json.dumps(value).encode()

    def candidate(self, change):
        candidate = copy.deepcopy(self.baseline)
        lock = copy.deepcopy(self.lock)
        change(lock)
        candidate['source-lock.json'] = self.encode(lock)
        return candidate

    def test_identical_artifacts_keep_the_original_acquisition_evidence(self):
        self.assertEqual([], verifier.compare_releases(self.baseline, self.baseline))

    def test_only_classified_live_html_byte_changes_become_explicit_acquisition_evidence(self):
        candidate = self.candidate(lambda lock: lock['sources'][0].update(sha256='c' * 64))
        changes = verifier.compare_releases(self.baseline, candidate)
        self.assertEqual(1, len(changes))
        self.assertEqual('a' * 64, changes[0]['previous_sha256'])
        self.assertEqual('c' * 64, changes[0]['observed_sha256'])
        self.assertEqual(self.encode(self.lock), self.baseline['source-lock.json'])

    def test_academy_acquisition_without_an_optional_dataset_identity_remains_verified(self):
        baseline = self.candidate(lambda lock: lock['sources'][0].pop('dataset'))
        lock = json.loads(baseline['source-lock.json'])
        lock['sources'][0]['sha256'] = 'c' * 64
        candidate = {**baseline, 'source-lock.json': self.encode(lock)}
        changes = verifier.compare_releases(baseline, candidate)
        self.assertEqual(1, len(changes))
        self.assertNotIn('dataset', changes[0])
        with self.assertRaisesRegex(ValueError, 'dataset'):
            verifier.compare_releases(self.baseline, self.candidate(lambda row: row['sources'][0].update(dataset=None)))

    def test_changed_facts_and_release_policy_reject_even_when_live_hashes_change(self):
        for name in ['facts.json', 'release.json']:
            with self.subTest(name=name):
                candidate = self.candidate(lambda lock: lock['sources'][0].update(sha256='c' * 64))
                candidate[name] += b' '
                with self.assertRaisesRegex(ValueError, 'Immutable factual'):
                    verifier.compare_releases(self.baseline, candidate)

    def test_pinned_source_hash_changes_reject(self):
        candidate = self.candidate(lambda lock: lock['sources'][1].update(sha256='c' * 64))
        with self.assertRaisesRegex(ValueError, 'Pinned or structured'):
            verifier.compare_releases(self.baseline, candidate)

    def test_new_missing_and_reordered_sources_reject(self):
        for change in [lambda lock: lock['sources'].pop(), lambda lock: lock['sources'].reverse(),
                       lambda lock: lock['sources'].append(copy.deepcopy(lock['sources'][0]))]:
            with self.subTest(change=change), self.assertRaises(ValueError):
                verifier.compare_releases(self.baseline, self.candidate(change))

    def test_changed_url_kind_dataset_and_recorded_cutoff_reject(self):
        for field, value in [('url', 'https://other.example/'), ('kind', 'unreviewed'), ('dataset', 'other')]:
            with self.subTest(field=field), self.assertRaises(ValueError):
                verifier.compare_releases(self.baseline, self.candidate(lambda lock: lock['sources'][0].update({field: value})))
        with self.assertRaises(ValueError):
            verifier.compare_releases(self.baseline, self.candidate(lambda lock: lock.update(observed_at='2026-09-13')))

    def test_unclassified_or_structured_sources_never_inherit_the_live_html_exception(self):
        for change in [dict(source_id='other'), dict(kind='structured_json'), dict(url='http://kingshotdata.com/a'),
                       dict(url='https://kingshotdata.com.evil.example/a'), dict(upstream_commit='1' * 40)]:
            with self.subTest(change=change):
                baseline = self.candidate(lambda lock: lock['sources'][0].update(change))
                lock = json.loads(baseline['source-lock.json'])
                lock['sources'][0]['sha256'] = 'c' * 64
                candidate = {**baseline, 'source-lock.json': self.encode(lock)}
                with self.assertRaisesRegex(ValueError, 'Pinned or structured'):
                    verifier.compare_releases(baseline, candidate)

    def test_git_baseline_is_immutable_and_filesystem_verification_rejects_symbolic_links(self):
        with tempfile.TemporaryDirectory() as directory:
            repo = Path(directory)
            release = repo / verifier.RELEASE
            release.mkdir(parents=True)
            for name, body in self.baseline.items():
                (release / name).write_bytes(body)
            def git(*arguments):
                return subprocess.check_output(['git', *arguments], cwd=repo, stderr=subprocess.DEVNULL)
            git('init', '-q')
            git('add', '.')
            git('-c', 'user.name=Test', '-c', 'user.email=test@example.invalid', 'commit', '-qm', 'Baseline fixture')
            lock = copy.deepcopy(self.lock)
            lock['sources'][0]['sha256'] = 'c' * 64
            (release / 'source-lock.json').write_bytes(self.encode(lock))
            report = verifier.verify(repo)
            self.assertEqual('immutable_facts_unchanged', report['status'])
            self.assertEqual(1, len(report['live_html_acquisition_changes']))
            self.assertEqual(self.baseline['source-lock.json'], git('show', f'HEAD:{verifier.RELEASE.as_posix()}/source-lock.json'))
            (release / 'facts.json').write_bytes(b'{"damage":101}')
            with self.assertRaisesRegex(ValueError, 'Immutable factual'):
                verifier.verify(repo)
            (release / 'facts.json').unlink()
            (repo / 'outside.json').write_bytes(self.baseline['facts.json'])
            (release / 'facts.json').symlink_to(repo / 'outside.json')
            with self.assertRaisesRegex(ValueError, 'symbolic links'):
                verifier.verify(repo)

    def test_invalid_hash_duplicate_json_fields_and_unexpected_files_reject(self):
        with self.assertRaises(ValueError):
            verifier.compare_releases(self.baseline, self.candidate(lambda lock: lock['sources'][0].update(sha256='not-a-hash')))
        with self.assertRaisesRegex(ValueError, 'Duplicate JSON'):
            verifier.source_lock(b'{"sources":[],"sources":[]}')
        with self.assertRaisesRegex(ValueError, 'inventory'):
            verifier.compare_releases(self.baseline, {**self.baseline, 'unexpected.json': b'{}'})
        with self.assertRaisesRegex(ValueError, 'inventory'):
            verifier.compare_releases(self.baseline, {'source-lock.json': self.baseline['source-lock.json']})


if __name__ == '__main__':
    unittest.main()
