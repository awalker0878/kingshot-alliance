"""Create reviewable translation drafts; never modifies application catalogues."""
import argparse
import hashlib
import html
import json
import os
from pathlib import Path
import re
import time
import urllib.request

LOCALES = ['ar', 'de', 'es', 'fr', 'id', 'it', 'ja', 'ko', 'pl', 'pt-BR', 'ru', 'th', 'tr', 'vi', 'zh-CN', 'zh-TW']
REVISION = 'aa32bbdeba7880eff2096ec044cb155a340a9400'
MODEL = 'Nextcloud-AI/madlad400-3b-mt-ct2-int8'
MODEL_FILES = {
    'model.bin': '77b9fd9ab97c1259d07089b5f854393dad81bc5fb5647d3f9a5d101c94f40daa',
    'spiece.model': 'ef11ac9a22c7503492f56d48dce53be20e339b63605983e9f27d2cd0e0f3922c',
    'shared_vocabulary.json': 'c327551ce3ca6efc7b437e11a267f79979893332dda8a1d146e2c950815193f8',
    'config.json': '90fb54962455a4e0a0bc7235c0f063d7e46d9c1a1ae003af8059809abd6aeece',
}
INVARIANTS = {'Kingshot Alliance', 'Kingshot', 'KingShot', 'KINGSHOT', 'Google', 'Discord', 'Telegram', 'HTTP', 'HTTPS', 'API', 'CSV', 'JSON', 'UTC', 'ID', 'NAP', 'MFA', 'LTR', 'RTL', 'X', 'Y', '/gift-codes.json', 'key,type,variant_key,x,y,rotation,...', 'TC {level}', 'phase-key', 'roster-key', 'formation-key', 'poll-key'}
PARAMETER = re.compile(r'\{[A-Za-z0-9_]+\}|(?<![\w/]):[A-Za-z_][A-Za-z0-9_]*')
PROTECTED = re.compile(PARAMETER.pattern + r'|https?://[^\s]+|Kingshot Alliance|KingShot|Kingshot|KINGSHOT|Discord|Google|Telegram|\b(?:API|CSV|JSON|HTTP|HTTPS|UTC|SHA-256|OCR|ID)\b|\b[a-z]+_[a-z_0-9]+\b|\.csv\b')
CONCEPTS = ['Governors', 'Governor', 'Alliances', 'Alliance', 'Kingdoms', 'Kingdom', 'Events', 'Event', 'Players', 'Player', 'Evidence', 'Gift Codes', 'Gift Code', 'User']


def protect(source):
    replacements = {}
    def replace(match):
        token = str(90000001 + len(replacements))
        replacements[token] = match.group()
        return token
    value = PROTECTED.sub(replace, source)
    for concept in CONCEPTS:
        value = re.sub(r'\b' + re.escape(concept) + r'\b', lambda match: match.group().lower(), value)
    return value, replacements


def restore(raw, replacements, source):
    value = html.unescape(raw).strip()
    for token, original in replacements.items():
        value, count = re.subn(r'[\s,٬，.]*'.join(token), lambda _: original, value)
        if count != 1:
            return None
    if re.search(r'9000\d{4}', value):
        return None
    if not re.match(r'^(?:\d|\{)', source):
        value = re.sub(r'^\d+\.\s+', '', value)
    if sorted(PARAMETER.findall(source)) != sorted(PARAMETER.findall(value)):
        return None
    return value if value else None


