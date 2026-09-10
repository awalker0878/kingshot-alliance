#!/usr/bin/env python3
"""PR163 source-only relocation. No test/application imports, commits or ref writes."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import tempfile
import urllib.request

REPOSITORY = 'awalker0878/kingshot-alliance'
SCRIPT = '.github/pr163-stranded-prepare.py'
WORKFLOW = '.github/workflows/pr163-stranded-prepare.yml'
RENAMES = {'PwaContractV3Test': 'PwaOfflinePrivacyContractTest',
           'ActionReceiptBehaviorV3Test': 'ActionResponseContractTest'}

def sha(data):
    return hashlib.sha1(b'blob '+str(len(data)).encode()+b'\0'+data).hexdigest()

def run(*args):
    return subprocess.check_output(args, text=True).strip()

def moves(files):
    contracts = {
      'tests/System/Architecture/PwaContractV3Test.php': 'tests/Shared/ApplicationShell/Architecture/PwaOfflinePrivacyContractTest.php',
      'tests/Shared/Infrastructure/Unit/ActionReceiptBehaviorV3Test.php': 'tests/Shared/Infrastructure/Http/Unit/ActionResponseContractTest.php',
    }
    support = {
      'tests/Support/WebAuthnAssertionFixture.php': 'tests/Contexts/Accounts/Authentication/Support/WebAuthnAssertionFixture.php',
      'tests/Support/WebAuthnRegistrationFixture.php': 'tests/Contexts/Accounts/Authentication/Support/WebAuthnRegistrationFixture.php',
      'tests/Support/RecruitmentCollectionFactory.php': 'tests/ReadModels/RecruitmentManagement/Support/RecruitmentCollectionFactory.php',
      'tests/Fixtures/territory-geometry.json': 'tests/Contexts/GameWorld/KingdomMaps/Fixtures/territory-geometry.json',
    }
    for path in files:
        if path.startswith('tests/Fixtures/Evidence/') and path.endswith('.json'):
            support[path] = path.replace('tests/Fixtures/Evidence/', 'tests/Contexts/Intelligence/Evidence/Fixtures/', 1)
    owners = {
      'AllianceContentGameParityVisualFixture': 'Contexts/Alliance/Content',
      'AllianceRoleVisualFixture': 'Contexts/Alliance/Access',
      'BearHuntDebriefVisualFixture': 'ReadModels/EventAnalysis',
      'CapabilityAcceptanceVisualFixture': 'System/Acceptance',
      'EventCommandVisualFixture': 'ReadModels/EventManagement',
      'GiftCodeVisualFixture': 'ReadModels/GiftCodes',
      'KingdomTransferCurrentRulesVisualFixture': 'Contexts/GameWorld/KingdomTransfers',
      'KingdomTransferVisualFixture': 'Contexts/GameWorld/KingdomTransfers',
      'RecruitmentHistoryVisualFixture': 'ReadModels/RecruitmentManagement',
      'ScreenshotIntakeVisualFixture': 'ReadModels/ScreenshotIntake',
      'TerritoryPlanningVisualFixture': 'ReadModels/TerritoryPlanning',
      'TerritoryReconciliationVisualFixture': 'ReadModels/TerritoryPlanning',
    }
    visual = {'tests/Fixtures/'+name+'.php': 'tests/'+owner+'/Fixtures/'+name+'.php' for name,owner in owners.items()}
    return [('shared-contracts', contracts), ('domain-support-and-corpora', support), ('visual-fixtures', visual)]

def namespace(path):
    return 'Tests\\'+'\\'.join(Path(path).parts[1:-1])

def substitutions(mapping):
    pairs = list(mapping.items())
    for old,new in mapping.items():
        if old.endswith('.php'):
            oldclass=namespace(old)+'\\'+Path(old).stem
            newclass=namespace(new)+'\\'+Path(new).stem
            pairs += [(oldclass,newclass),(oldclass.replace('\\','\\\\'),newclass.replace('\\','\\\\'))]
    if any(p.startswith('tests/Fixtures/Evidence/') for p in mapping):
        pairs += [('tests/Fixtures/Evidence/', 'tests/Contexts/Intelligence/Evidence/Fixtures/')]
    return sorted(pairs, key=lambda p:len(p[0]), reverse=True)

def replace(text,pairs):
    for old,new in pairs:
        text=text.replace(old,new)
    return text

def tracked():
    return subprocess.check_output(['git','ls-files','-z']).decode().split('\0')[:-1]

def write_state(previous,current):
    for path in previous.keys()-current.keys():
        Path(path).unlink()
    for path,data in current.items():
        if previous.get(path)!=data:
            Path(path).parent.mkdir(parents=True,exist_ok=True)
            Path(path).write_bytes(data)
    for path in sorted(Path('tests').rglob('*'),key=lambda p:len(p.parts),reverse=True):
        if path.is_dir() and not any(path.iterdir()):path.rmdir()

def main():
    parser=argparse.ArgumentParser()
    parser.add_argument('--output',required=True)
    parser.add_argument('--publish-trees',action='store_true')
    args=parser.parse_args()
    output=Path(args.output).resolve();output.mkdir(parents=True,exist_ok=True)
    if args.publish_trees and os.environ.get('GITHUB_REPOSITORY')!=REPOSITORY:
        raise RuntimeError('Unexpected repository')
    original={p:Path(p).read_bytes() for p in tracked()}
    modes={line.split('\t')[1]:line.split()[0] for line in run('git','ls-files','--stage').splitlines()}
    state=dict(original)
    suites_before={p:original[p] for p in original if p.startswith('tests/') and p.endswith('Test.php')}
    if len(suites_before)!=287:raise RuntimeError('Inventory changed; review before proceeding')
    php=os.environ.get('SOURCE_PHP','php')
    base_tree=run('git','write-tree')
    receipt={'source_revision':run('git','rev-parse','HEAD'),'php_version':run(php,'-v').splitlines()[0],
             'tests_executed':False,'runner_discovery':False,'stages':[]}
    for label,mapping in moves(original):
        if any(p not in state for p in mapping):raise RuntimeError('Missing source')
        if len(set(mapping.values()))!=len(mapping) or any(p in state for p in mapping.values()):raise RuntimeError('Destination collision')
        previous=dict(state)
        pairs=substitutions(mapping)
        state={mapping.get(p,p):data for p,data in state.items()}
        for old,new in mapping.items():
            modes[new]=modes[old]
            if new.endswith('.php'):
                text=state[new].decode().replace('namespace '+namespace(old)+';', 'namespace '+namespace(new)+';', 1)
                if Path(old).stem in RENAMES:text=text.replace('final class '+Path(old).stem+' ', 'final class '+Path(new).stem+' ', 1)
                state[new]=text.encode()
        for path,data in list(state.items()):
            if path in [SCRIPT,WORKFLOW] or Path(path).suffix not in ['.php','.ts','.mjs','.md','.yml','.yaml']:
                continue
            if not path.startswith(('tests/','scripts/','docs/','.github/workflows/')):continue
            if path.startswith('docs/architecture/adr/') or path=='docs/codebase/test-organization-progress.md':continue
            state[path]=replace(data.decode(),pairs).encode()
        corpus_changes={}
        if label=='domain-support-and-corpora':
            for path,data in list(state.items()):
                if path.startswith('tests/Contexts/Intelligence/Evidence/Unit/') and path.endswith('.php'):
                    old="dirname(__DIR__, 4).'/Fixtures/Evidence/"
                    new="dirname(__DIR__).'/Fixtures/"
                    text=data.decode()
                    if old in text:
                        corpus_changes[path]=(old,new)
                        state[path]=text.replace(old,new).encode()
        added_import=None
        if label=='visual-fixtures':
            added_import='use Tests\\System\\Acceptance\\Fixtures\\CapabilityAcceptanceVisualFixture;\n'
            path=mapping['tests/Fixtures/EventCommandVisualFixture.php']
            text=state[path].decode().replace('use Illuminate\\Support\\Facades\\Hash;\n','use Illuminate\\Support\\Facades\\Hash;\n'+added_import,1)
            state[path]=text.encode()
        # Inverse comparison checks every PHP file and browser specification,
        # including consumers; only reviewed namespace/path/import substitutions are allowed.
        reverse=sorted([(new,old) for old,new in pairs],key=lambda p:len(p[0]),reverse=True)
        checked=0
        for old,data in previous.items():
            if not old.startswith('tests/') or not (old.endswith('.php') or old.endswith('.spec.ts')):continue
            new=mapping.get(old,old)
            text=state[new].decode()
            if added_import and new==mapping.get('tests/Fixtures/EventCommandVisualFixture.php'):
                text=text.replace(added_import,'',1)
            if new in corpus_changes:
                a,b=corpus_changes[new];text=text.replace(b,a)
            text=replace(text,reverse)
            if old in mapping and old.endswith('.php'):
                text=text.replace('namespace '+namespace(new)+';', 'namespace '+namespace(old)+';',1)
                if Path(old).stem in RENAMES:text=text.replace('final class '+Path(new).stem+' ', 'final class '+Path(old).stem+' ',1)
            if text.encode()!=data:raise RuntimeError('Inverse source mismatch: '+new)
            checked+=1
        for old,data in previous.items():
            if old.startswith('tests/') and old.endswith(('.json','.png')) and state[mapping.get(old,old)]!=data:
                raise RuntimeError('Fixture data or image changed: '+old)
        write_state(previous,state)
        print(run(php,'scripts/sync-test-suites.php'),flush=True)
        state['phpunit.xml']=Path('phpunit.xml').read_bytes()
        print(run(php,'scripts/verify-test-layout.php'),flush=True)
        changed=sorted(p for p in previous.keys()|state.keys() if previous.get(p)!=state.get(p))
        for p in changed:
            if not (p.startswith(('tests/','docs/','scripts/','.github/workflows/')) or p=='phpunit.xml'):
                raise RuntimeError('Out of scope mutation: '+p)
        linted=[p for p in changed if p in state and p.endswith('.php')]
        for p in linted:run(php,'-l',p)
        subprocess.run(['git','add','-A'],check=True)
        subprocess.run(['git','diff','--cached','--check'],check=True)
        elements=[]
        for p in changed:
            e={'path':p,'mode':modes.get(p,'100644'),'type':'blob'}
            if p not in state:e['sha']=None
            elif p.endswith(('.json','.png')) and sha(state[p]) in {sha(d) for d in original.values()}:
                e['sha']=sha(state[p])
            else:e['content']=state[p].decode()
            if not p.startswith('.github/workflows/'):elements.append(e)
        workflows=[{'path':p,'mode':modes[p],'type':'blob','content':state[p].decode()} for p in state
                   if p.startswith('.github/workflows/') and state[p]!=original.get(p)]
        expected=run('git','write-tree')
        with tempfile.TemporaryDirectory() as temporary:
            env={**os.environ,'GIT_INDEX_FILE':str(Path(temporary)/'index')}
            subprocess.run(['git','read-tree',expected],env=env,check=True)
            for path,data in original.items():
                if path.startswith('.github/workflows/'):
                    subprocess.run(['git','update-index','--cacheinfo',modes[path],sha(data),path],env=env,check=True)
            permitted=subprocess.check_output(['git','write-tree'],env=env,text=True).strip()
        published=None
        if args.publish_trees:
            request=urllib.request.Request('https://api.github.com/repos/'+REPOSITORY+'/git/trees',
               data=json.dumps({'base_tree':base_tree,'tree':elements}).encode(),method='POST',
               headers={'Authorization':'Bearer '+os.environ['SOURCE_TREE_TOKEN'],'Accept':'application/vnd.github+json'})
            with urllib.request.urlopen(request,timeout=60) as response:published=json.load(response)['sha']
            if published!=permitted:raise RuntimeError('Remote source tree mismatch')
            base_tree=published
        entry={'stage':label,'expected_tree':expected,'published_source_tree':published,'workflow_entries':workflows,
               'moves':[{'old_path':o,'new_path':n,'source_blob':sha(previous[o]),'result_blob':sha(state[n])} for o,n in mapping.items()],
               'changed_files':changed,'php_linted':len(linted),'inverse_source_files':checked,
               'php_source_files':sum(p.startswith('tests/') and p.endswith('Test.php') for p in state),
               'browser_specs':sum(p.startswith('tests/') and p.endswith('.spec.ts') for p in state),
               'snapshot_bytes_unchanged':True,'fixture_data_bytes_unchanged':True,
               'result_blobs':{p:sha(state[p]) for p in changed if p in state}}
        receipt['stages'].append(entry)
        (output/'receipt.json').write_text(json.dumps(receipt,indent=2)+'\n')
        print(json.dumps({k:entry[k] for k in ['stage','published_source_tree','php_linted','php_source_files','browser_specs']}),flush=True)
    print('SOURCE ONLY: no tests, discovery, migrations, commits or refs executed.',flush=True)

if __name__=='__main__':main()
