import { createHash } from 'node:crypto';
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

export const hash = (v) => createHash('sha256').update(v, 'utf8').digest('hex');
const hex = /^[0-9a-f]{64}$/;
const commit = /^[0-9a-f]{40}$/;
const localeCodes = new Set(['ar','de','es','fr','id','it','ja','ko','pl','pt-BR','ru','th','tr','vi','zh-CN','zh-TW']);
export const id = (r) => [r.catalogue,r.domain,r.key,r.locale].join(':');
export const binding = (r) => hash(JSON.stringify([
  'kingshot-native-review-v1',r.catalogue,r.domain,r.key,r.locale,
  r.sourceSha256,r.translationSha256,r.reviewer.toLowerCase(),
  r.reviewedPr,r.reviewedCommitSha,
]));
export function validate(r) {
  if (!r || typeof r !== 'object' || Array.isArray(r)) throw Error('invalid record');
  if (r.version !== 1 || r.decision !== 'approved' || r.scope !== 'native-translation') throw Error('unapproved record');
  if (!['client','server'].includes(r.catalogue) || typeof r.domain !== 'string' || !r.domain || typeof r.key !== 'string' || !r.key) throw Error('invalid message identity');
  if (!localeCodes.has(r.locale)) throw Error('unknown locale');
  if (typeof r.reviewer !== 'string' || !/^[a-z0-9-]{1,39}$/i.test(r.reviewer)) throw Error('unknown reviewer identity');
  if (!Number.isSafeInteger(r.reviewedPr) || r.reviewedPr < 1 || !Number.isSafeInteger(r.reviewId) || r.reviewId < 1) throw Error('invalid review reference');
  if (!commit.test(r.reviewedCommitSha)) throw Error('invalid review head SHA');
  for (const k of ['sourceSha256','translationSha256','reviewBindingSha256']) if (!hex.test(r[k])) throw Error('invalid digest: ' + k);
  if (r.reviewBindingSha256 !== binding(r)) throw Error('unsigned or altered review record');
}
export function values(r, exported) {
  let source,translation;
  if (r.catalogue === 'client') {
    source = exported.client[r.domain]?.en?.[r.key];
    translation = exported.client[r.domain]?.[r.locale]?.[r.key];
  } else if (r.domain === 'json') {
    source = exported.server.en?.[r.key];
    translation = exported.server[r.locale]?.[r.key];
  } else if (r.domain === 'accounts' && /^accounts\.php#[1-9]\d*$/.test(r.key)) {
    const i = Number(r.key.slice(13)) - 1;
    source = exported.php.en?.['accounts.php']?.[i]?.value;
    translation = exported.php[r.locale]?.['accounts.php']?.[i]?.value;
  }
  if (typeof source !== 'string' || typeof translation !== 'string') throw Error('unresolvable source/translation: ' + id(r));
  return {source,translation};
}
export async function githubReview(r) {
  const token=process.env.GITHUB_TOKEN,repo=process.env.GITHUB_REPOSITORY;
  if (!token || !/^[\w.-]+\/[\w.-]+$/.test(repo ?? '')) throw Error('GitHub authenticated review lookup unavailable');
  const response=await fetch('https://api.github.com/repos/'+repo+'/pulls/'+r.reviewedPr+'/reviews/'+r.reviewId,{
    headers:{Authorization:'Bearer '+token,Accept:'application/vnd.github+json','X-GitHub-Api-Version':'2022-11-28','User-Agent':'kingshot-native-review-provenance'},
  });
  if (!response.ok) throw Error('GitHub review check: HTTP '+response.status);
  const review=await response.json();
  return review.state==='APPROVED' && review.user?.login?.toLowerCase()===r.reviewer.toLowerCase()
    && review.commit_id===r.reviewedCommitSha && typeof review.submitted_at==='string'
    && typeof review.body==='string'
    && review.body.split(/\r?\n/).includes('Approval-Record-SHA256: '+r.reviewBindingSha256);
}
export async function check(r,exported,reviewVerifier=githubReview) {
  validate(r);
  const v=values(r,exported);
  if (hash(v.source)!==r.sourceSha256 || hash(v.translation)!==r.translationSha256) throw Error('stale translation/source: '+id(r));
  if (await reviewVerifier(r)!==true) throw Error('missing matching GitHub APPROVED review: '+id(r));
  return id(r);
}
export async function main(root=process.cwd()) {
  const directory=path.join(root,'resources/data/localization/reviews');
  const names=existsSync(directory)?readdirSync(directory).filter(n=>n.endsWith('.json')).sort():[];
  const exported=(n)=>JSON.parse(readFileSync(path.join(root,'.translation-pr6-current',n),'utf8'));
  const data={client:exported('effective-all-locales.json'),server:exported('server-catalogues.json'),php:exported('server-php-catalogues.json')};
  const seen=new Set();
  for (const name of names) {
    const r=JSON.parse(readFileSync(path.join(directory,name),'utf8'));
    validate(r);
    if (name!==hash(id(r))+'.json' || seen.has(id(r))) throw Error('duplicate or incorrectly named approval: '+id(r));
    seen.add(id(r));
    await check(r,data);
  }
  console.log('NATIVE PROVENANCE INTEGRITY: '+JSON.stringify({
    verifiedApprovalRecords:seen.size,scope:'record integrity only',
    nativeApprovalCoverageEstablished:false,
    message:'An empty registry means ZERO sign-offs, not approved translations.',
  }));
}
if (process.argv[1] && import.meta.url===pathToFileURL(path.resolve(process.argv[1])).href) {
  main().catch(e=>{console.error(e);process.exitCode=1;});
}
