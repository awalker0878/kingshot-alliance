#!/usr/bin/env python3
"""Temporary PR163 source migration. No test runner, application or provider execution."""
import argparse
import hashlib
import json
import os
from pathlib import Path, PurePosixPath as P
import posixpath
import re
import subprocess
import urllib.request

TIERS = ['Unit', 'Feature', 'Integration', 'Architecture', 'Frontend']
STAGES = ['Browser', 'ReadModels', 'Contexts/Accounts', 'Contexts/Alliance',
          'Contexts/GameWorld', 'Contexts/Operations', 'Contexts/Intelligence',
          'Contexts/Communications', 'Contexts/Platform', 'Workflows', 'Shared', 'System']
HISTORICAL = {'docs/codebase/test-organization-progress.md'}

def destination(path):
    parts = list(P(path).parts)
    if len(parts) < 3 or parts[0] != 'tests' or parts[1] not in TIERS + ['Browser']:
        return path
    tier, tail = parts[1], parts[2:]
    special = {'tests/Architecture/Contexts/README.md':'tests/Contexts/README.md',
               'tests/Architecture/ReadModels/README.md':'tests/ReadModels/README.md',
               'tests/Browser/README.md':'docs/codebase/browser-testing.md'}
    if path in special:
        return special[path]
    if tier == 'Browser' and tail[0] == '__screenshots__':
        index = next(i for i, p in enumerate(tail) if p.endswith('.spec.ts'))
        spec = P(destination('tests/Browser/'+'/'.join(tail[1:index+1])))
        return str(spec.parent/'__screenshots__'/spec.name/'/'.join(tail[index+1:]))
    qualifier = []
    if tail[0] == 'Concurrency':
        qualifier, tail = ['Concurrency'], tail[1:]
    if tail[0] == 'Contexts':
        cut = 3
    elif tail[0] in ['ReadModels', 'Workflows']:
        cut = 2
    elif tail[0] == 'Shared':
        if tier == 'Browser':
            return 'tests/Shared/ApplicationShell/Browser/'+'/'.join(tail[1:])
        cut = len(tail)-1
    elif tail[0] == 'Acceptance':
        return '/'.join(['tests', 'System', 'Acceptance', tier]+tail[1:])
    elif tier in ['Architecture', 'Frontend']:
        return '/'.join(['tests', 'System', tier]+tail)
    else:
        raise RuntimeError('Unknown owner: '+path)
    if len(tail) <= cut:
        raise RuntimeError('Missing owner: '+path)
    return '/'.join(['tests']+tail[:cut]+[tier]+qualifier+tail[cut:])

def stage_for(old, new):
    if old.startswith('tests/Browser/'):
        return 'Browser'
    if new.startswith('tests/Contexts/') and not new.endswith('README.md'):
        return '/'.join(new.split('/')[1:3])
    for owner in ['ReadModels', 'Workflows', 'Shared']:
        if new.startswith('tests/'+owner+'/'):
            return owner
    return 'System'

def blob(data):
    return hashlib.sha1(b'blob '+str(len(data)).encode()+b'\0'+data).hexdigest()

def command(*args):
    return subprocess.check_output(args, text=True).strip()

def path_pairs(mapping):
    pairs = dict(mapping)
    directories = {}
    for old,new in mapping.items():
        if old.endswith('Test.php') or old.endswith('.spec.ts'):
            directories.setdefault(str(P(old).parent), set()).add(str(P(new).parent))
    for old, targets in directories.items():
        if len(targets) == 1:
            pairs[old] = next(iter(targets))
    return sorted(pairs.items(), key=lambda x:len(x[0]), reverse=True)

def class_pairs(mapping, original):
    pairs = []
    for old,new in mapping.items():
        if old.endswith('Test.php'):
            source = original[old].decode()
            ns = re.search(r'^namespace ([^;]+);', source, re.M)
            name = re.search(r'^final class (\w+)', source, re.M)
            if ns and name:
                old_class = ns[1]+'\\'+name[1]
                new_class = 'Tests\\'+'\\'.join(P(new).parts[1:-1])+'\\'+name[1]
                pairs.extend([(old_class, new_class), (old_class.replace('\\','\\\\'),new_class.replace('\\','\\\\'))])
    return sorted(pairs,key=lambda x:len(x[0]),reverse=True)

_RULES = {}

def replace_refs(source, mapping, original):
    key = tuple(mapping.items())
    if key not in _RULES:
        _RULES[key] = (path_pairs(mapping), [(re.compile(re.escape(old)+r'(?![A-Za-z0-9_])'), new) for old,new in class_pairs(mapping, original)])
    paths, classes = _RULES[key]
    for old,new in paths:
        source = source.replace(old,new)
    for pattern,new in classes:
        source = pattern.sub(lambda m:new, source)
    return source

