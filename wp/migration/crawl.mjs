#!/usr/bin/env node
// Polite, resumable, strictly sequential HTML fetcher for the old site (www.tilleke.com).
//
//   node wp/migration/crawl.mjs [--delay 10] [--limit N] [--only <type>] [--dry]
//
// Input:  temporary/migration/urls/*.tsv (url, lastmod, images) + EXTRAS below.
// Output: temporary/migration/raw/<file> (same naming as the inventory pass), log raw/_crawl-log.tsv.
// Resume: just run it again. A saved file or a logged "skip" (404/410/not HTML) is never fetched again.
// Types (--only): file names of urls/*.tsv (professional, practices, sub_practices, locations,
//   careers_opportunitie, page, events, practice_category, resource_category, resource) and "extras".
// Test-only: --data <dir> (urls/ + raw/ root), --origin <http://127.0.0.1:port> (rewrites the host;
//   only then may --backoff go below 300 s), --timeout <s>.
import fs from 'node:fs';
import path from 'node:path';
import { createHash } from 'node:crypto';

const argv = process.argv.slice(2);
const arg = (k, d) => { const i = argv.indexOf('--' + k); return i < 0 ? d : argv[i + 1]; };

const SITE = 'https://www.tilleke.com';
const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 SiteMigration/1.0';
const ORDER = ['professional', 'practices', 'sub_practices', 'locations', 'careers_opportunitie', 'page', 'extras',
  'events', 'practice_category', 'resource_category', 'resource'];
// Not in any sitemap (inventory §1). TranslatePress /ja/ /zh/ copies of English pages are left out on purpose.
const EXTRAS = ['/career-role/legal-professionals/', '/career-role/operations-professionals/',
  '/career-role/internships/', '/fraudulent-communications/'].map(p => SITE + p);

const DATA = path.resolve(arg('data', path.join(import.meta.dirname, '../../temporary/migration')));
const RAW = path.join(DATA, 'raw');
const LOG = path.join(RAW, '_crawl-log.tsv');
const ORIGIN = arg('origin', SITE);
const local = /^http:\/\/(127\.0\.0\.1|localhost)(:\d+)?$/.test(ORIGIN);
if (ORIGIN !== SITE && !local) die('--origin is for a local stub only');
const DELAY = Math.max(10, Number(arg('delay', 10))) * 1000;           // never below 10 s
const BACKOFF = Math.max(local ? 0 : 300, Number(arg('backoff', 300))) * 1000;
const TIMEOUT = Number(arg('timeout', 60)) * 1000;
const LIMIT = Number(arg('limit', Infinity));
const ONLY = arg('only');
const MAX_FAILS = 5, MIN_BYTES = 8000;
const CHALLENGE = /<title>\s*(Just a moment|Attention Required|Checking your browser)|window\._cf_chl_opt|cf-chl-|cf-turnstile/i;