def download_model(directory):
    directory.mkdir(parents=True, exist_ok=True)
    for name, expected in MODEL_FILES.items():
        destination = directory / name
        if destination.exists() and hashlib.file_digest(destination.open('rb'), 'sha256').hexdigest() == expected:
            continue
        url = f'https://huggingface.co/{MODEL}/resolve/{REVISION}/{name}'
        temporary = directory / (name + '.download')
        digest = hashlib.sha256()
        with urllib.request.urlopen(url, timeout=180) as response, temporary.open('wb') as stream:
            while chunk := response.read(1024 * 1024):
                stream.write(chunk)
                digest.update(chunk)
        if digest.hexdigest() != expected:
            raise RuntimeError(f'Model checksum mismatch: {name}')
        temporary.replace(destination)
        print(f'Verified model file: {name}', flush=True)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--input', type=Path, required=True)
    parser.add_argument('--locale', choices=LOCALES, required=True)
    parser.add_argument('--output', type=Path, required=True)
    parser.add_argument('--model-directory', type=Path, required=True)
    args = parser.parse_args()
    data = json.loads(args.input.read_text())
    source_hash = hashlib.sha256(args.input.read_bytes()).hexdigest()
    locale = args.locale
    args.output.mkdir(parents=True, exist_ok=True)
    output = args.output / f'{locale}.jsonl'
    done = {record['en']: record for record in map(json.loads, output.read_text().splitlines())} if output.exists() else {}
    reviewed_path = Path('resources/data/localization/reviewed.json')
    reviewed = json.loads(reviewed_path.read_text()) if reviewed_path.exists() else {}
    remote_locale = {'pt-BR': 'pt_BR', 'zh-CN': 'zh_CN', 'zh-TW': 'zh_TW'}.get(locale, locale)
    shared_url = f'https://raw.githubusercontent.com/Laravel-Lang/lang/dfd5466ebdab22a01b3587bcdac2f105fd3ce597/locales/{remote_locale}/json.json'
    shared = json.load(urllib.request.urlopen(shared_url, timeout=60))
    if not isinstance(shared, dict) or not all(isinstance(k, str) and isinstance(v, str) for k, v in shared.items()):
        raise RuntimeError('Invalid shared translation data')
    download_model(args.model_directory)
    import ctranslate2
    import sentencepiece
    from opencc import OpenCC
    sp = sentencepiece.SentencePieceProcessor(model_file=str(args.model_directory / 'spiece.model'))
    translator = ctranslate2.Translator(str(args.model_directory), device='cpu', compute_type='int8', intra_threads=min(4, os.cpu_count() or 1), inter_threads=1)
    traditional = OpenCC('s2twp') if locale == 'zh-TW' else None
    target = {'pt-BR': 'pt', 'zh-CN': 'zh', 'zh-TW': 'zh'}.get(locale, locale)
    work, parts = [], {}
    started = time.monotonic()
    with output.open('a', encoding='utf8') as stream:
        def save(record):
            stream.write(json.dumps(record, ensure_ascii=False) + '\n')
            done[record['en']] = record
        for item in data['items']:
            en = item['en']
            if en in done:
                continue
            memory = data['memory'][locale].get(en, [])
            manual = reviewed.get(en, {}).get(locale)
            if manual is not None:
                save({'en': en, 'translation': manual, 'kind': 'reviewed-terminology'})
            elif en in INVARIANTS or not re.search('[A-Za-z]{2}', PARAMETER.sub('', en)):
                save({'en': en, 'translation': en, 'kind': 'invariant'})
            elif len(memory) == 1:
                save({'en': en, 'translation': memory[0], 'kind': 'existing'})
            elif en in shared:
                save({'en': en, 'translation': shared[en], 'kind': 'shared-software-translation'})
            else:
                segments = re.split(r'(\n+|(?<=[.!?])\s+(?=[A-Z]))', en)
                parts[en] = [segment if not segment.strip() else None for segment in segments]
                for index, segment in enumerate(segments):
                    if not segment.strip():
                        continue
                    safe, replacements = protect(segment)
                    # Encoding the entire prefix is required: sentencepiece emits
                    # a leading whitespace token before the target-language token.
                    tokens = sp.encode(f'<2{target}> {safe}', out_type=str) + ['</s>']
                    work.append((en, index, segment, replacements, tokens))
        stream.flush()
        work.sort(key=lambda item: len(item[-1]))
        raw_parts = {}
        for offset in range(0, len(work), 64):
            batch = work[offset:offset + 64]
            results = translator.translate_batch([item[-1] for item in batch], beam_size=1, max_decoding_length=192, max_input_length=0, max_batch_size=1024, batch_type='tokens')
            for (en, index, source, replacements, _), result in zip(batch, results):
                raw = sp.decode(result.hypotheses[0])
                value = restore(raw, replacements, source)
                if len(result.hypotheses[0]) >= 192 or (value and len(source) > 25 and len(value) < len(source) * 0.12):
                    value = None
                if traditional and value is not None:
                    value = traditional.convert(value)
                parts[en][index] = (value, raw)
                raw_parts[en] = True
            for en in list(raw_parts):
                if any(part is None for part in parts[en]):
                    continue
                valid = all(isinstance(part, str) or part[0] is not None for part in parts[en])
                value = ''.join(part if isinstance(part, str) else part[0] for part in parts[en]) if valid else None
                raw = ''.join(part if isinstance(part, str) else part[1] for part in parts[en])
                save({'en': en, 'translation': value, 'raw': raw, 'kind': 'machine-assisted' if valid else 'needs-repair'})
                del raw_parts[en]
            stream.flush()
            if offset % 256 == 0:
                print(f'{locale}: {len(done)}/{len(data["items"])} sources; {time.monotonic() - started:.1f}s', flush=True)
    report = {'locale': locale, 'inputSha256': source_hash, 'model': MODEL, 'revision': REVISION, 'sources': len(done), 'needsRepair': sum(record['translation'] is None for record in done.values()), 'status': 'draft-requires-review'}
    (args.output / f'{locale}-report.json').write_text(json.dumps(report, indent=2) + '\n')
    print(json.dumps(report), flush=True)


if __name__ == '__main__':
    main()