def relocate_php(old, new, source):
    if old == new:
        return source
    source = re.sub(r'^namespace ([^;]+);', lambda m:'namespace Tests\\'+'\\'.join(P(new).parts[1:-1])+';', source, count=1, flags=re.M)
    def ancestor(match):
        levels = int(match[1])
        old_parts = list(P(old).parent.parts)
        target = old_parts[:-levels] if levels else old_parts
        new_parts = list(P(new).parent.parts)
        if new_parts[:len(target)] != target:
            raise RuntimeError('Relative ancestor needs explicit review: '+old+' '+match[0])
        count = len(new_parts)-len(target)
        if count < 1:
            raise RuntimeError('Invalid ancestor depth: '+old)
        return 'dirname(__DIR__, '+str(count)+')'
    return re.sub(r'dirname\(__DIR__,\s*(\d+)\)', ancestor, source)

def markdown_links(source, old_path, new_path, mapping):
    def link(match):
        target = match[1]
        if re.match(r'^[a-zA-Z][\w+.-]*:',target) or target.startswith(('#','/')):
            return match[0]
        name, marker, anchor = target.partition('#')
        absolute = posixpath.normpath(str(P(old_path).parent/name))
        mapped = mapping.get(absolute, absolute)
        relative = posixpath.relpath(mapped, str(P(new_path).parent))
        return ']('+relative+(marker+anchor if marker else '')+')'
    return re.sub(r'\]\(([^\s)]+)\)',link,source)

def mutable(path):
    return path.startswith(('tests/','scripts/','.github/workflows/','docs/')) or path in ['phpunit.xml','playwright.config.ts']

def apply_files(previous, current):
    for path in previous.keys()-current.keys():
        Path(path).unlink()
    for path,data in current.items():
        if previous.get(path) != data:
            target=Path(path);target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(data)
    for directory in sorted(Path('tests').rglob('*'),key=lambda p:len(p.parts),reverse=True):
        if directory.is_dir() and not any(directory.iterdir()):
            directory.rmdir()

def api_tree(base, elements):
    repository=os.environ['GITHUB_REPOSITORY']
    if repository!='awalker0878/kingshot-alliance':
        raise RuntimeError('Unexpected repository')
    request=urllib.request.Request('https://api.github.com/repos/'+repository+'/git/trees',
       data=json.dumps({'base_tree':base,'tree':elements}).encode(),method='POST',
       headers={'Authorization':'Bearer '+os.environ['OWNER_LAYOUT_TOKEN'],
                'Accept':'application/vnd.github+json','X-GitHub-Api-Version':'2022-11-28'})
    with urllib.request.urlopen(request,timeout=90) as response:
        return json.load(response)['sha']