function die(msg) { console.error(msg); process.exit(1); }
const sleep = ms => new Promise(r => setTimeout(r, ms));
const now = () => new Date().toISOString();
// Same scheme the inventory used for raw/: drop the scheme, runs of unsafe chars -> "_", max 180 chars.
// Over 180 the inventory cut bare, which collides on long CJK slugs; here the tail becomes a hash instead.
const plain = u => u.replace(/^https?:\/\//, '').replace(/[^A-Za-z0-9._-]+/g, '_');
const fname = u => { const p = plain(u); return p.length <= 180 ? p : p.slice(0, 170) + '~' + createHash('sha1').update(u).digest('hex').slice(0, 9); };
const legacy = u => plain(u).slice(0, 180);
// Resource URLs without a trailing slash 301 (in PHP) to the slash form: request the canonical form directly.
const canon = u => /\/[^/?.]+$/.test(new URL(u).pathname) && !u.includes('?') ? u + '/' : u;

// robots.txt, "User-agent: *" group only (the only group that applies to this UA).
const robotsFile = path.join(RAW, 'www.tilleke.com_robots.txt');
if (!fs.existsSync(robotsFile)) die('missing ' + robotsFile);
const rules = []; let star = false;
for (const line of fs.readFileSync(robotsFile, 'utf8').split('\n')) {
  const m = line.replace(/#.*/, '').trim().match(/^([\w-]+)\s*:\s*(.*)$/);
  if (!m) continue;
  const k = m[1].toLowerCase();
  if (k === 'user-agent') star = m[2] === '*';
  else if (star && (k === 'allow' || k === 'disallow') && m[2])
    rules.push({ allow: k === 'allow', len: m[2].length, re: new RegExp('^' + m[2].replace(/[.+?^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*')) });
}
const allowed = u => {
  const p = new URL(u).pathname + new URL(u).search;
  const hit = rules.filter(r => r.re.test(p)).sort((a, b) => b.len - a.len || b.allow - a.allow)[0];
  return !hit || hit.allow;
};

// Build the ordered work list.
const lists = { extras: EXTRAS };
for (const f of fs.readdirSync(path.join(DATA, 'urls')).filter(f => f.endsWith('.tsv')))
  lists[f.slice(0, -4)] = fs.readFileSync(path.join(DATA, 'urls', f), 'utf8').split('\n').slice(1).map(l => l.split('\t')[0].trim()).filter(Boolean);
const types = [...ORDER.filter(t => lists[t]), ...Object.keys(lists).filter(t => !ORDER.includes(t))];
if (ONLY && !lists[ONLY]) die(`--only: unknown type "${ONLY}". Known: ${types.join(', ')}`);

const gone = new Set();  // URLs logged as skip: never fetched again
if (fs.existsSync(LOG)) for (const l of fs.readFileSync(LOG, 'utf8').split('\n')) {
  const c = l.split('\t'); if (c[7]?.startsWith('skip')) gone.add(c[1]);
}
// A bare-cut inventory file name only counts as "saved" when no other URL shares it.
const legacyCount = new Map();
for (const l of Object.values(lists)) for (const u of l)
  for (const n of new Set([legacy(u), legacy(canon(u))])) legacyCount.set(n, (legacyCount.get(n) || 0) + 1);
const isSaved = (url, listed) => [fname(url), fname(listed), ...[legacy(url), legacy(listed)].filter(n => legacyCount.get(n) === 1)]
  .some(f => fs.existsSync(path.join(RAW, f)));
const seen = new Map(), all = [], robotsSkipped = [];
for (const type of types) {
  if (ONLY && type !== ONLY) continue;
  for (const listed of lists[type]) {
    const url = canon(listed), file = fname(url);
    if (seen.has(file)) { if (seen.get(file) !== url) die(`file name collision: ${url} vs ${seen.get(file)}`); continue; }
    seen.set(file, url);
    if (!allowed(url)) { robotsSkipped.push(url); continue; }
    all.push({ type, url, file, saved: isSaved(url, listed) || gone.has(url) });
  }
}
const scoped = all.slice(0, LIMIT);
const todo = scoped.filter(x => !x.saved);

const byType = {};
for (const x of all) { byType[x.type] ??= { listed: 0, todo: 0 }; byType[x.type].listed++; if (!x.saved) byType[x.type].todo++; }
const hrs = ms => (ms / 3600000).toFixed(1) + ' h';
console.log(`${now()} plan: ${todo.length} to fetch (of ${scoped.length} in scope; ${all.length - all.filter(x => !x.saved).length} already saved/skipped overall; ${robotsSkipped.length} robots-disallowed: ${robotsSkipped.map(u => new URL(u).pathname).join(' ')}), delay ${DELAY / 1000}s, est. ${hrs(todo.length * (DELAY + 300))}`);
if (argv.includes('--dry')) { console.table(byType); process.exit(0); }

fs.mkdirSync(RAW, { recursive: true });
if (!fs.existsSync(LOG)) fs.writeFileSync(LOG, 'time\turl\tstatus\tfinal_url\tbytes\tcf_cache\tfile\tnote\n');
const log = (...c) => fs.appendFileSync(LOG, c.join('\t') + '\n');  // first column: request start time

let lastStart = 0, done = 0, ok = 0, skipped = 0, failures = 0;
const t0 = Date.now();
function summary(why) {
  console.log(`${now()} ${why}: ${ok} saved, ${skipped} skipped (404/410/other), ${failures} failed attempts, ${todo.length - done} left, ${hrs(Date.now() - t0)} elapsed. Log: ${LOG}`);
}
process.on('SIGINT', () => { summary('interrupted'); process.exit(130); });
process.on('SIGTERM', () => { summary('terminated'); process.exit(143); });

async function attempt({ url, file }) {
  const wait = lastStart + DELAY - Date.now();
  if (wait > 0) await sleep(wait);
  lastStart = Date.now();
  const t = new Date(lastStart).toISOString();
  let res, buf = Buffer.alloc(0), isHtml = false;
  try {
    res = await fetch(url.replace(SITE, ORIGIN), {
      headers: { 'user-agent': UA, accept: 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', 'accept-language': 'en-US,en;q=0.9' },
      redirect: 'follow', signal: AbortSignal.timeout(TIMEOUT),
    });
    isHtml = (res.headers.get('content-type') || '').includes('html');
    if (isHtml) buf = Buffer.from(await res.arrayBuffer()); else await res.body?.cancel();
  } catch (e) {
    const note = e.name === 'TimeoutError' ? 'fail:timeout' : 'fail:network ' + (e.cause?.code || e.message);
    log(t, url, '-', '-', 0, '-', file, note);
    return { fail: note };
  }
  const s = res.status, h = k => res.headers.get(k) || '-';
  const final = res.url.replace(ORIGIN, SITE);
  const row = (note) => log(t, url, s, final, buf.length, h('cf-cache-status'), file, note);
  const text = buf.toString('latin1');
  if (s === 429 || s === 403 || s >= 500 || res.headers.get('cf-mitigated') || CHALLENGE.test(text)) {
    const note = s === 200 || res.headers.get('cf-mitigated') ? 'fail:challenge' : 'fail:http';
    row(note);
    const ra = res.headers.get('retry-after');
    const raMs = ra == null ? 0 : isNaN(+ra) ? Date.parse(ra) - Date.now() : +ra * 1000;
    return { fail: note + ' ' + s, retryAfter: raMs || 0 };
  }
  if (s >= 400 || !isHtml) { row(`skip:${s >= 400 ? s : 'not-html'}`); return { skip: true }; }
  if (!/<\/html>/i.test(text) || buf.length < MIN_BYTES) { row('fail:short'); return { fail: `fail:short ${buf.length}B` }; }
  const dest = path.join(RAW, file);
  fs.writeFileSync(dest + '.part', buf);
  fs.renameSync(dest + '.part', dest);
  row('ok');
  return { ok: true };
}

let streak = 0;
for (const item of todo) {
  for (;;) {
    const r = await attempt(item);
    if (!r.fail) { streak = 0; r.ok ? ok++ : skipped++; break; }
    failures++; streak++;
    if (streak >= MAX_FAILS) {
      log(now(), 'STOP', '-', '-', 0, '-', '-', `${MAX_FAILS} consecutive failures, last: ${item.url} ${r.fail}`);
      summary(`STOPPED after ${MAX_FAILS} consecutive failures (last ${item.url} ${r.fail})`);
      process.exit(2);
    }
    const wait = Math.max(BACKOFF * 2 ** (streak - 1), r.retryAfter || 0);
    console.log(`${now()} ${r.fail} on ${item.url}; failure ${streak}/${MAX_FAILS}, waiting ${Math.round(wait / 1000)}s`);
    await sleep(wait);
  }
  if (++done % 50 === 0) {
    const per = (Date.now() - t0) / done;
    console.log(`${now()} ${done}/${todo.length} done, ${failures} failed attempts, ~${hrs(per * (todo.length - done))} left`);
  }
}
summary('finished');
