#!/usr/bin/env node
// Loader driver for POST /wp-json/tng/v1/import (wp/tng-core/inc/import.php).
//
// Attaches over CDP to the user's logged-in Chromium, opens its own wp-admin
// tab and runs fetch() inside it (cookie auth + REST nonce), so this script
// never sees credentials. Sends the normalised JSONL in batches, appends one
// line per result to a progress log, and on restart skips what the log says
// is already created / skipped. The server is idempotent anyway: a lost log
// only costs a slower rerun, never a duplicate.
//
// No dependencies (Node 22+: built-in fetch + WebSocket).
// Contract and examples: temporary/migration/loader-contract.md
//
//   node wp/migration/import.mjs --site https://tng.mydemobb.com --port 9222 \
//     --run r1115a --op insight --file temporary/migration/out/insights.jsonl [--write]
//
// Without --write every request is a dry run.

import { readFileSync, appendFileSync, existsSync, mkdirSync } from 'node:fs';
import { dirname } from 'node:path';

const ITEM_OPS = ['media', 'terms', 'professional', 'term_fields', 'insight', 'award', 'job', 'redirects', 'page', 'resync'];
const OTHER_OPS = ['status', 'finalize', 'rollback', 'retire_demo', 'cutover'];
const DEFAULT_BATCH = { media: 5, redirects: 200 }; // everything else: 20

const args = parseArgs(process.argv.slice(2));
const usage = `usage: node wp/migration/import.mjs --site <url> --run <id> --op <${[...ITEM_OPS, ...OTHER_OPS].join('|')}>
  [--file <items.jsonl>] [--write] [--port 9222] [--batch N] [--limit N] [--author <user id>]
  [--log temporary/migration/out/progress.jsonl] [--delay <ms between batches>] [--b64]
  [--backoff <ms> --max-backoffs N]   (media: first wait when the old site strains, doubled each time; default 60000, capped at 15 min, 5 waits)
  [--types professional,job]   (retire_demo only: limit to these post types)
  [--from tng.mydemobb.com --to www.tilleke.com [--confirm "<from> -> <to>"]]   (cutover only; a real run needs --write and --confirm)`;
if (args.help || !args.site || !args.run || !args.op) die(usage);
if (![...ITEM_OPS, ...OTHER_OPS].includes(args.op)) die(`unknown op ${args.op}\n${usage}`);
if (!/^[a-z0-9-]{1,32}$/.test(args.run)) die('--run must match [a-z0-9-]{1,32}');
if (ITEM_OPS.includes(args.op) && !args.file) die(`--op ${args.op} needs --file`);

const site = new URL(args.site).origin;
const port = Number(args.port || 9222);
const write = Boolean(args.write);
const logPath = args.log || 'temporary/migration/out/progress.jsonl';
const batchSize = Number(args.batch || DEFAULT_BATCH[args.op] || 20);
const delay = Number(args.delay || 0);
let useB64 = Boolean(args.b64);
const backoff = Number(args.backoff || 60000);
const backoffCap = args.backoff ? Infinity : 900000;
const maxBackoffs = Number(args['max-backoffs'] || 5);

const cdp = await openTab(port, `${site}/wp-admin/`);
try {
  const loc = await cdp.eval('location.href');
  if (!loc.startsWith(site) || loc.includes('wp-login.php')) die(`The browser is not logged in to ${site} (tab is at ${loc}). Log in to wp-admin in that browser, then rerun.`);
  if (ITEM_OPS.includes(args.op)) await runItems();
  else await runOther();
} finally {
  await cdp.close();
}

/* ---------------- ops ---------------- */