def main():
    parser=argparse.ArgumentParser()
    parser.add_argument('--publish-trees',action='store_true')
    parser.add_argument('--output',required=True)
    args=parser.parse_args()
    output=Path(args.output).resolve();output.mkdir(parents=True,exist_ok=True)
    php=os.environ.get('OWNER_LAYOUT_PHP','php')
    tracked=subprocess.check_output(['git','ls-files','-z']).decode().split('\0')[:-1]
    original={p:Path(p).read_bytes() for p in tracked}
    state=dict(original)
    modes={line.split('\t')[1]:line.split()[0] for line in command('git','ls-files','--stage').splitlines()}
    mapping={p:destination(p) for p in tracked if p.startswith('tests/')}
    mapping={o:n for o,n in mapping.items() if o!=n}
    if len(set(mapping.values()))!=len(mapping) or any(n in original for n in mapping.values()):
        raise RuntimeError('Destination collision')
    php_files=[p for p in original if p.startswith('tests/') and p.endswith('Test.php')]
    specs=[p for p in original if p.startswith('tests/') and p.endswith('.spec.ts')]
    pngs=[p for p in original if p.startswith('tests/') and p.endswith('.png')]
    if (len(php_files),len(specs),len(pngs))!=(287,17,12):
        raise RuntimeError('Source inventory changed; review the new revision')
    base_tree=command('git','write-tree')
    receipt={'source_tree':base_tree,'source_revision':os.environ.get('OWNER_LAYOUT_SOURCE_SHA',command('git','rev-parse','HEAD')),
             'php_version':command(php,'-v').splitlines()[0], 'tests_executed':False,'runner_discovery_executed':False,'stages':[]}
    completed={}
    for stage in STAGES:
        step={o:n for o,n in mapping.items() if stage_for(o,n)==stage}
        if not step:continue
        previous=dict(state)
        completed.update(step)
        state={step.get(p,p):data for p,data in state.items()}
        for old,new in step.items():
            if old.endswith('.php'):
                state[new]=relocate_php(old,new,state[new].decode()).encode()
            modes[new]=modes[old]
        inverse={n:o for o,n in step.items()}
        for path,data in list(state.items()):
            if not mutable(path) or path in HISTORICAL or path.endswith('.json'):
                continue
            try:source=data.decode()
            except UnicodeDecodeError:continue
            if path.endswith('.md'):
                source=markdown_links(source,inverse.get(path,path),path,step)
            source=replace_refs(source,step,original)
            state[path]=source.encode()
        if stage=='Browser':
            source=state['playwright.config.ts'].decode()
            source=source.replace("testDir: './tests/Browser',", "testDir: './tests',\n  testMatch: '**/Browser/**/*.spec.ts',")
            source=source.replace('{testDir}/__screenshots__/{testFilePath}/{projectName}/{arg}{ext}',
                                  '{testDir}/{testFileDir}/__screenshots__/{testFileName}/{projectName}/{arg}{ext}')
            state['playwright.config.ts']=source.encode()
        if stage=='Contexts/Intelligence':
            path='.github/workflows/intelligence-verification.yml'
            source=state[path].decode()
            for tier in ['Architecture','Feature','Integration','Unit']:
                source=source.replace('tests/'+tier+'/Contexts/Intelligence','tests/Contexts/Intelligence')
            seen=set();lines=[]
            for line in source.splitlines():
                if 'tests/Contexts/Intelligence' in line:
                    key=line.strip()
                    if key in seen:continue
                    seen.add(key)
                lines.append(line)
            state[path]=('\n'.join(lines)+'\n').encode()
        if stage=='System':
            for path in ['scripts/sync-test-suites.php','scripts/verify-test-layout.php']:
                source=state[path].decode().replace('    // Transitional support is removed when the owner-first migration is complete.\n','')
                source=source.replace('testLayoutInventory($root, allowLegacy: true)','testLayoutInventory($root)')
                if path.endswith('verify-test-layout.php'):
                    source=source.replace("foreach (['v2', 'v3'] as $legacy)","foreach (array_merge(['v2', 'v3', 'Browser'], $suites) as $legacy)")
                state[path]=source.encode()
            source=state['scripts/test-layout.php'].decode().replace('string $root, bool $allowLegacy = false','string $root').replace('default => $allowLegacy && $position === 1,','default => false,')
            state['scripts/test-layout.php']=source.encode()
        # Every PHP test must still be exactly its original source after only
        # namespace, ancestor-depth and known test-reference path transformations.
        for old in php_files:
            new=completed.get(old,old)
            expected=replace_refs(relocate_php(old,new,original[old].decode()),completed,original).encode()
            if state[new]!=expected:
                raise RuntimeError('Unexpected PHP test-content change: '+new)
        for old in specs+pngs:
            if state[completed.get(old,old)]!=original[old]:
                raise RuntimeError('Browser source/baseline changed: '+old)
        if stage=='System':
            manifest={'source_revision':'b901f527c4fb533c29ff708af8ebc8da54c7f0a5',
                      'php_test_files':287,'browser_spec_files':17,'snapshot_files':12,
                      'tests_executed':False,'runner_discovery_executed':False,
                      'allowed_php_changes':['matching namespace','equivalent ancestor depth','known test references'],
                      'files':[{'old_path':o,'new_path':n,'source_blob':blob(original[o]),'result_blob':blob(state[n])} for o,n in sorted(mapping.items())]}
            path='docs/codebase/test-owner-first-migration.json'
            state[path]=(json.dumps(manifest,indent=2)+'\n').encode();modes[path]='100644'
        apply_files(previous,state)
        print(command(php,'scripts/sync-test-suites.php'),flush=True)
        state['phpunit.xml']=Path('phpunit.xml').read_bytes()
        print(command(php,'scripts/verify-test-layout.php'),flush=True)
        changed=sorted(p for p in previous.keys()|state.keys() if previous.get(p)!=state.get(p))
        if any(not mutable(p) for p in changed):
            raise RuntimeError('Out-of-scope file mutation')
        linted=[]
        for path in changed:
            if path in state and path.endswith('.php'):
                command(php,'-l',path);linted.append(path)
        subprocess.run(['git','add','-A'],check=True)
        subprocess.run(['git','diff','--cached','--check'],check=True)
        expected_tree=command('git','write-tree')
        elements=[]
        for path in changed:
            element={'path':path,'mode':modes.get(path,'100644'),'type':'blob'}
            if path not in state:element['sha']=None
            else:
                try:element['content']=state[path].decode()
                except UnicodeDecodeError:element['sha']=blob(state[path])
            elements.append(element)
        (output/(str(len(receipt['stages'])+1)+'-tree.json')).write_text(json.dumps({'base_tree':base_tree,'tree':elements}))
        if args.publish_trees:
            actual_tree=api_tree(base_tree,elements)
            if actual_tree!=expected_tree:
                raise RuntimeError('Published tree differs from prepared index: '+stage)
        base_tree=expected_tree
        entry={'stage':stage,'tree_sha':expected_tree,'moved_files':len(step),'changed_files':len(changed),
               'php_linted':len(linted),'php_source_files':287,'browser_specs':17,'snapshots':12,
               'source_transform_reconciled':True,'browser_blobs_unchanged':True}
        receipt['stages'].append(entry)
        (output/'owner-migration-receipt.json').write_text(json.dumps(receipt,indent=2)+'\n')
        print(json.dumps(entry),flush=True)
    # Remaining tier-first references must be only deliberate historical/negative checks.
    (output/'final-tree.txt').write_text(command('git','ls-files','--stage')+'\n')
    print('SOURCE PREPARATION COMPLETE; no tests, discovery, migrations, refs or commits executed.',flush=True)

if __name__=='__main__':main()
