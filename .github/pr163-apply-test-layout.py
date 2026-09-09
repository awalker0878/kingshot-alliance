from pathlib import Path
import re,json,hashlib,subprocess,collections
r=Path.cwd()
by={}
for p in (r/'tests/v3').rglob('*Test.php'):
 source=p.read_text()
 by[p.relative_to(r).as_posix()]={'base':'PHPUnit\\Framework\\TestCase' if 'use PHPUnit\\Framework\\TestCase;' in source else 'Laravel', 'trait':'Migration' if 'use DatabaseMigrations;' in source else 'Other'}
assert len(by)==283, 'Wrong source inventory'

arch={'AllianceContentGameParityHttpContractV3Test','GovernorProgressionEvidenceReferenceBoundaryV3Test','PublishedIntegrationContractV3Test'}
frontend={'TransferManualEvidenceUxV3Test'}
infra={'InfrastructureBehaviorV3Test','CacheNamespaceIsolationV3Test','KingdomAllianceCanonicalIdentityIntegrationV3Test','TerritoryEventRevisionIntegrationV3Test'}
feature_commits={'GoogleAuthenticationV3Test','PasswordProofThrottleV3Test'}
mapping={}; rows=[]
for p in sorted((r/'tests/v3').rglob('*')):
 if not p.is_file(): continue
 old=p.relative_to(r).as_posix(); rel=p.relative_to(r/'tests/v3').as_posix(); stem=p.stem
 s=p.read_text() if p.suffix in {'.php','.json'} else ''
 if rel=='TestCase.php': dest='tests/TestCase.php'; reason='Shared Laravel base; cache and worker isolation unchanged.'
 elif rel.startswith(('Support/','Fixtures/','Architecture/','Frontend/')):
  dest='tests/'+rel; reason=rel.split('/')[0]+' support or existing contract.'
 elif stem in arch:
  dest='tests/Architecture/'+rel; reason='Read source, routes or reflection/DI structure; no business mutation.'
 elif stem in frontend:
  dest='tests/Frontend/'+rel; reason='Vue source and accessible input contract; no browser execution.'
 elif old in by and by[old]['base']=='PHPUnit\\Framework\\TestCase':
  dest='tests/Unit/'+rel; reason='Pure PHPUnit logic or inert fixture contract; no Laravel boot.'
 elif old in by and by[old]['trait']=='Migration' and stem not in feature_commits:
  multi='setDefaultConnection' in s or 'lock_timeout' in s
  dest='tests/Integration/'+('Concurrency/' if multi else '')+rel
  reason='Competing real PostgreSQL connections and lock/commit semantics.' if multi else 'Committed-state, after-commit or lifecycle atomicity contract; no outer test transaction.'
 elif stem in infra:
  dest='tests/Integration/'+rel; reason='Real persistence, canonical adapter or shared infrastructure boundary.'
 else:
  assert old in by,old
  dest='tests/Feature/'+rel; reason='Booted Laravel application/HTTP/read-model behavior; existing isolation retained.'
 assert not (r/dest).exists(),(old,dest)
 assert dest not in mapping.values(),dest
 mapping[old]=dest
 content=p.read_bytes(); blob=hashlib.sha1(b'blob '+str(len(content)).encode()+b'\0'+content).hexdigest()
 rows.append({'old_path':old,'new_path':dest,'source_blob':blob,'reason':reason})
assert len([p for p in mapping if p.endswith('Test.php')])==283
print(collections.Counter(v.split('/')[1] for k,v in mapping.items() if k.endswith('Test.php')))
# Each class namespace is rewritten according to its own destination; imports follow full symbols.
syms={}
for old,new in mapping.items():
 if old.endswith('.php'):
  src=(r/old).read_text(); m=re.search(r'^namespace ([^;]+);',src,re.M)
  if m:
   name=re.search(r'^(?:(?:final|abstract) )?(?:class|trait|interface|enum) (\w+)',src,re.M)
   if name:
    oldname=m[1]+'\\'+name[1]; newname=str(Path(new).with_suffix('')).replace('/','\\'); newname='Tests'+newname[5:]
    assert newname.endswith('\\'+name[1]),(old,new)
    syms[oldname]=newname
for old,new in mapping.items():
 p=r/old; q=r/new; q.parent.mkdir(parents=True,exist_ok=True)
 if p.suffix=='.php':
  src=p.read_text(); old_depth=len(Path(old).parent.parts); new_depth=len(Path(new).parent.parts)
  if re.search(r'^namespace ',src,re.M):
   ns='Tests\\'+'\\'.join(Path(new).parent.parts[1:])
   src=re.sub(r'^namespace [^;]+;',lambda m:'namespace '+ns.rstrip('\\')+';',src,count=1,flags=re.M)
  # Preserve the resolved directory for every numeric dirname(__DIR__, N) expression.
  src=re.sub(r'dirname\(__DIR__, (\d+)\)',lambda m:f'dirname(__DIR__, {int(m[1])+new_depth-old_depth})',src)
  q.write_text(src);p.unlink()
 else:p.rename(q)