async function runItems() {
  let items = readJsonl(args.file);
  if (args.limit) items = items.slice(0, Number(args.limit));
  const done = write ? doneKeys() : new Set();
  const queue = items.filter((it) => !done.has(keyOf(it)));
  const total = queue.length;
  console.log(`${write ? 'WRITE' : 'DRY RUN'} ${args.op}: ${items.length} items, ${items.length - total} already done in ${logPath}, ${total} to send`);

  const counts = {};
  const notes = {};
  let stalls = 0;
  let strain = 0; // media: consecutive batches where the old site answered 429 / 5xx
  while (queue.length) {
    const batch = queue.splice(0, batchSize);
    const res = await post({ items: batch });
    const results = res.results || [];
    let progress = 0;
    const retry = [];
    // Media downloads hit the client's live site: a 429 / 5xx / timeout means back off hard
    // (resend later), a disk / quota error on our host means stop.
    const disk = results.find((r) => r.status === 'error' && /quota|disk|no space|could not be moved|could not write|unable to create directory/i.test(r.error || ''));
    if (disk) die(`Stopping: the new host reports a storage problem: ${disk.error}`);
    const busy = results.filter((r) => r.status === 'error' && /http_(403|429|5\d\d)|http_request_failed|timed out|cURL error (28|7|35|52|56)/i.test(r.error || ''));
    if (busy.length) {
      strain++;
      if (strain > maxBackoffs) die(`Stopping: the old site keeps answering errors (${busy[0].error}). Resume later with the same command.`);
      const wait = Math.min(backoff * 2 ** (strain - 1), backoffCap);
      console.log(`  old site strained (${busy.length}: ${busy[0].error}); backing off ${wait / 1000} s`);
      batch.forEach((item, i) => { if (busy.includes(results[i])) results[i] = { status: 'pending' }; });
      await sleep(wait);
    } else strain = 0;
    batch.forEach((item, i) => {
      const r = results[i] || { status: 'pending' };
      if (r.status === 'pending') { retry.push(item); return; }
      progress++;
      counts[r.status] = (counts[r.status] || 0) + 1;
      for (const n of r.notes || []) { const k = n.split(':')[0]; notes[k] = (notes[k] || 0) + 1; }
      if (r.status === 'error') console.log(`  error ${keyOf(item)}: ${r.error}`);
      log({ key: keyOf(item), ...r });
    });
    queue.unshift(...retry); // pending = the time budget ran out; resend first
    stalls = progress || strain ? 0 : stalls + 1;
    if (stalls >= 3) die('Three batches in a row made no progress (time budget too small for one item?). Stopping.');
    const sent = total - queue.length;
    console.log(`  ${sent}/${total}  ${JSON.stringify(counts)}  ${res.elapsed}s`);
    if (delay && queue.length) await sleep(delay);
  }
  console.log(`done: ${JSON.stringify(counts)}  notes: ${JSON.stringify(notes)}`);
}

async function runOther() {
  // rollback deletes at most 50 objects per request, a real cutover writes in batches: repeat until nothing is left
  let cursor = null;
  for (;;) {
    const res = await post(args.op === 'cutover' ? { from: args.from, to: args.to, ...(args.confirm ? { confirm: args.confirm } : {}), ...(cursor ? { cursor } : {}) } : {});
    if (args.op === 'cutover') {
      log({ key: `cutover:${args.from}->${args.to}`, ...res });
      console.log(JSON.stringify(res, null, 2));
      if (!(write && res.more)) break;
      cursor = res.cursor;
      continue;
    }
    for (const r of res.results || []) {
      log({ key: `${r.kind}:${r.id}`, ...r });
      if (r.status !== 'would_delete' && r.status !== 'would_trash') console.log(`  ${r.status} ${r.kind} #${r.id}${r.title ? ' ' + r.title : ''}${r.notes ? ' (' + r.notes.join('; ') + ')' : ''}`);
    }
    const tally = {};
    for (const r of res.results || []) tally[r.status] = (tally[r.status] || 0) + 1;
    const { results, ...rest } = res;
    console.log(JSON.stringify({ ...rest, tally }, null, 2));
    if (!(write && args.op === 'rollback' && res.more)) break;
  }
}

/* ---------------- transport ---------------- */

async function post(extra) {
  const body = JSON.stringify({ run: args.run, op: args.op, dry_run: !write, ...(args.author ? { author: Number(args.author) } : {}), ...(args.types ? { types: String(args.types).split(',') } : {}), ...extra });
  let locked = 0;
  for (let attempt = 0; ; attempt++) {
    const r = await cdp.eval(`(${inTab})(${JSON.stringify(body)}, ${useB64})`);
    let json = null;
    try { json = JSON.parse(r.text); } catch {}
    if (r.status === 200 && json) return json;
    // A worker killed mid-request leaves the lock; the server takes it over after 5 min.
    if (r.status === 409) { if (++locked > 30) die('Still locked after 7.5 min.'); console.log('  locked by another request, waiting 15 s'); await sleep(15000); attempt--; continue; }
    if (r.status === 403 && !json && !useB64) { console.log('  403 without JSON (WAF?): switching to base64 bodies'); useB64 = true; continue; }
    if (r.status === 403 && !json) die(`HTTP 403 without JSON even as base64: the host killed the request (upload scanner / resource limit?). Last batch: ${body.slice(0, 300)}`);
    if (json && r.status < 500) die(`HTTP ${r.status}: ${json.message || r.text}`); // a request problem: retrying won't help
    if (attempt >= 3) die(`HTTP ${r.status} after ${attempt + 1} tries: ${String(r.text).slice(0, 500)}`);
    const wait = 5000 * 3 ** attempt;
    console.log(`  HTTP ${r.status}, retrying in ${wait / 1000} s`);
    await sleep(wait);
  }
}

// Runs inside the wp-admin tab. Refetches the REST nonce once when it expired.
async function inTab(body, b64) {
  const nonce = async () => {
    const r = await fetch('/wp-admin/admin-ajax.php?action=rest-nonce', { credentials: 'same-origin' });
    const t = (await r.text()).trim();
    if (!/^[a-f0-9]{10}$/.test(t)) throw new Error('No REST nonce (logged out?)');
    return t;
  };
  const encode = (s) => {
    const bytes = new TextEncoder().encode(s);
    let bin = '';
    for (let i = 0; i < bytes.length; i += 0x8000) bin += String.fromCharCode(...bytes.subarray(i, i + 0x8000));
    return btoa(bin);
  };
  const send = () => fetch('/wp-json/tng/v1/import', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.__tngNonce },
    body: b64 ? JSON.stringify({ b64: encode(body) }) : body,
  });
  window.__tngNonce = window.__tngNonce || await nonce();
  let r = await send();
  let text = await r.text();
  if (r.status === 403 && text.includes('rest_cookie_invalid_nonce')) {
    window.__tngNonce = await nonce();
    r = await send();
    text = await r.text();
  }
  return { status: r.status, text };
}

/* ---------------- CDP ---------------- */

async function openTab(port, url) {
  const base = `http://127.0.0.1:${port}`;
  let target;
  try {
    target = await (await fetch(`${base}/json/new?${encodeURI(url)}`, { method: 'PUT' })).json();
  } catch (e) {
    die(`No Chromium with remote debugging on port ${port} (${e.message}). Start it with --remote-debugging-port=${port} and the logged-in profile.`);
  }
  const ws = new WebSocket(target.webSocketDebuggerUrl);
  await new Promise((ok, fail) => { ws.onopen = ok; ws.onerror = () => fail(new Error('CDP connection failed')); });
  let id = 0;
  const waiting = new Map();
  ws.onmessage = (m) => {
    const msg = JSON.parse(m.data);
    if (msg.id && waiting.has(msg.id)) { waiting.get(msg.id)(msg); waiting.delete(msg.id); }
  };
  const send = (method, params = {}) => new Promise((ok) => { const n = ++id; waiting.set(n, ok); ws.send(JSON.stringify({ id: n, method, params })); });
  const evaluate = async (expression) => {
    const msg = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
    if (msg.error) throw new Error(msg.error.message);
    if (msg.result.exceptionDetails) throw new Error(msg.result.exceptionDetails.exception?.description || msg.result.exceptionDetails.text);
    return msg.result.result.value;
  };
  for (let i = 0; i < 60; i++) { // wait for the admin page to load (or land on the login page)
    try { if ((await evaluate('location.protocol.startsWith("http") && document.readyState')) === 'complete') break; } catch {}
    await sleep(500);
  }
  return {
    eval: evaluate,
    close: async () => { ws.close(); await fetch(`${base}/json/close/${target.id}`).catch(() => {}); },
  };
}

/* ---------------- files ---------------- */

function keyOf(item) {
  if (args.op === 'terms' || args.op === 'term_fields') return `${item.taxonomy}:${item.slug}`;
  return item.src || item.from || item.path || '';
}

function readJsonl(path) {
  return readFileSync(path, 'utf8').split('\n').map((line, i) => {
    if (!line.trim()) return null;
    try { return JSON.parse(line); } catch (e) { die(`${path}:${i + 1}: ${e.message}`); }
  }).filter(Boolean);
}

function doneKeys() {
  const done = new Set();
  if (!existsSync(logPath)) return done;
  for (const line of readFileSync(logPath, 'utf8').split('\n')) {
    if (!line.trim()) continue;
    const r = JSON.parse(line);
    if (r.run === args.run && r.op === args.op && r.dry_run === false && ['created', 'skipped', 'filled', 'updated', 'unchanged'].includes(r.status)) done.add(r.key);
  }
  return done;
}

function log(r) {
  mkdirSync(dirname(logPath), { recursive: true });
  appendFileSync(logPath, JSON.stringify({ at: new Date().toISOString(), run: args.run, op: args.op, dry_run: !write, ...r }) + '\n');
}

function parseArgs(argv) {
  const out = {};
  for (let i = 0; i < argv.length; i++) {
    const m = argv[i].match(/^--([a-z0-9-]+)(?:=(.*))?$/);
    if (!m) die(`unexpected argument ${argv[i]}`);
    if (m[2] !== undefined) out[m[1]] = m[2];
    else if (argv[i + 1] !== undefined && !argv[i + 1].startsWith('--')) out[m[1]] = argv[++i];
    else out[m[1]] = true;
  }
  return out;
}

function sleep(ms) { return new Promise((r) => setTimeout(r, ms)); }
function die(msg) { console.error(msg); process.exit(1); }