for p in sorted((r/'tests/v3').rglob('*'),reverse=True):
 if p.is_dir():p.rmdir()
(r/'tests/v3').rmdir()
# Rewrite current executable references and current documentation. Historical plan/progress stays explicit.
tracked=subprocess.check_output(['git','ls-files','-z'],cwd=r).decode().split('\0')
paths={r/p for p in tracked if p and (r/p).is_file()}|{r/p for p in mapping.values()}
historical={'docs/codebase/test-organization-and-performance-plan.md','docs/codebase/test-organization-progress.md'}
for p in paths:
 if p.relative_to(r).as_posix() in {'.github/pr163-apply-test-layout.py','.github/workflows/pr163-prepare-tree.yml'}:continue
 try:src=p.read_text()
 except UnicodeDecodeError:continue
 original=src
 for a,b in sorted(syms.items(),key=lambda x:-len(x[0])):
  # In literal class names and in escaped PHP/JSON strings.
  src=src.replace(a.replace('\\','\\\\'),b.replace('\\','\\\\')).replace(a,b)
 if p.relative_to(r).as_posix() not in historical:
  for a,b in sorted(mapping.items(),key=lambda x:-len(x[0])):src=src.replace(a,b)
  for a,b in [('tests/v3/Architecture','tests/Architecture'),('tests/v3/Frontend','tests/Frontend'),('tests/v3/Fixtures','tests/Fixtures'),('tests/v3/Support','tests/Support')]:src=src.replace(a,b)
  # Remaining directory selections (capability gates), not arbitrary per-file classifications.
  for cap in ['Contexts/GameWorld/GiftCodes','Contexts/Operations/KingPerks','Contexts/GameWorld/KingdomMaps']:
   src=src.replace('tests/v3/'+cap,'tests/Feature/'+cap)
  src=src.replace("'tests/v3/**'","'tests/**'")
  src=src.replace('database routes tests/v3 ','database routes tests ')
  src=src.replace("$root.'/tests/v3'","$root.'/tests'")
  # Correct an already stale workflow test reference rather than retaining it.
  src=src.replace('tests/v3/ReadModels/NotificationDelivery/NotificationQueueDeliveryV3Test.php','tests/Feature/Workflows/NotificationDelivery/NotificationQueueDeliveryV3Test.php')
 for kind in ['Support','Fixtures']:
  src=src.replace('Tests\\v3\\'+kind+'\\','Tests\\'+kind+'\\')
 if src!=original:p.write_text(src)
# Replace suite topology checks, retaining all unrelated source and behavior invariants.
p=r/'phpunit.xml';src=p.read_text();src=re.sub(r'    <testsuites>.*?    </testsuites>',
 '    <testsuites>\n'+''.join(f'        <testsuite name="{n}">\n            <directory suffix="Test.php">tests/{n}</directory>\n        </testsuite>\n' for n in ['Unit','Feature','Integration','Architecture','Frontend'])+'    </testsuites>',src,flags=re.S);p.write_text(src)
p=r/'tests/Architecture/verify-behavior-contracts.php';src=p.read_text();start=src.index("$phpunit =");end=src.index('$legacyFiles =')
src=src[:start]+"require $root.'/scripts/verify-test-layout.php';\n\n"+src[end:];p.write_text(src)
p=r/'tests/Architecture/verify-final-source.php';src=p.read_text();start=src.index("$phpunit =");end=src.index('$visualSpec =')
src=src[:start]+"require $root.'/scripts/verify-test-layout.php';\n\n"+src[end:];p.write_text(src)
# Human-auditable exact manifest. Not a future filename-based classifier.
p=r/'docs/codebase/test-layout-migration.json'
p.write_text(json.dumps({'source_commit':'5760d3e4c01ceb5ec6ddec46c170ca80e952f345','php_classes':283,'php_cases':1482,'browser_cases':62,'files':rows},indent=2)+'\n')

p=r/'.github/workflows/intelligence-verification.yml';src=p.read_text()
src=src.replace('tests/v3/Contexts/Intelligence','tests/Architecture/Contexts/Intelligence \\\n            tests/Feature/Contexts/Intelligence \\\n            tests/Integration/Contexts/Intelligence \\\n            tests/Unit/Contexts/Intelligence')
for name in ['EventAnalysis','KingdomIntelligence','IntelligenceSignals']:
 src=src.replace('tests/v3/ReadModels/'+name,'tests/Feature/ReadModels/'+name)
p.write_text(src)
p=r/'composer.json';p.write_text(p.read_text().replace('"@lint:check",\n            "@types:check",','"@php scripts/verify-test-layout.php",\n            "@lint:check",\n            "@types:check",'))
# Preparation machinery never becomes part of the proposed application tree.
for relative in ['.github/pr163-apply-test-layout.py','.github/workflows/pr163-prepare-tree.yml']:
 (r/relative).unlink(missing_ok=True)
