#!/usr/bin/env node
// Old (Laravel prerender) vs new (Astro) SEO parity diff. Pure `diffSeo` + CLI. Read-only GETs only.
import { fileURLToPath } from "node:url";
import { parseHtml, norm, nodesOf } from "./seo-gate.mjs";

// ---------- whitelist: intentional differences (each needs a reason) ----------
// 1. canonical: new site prefixes the language (/blog/x -> /{lang}/blog/x, /projects/x -> /{lang}/projects/x);
//    spec: language-prefixed URLs are the new canonical scheme.
// 2. aggregateRating (and its AggregateRating type): Ali dropped self-made ratings (spec: no aggregateRating/review).
// 3. old title suffix "| Ali Sadikin Ma" / "- Ali Sadikin Ma": old prerender appended the brand; new titles follow the
//    per-route brand map, so the suffix is not a "keyword" and is stripped before comparing.
const SUFFIX_RE = /\s*[|\-–—]\s*(?:Blog\s*\|\s*)?Ali Sadikin Ma.*$/i;
// 4. FAQPage/Question/Answer: the old prerender put the same 3-question FAQ on every page. Spec: FAQPage only on
//    pages whose brief asks for it (Google retired FAQ rich results 2026-05-07); a site-wide copy is duplicate content.
// 5. project title "{title} - {category}" (e.g. "Asset Audit - Web Platform"): spec requires the brand in project
//    titles ("{title} — Ali Sadikin Ma"); the category is not a title keyword. Only the trailing " - X" on project URLs.
const WHITELIST_TYPES = new Map([
  ["AggregateRating", "aggregateRating removed on purpose"],
  ["FAQPage", "site-wide boilerplate FAQ removed (spec)"],
  ["Question", "site-wide boilerplate FAQ removed (spec)"],
  ["Answer", "site-wide boilerplate FAQ removed (spec)"],
]);
const PROJECT_SUFFIX_RE = /\s+-\s+[^-|–—]+$/;

const STOP = new Set(["the", "and", "for", "with", "from", "that", "this", "dan", "yang", "untuk", "dengan", "dari", "ini", "itu", "blog", "your", "are", "you"]);
const sig = (s) => [...new Set(norm(s).toLowerCase().split(/[^\p{L}\p{N}]+/u).filter((w) => w.length >= 3 && !STOP.has(w)))];

function collectTypes(v, out, flags) {
  if (Array.isArray(v)) return v.forEach((x) => collectTypes(x, out, flags));
  if (!v || typeof v !== "object") return;
  for (const t of nodesOf(v["@type"])) out.add(t);
  if ("aggregateRating" in v) flags.rating = true;
  for (const x of Object.values(v)) collectTypes(x, out, flags);
}

export function extractSeo(html) {
  const { els } = parseHtml(html);
  const q = (t) => els.filter((e) => e.tag === t);
  const meta = (k, v) => q("meta").find((e) => e.attrs[k] === v)?.attrs.content?.trim() ?? "";
  const types = new Set();
  const flags = { rating: false };
  for (const s of q("script").filter((e) => e.attrs.type === "application/ld+json")) {
    try { collectTypes(JSON.parse(s.raw), types, flags); } catch { /* unparsable JSON-LD: no types */ }
  }
  return {
    title: norm(q("title")[0]?.text ?? ""),
    canonical: q("link").find((e) => e.attrs.rel === "canonical")?.attrs.href ?? "",
    h1: q("h1").map((e) => norm(e.text)).filter(Boolean),
    description: meta("name", "description"),
    ogImage: meta("property", "og:image"),
    robots: (meta("name", "robots") + "," + meta("name", "googlebot")).toLowerCase().split(/[\s,]+/).filter(Boolean),
    types, rating: flags.rating,
  };
}

const prefixedCanon = (oldC, newC) => {
  try {
    const o = new URL(oldC), n = new URL(newC);
    return o.origin === n.origin && /^\/(?:id|en)\//.test(n.pathname) && n.pathname.replace(/^\/(?:id|en)/, "") === o.pathname;
  } catch { return false; }
};

/** @returns {{kind:string, detail:string, whitelisted:boolean}[]} */
export function diffSeo(oldHtml, newHtml, ctx = {}) {
  const o = extractSeo(oldHtml), n = extractSeo(newHtml);
  const out = [];
  const add = (kind, detail, whitelisted = false) => out.push({ kind, detail, whitelisted });

  for (const t of o.types) if (!n.types.has(t)) {
    if (WHITELIST_TYPES.has(t)) add("jsonld-type-dropped", `${t} (${WHITELIST_TYPES.get(t)})`, true);
    else add("jsonld-type-dropped", t);
  }
  if (o.rating && !n.rating) add("aggregateRating-removed", "aggregateRating dropped", true);

  if (o.canonical && o.canonical !== n.canonical) {
    if (prefixedCanon(o.canonical, n.canonical)) add("canonical-changed", `${o.canonical} -> ${n.canonical} (lang prefix)`, true);
    else add("canonical-changed", `${o.canonical} -> ${n.canonical || "(none)"}`);
  }

  const nt = n.title.toLowerCase();
  let oldTitle = o.title.replace(SUFFIX_RE, "");
  if (/\/projects\/[^/]+\/?$/.test(ctx.oldUrl ?? "")) oldTitle = oldTitle.replace(PROJECT_SUFFIX_RE, "");
  const lostT = sig(oldTitle).filter((w) => !nt.includes(w));
  if (lostT.length) add("title-keyword-lost", `lost [${lostT.join(", ")}]; old "${o.title}" new "${n.title}"`);

  if (o.h1.length) {
    const nh = n.h1.join(" ").toLowerCase();
    const lostH = sig(o.h1[0]).filter((w) => !nh.includes(w));
    if (!n.h1.length) add("h1-lost", `old "${o.h1[0]}", new has no H1`);
    else if (lostH.length) add("h1-lost", `lost [${lostH.join(", ")}]; old "${o.h1[0]}" new "${n.h1[0]}"`);
  }

  if (o.description && !n.description) add("description-lost", `old "${o.description.slice(0, 80)}"`);
  if (o.ogImage && !n.ogImage) add("og-image-lost", `old ${o.ogImage}`);
  const noidx = (r) => r.includes("noindex") || r.includes("none");
  if (!noidx(o.robots) && noidx(n.robots)) add("robots-noindex", `new robots: ${n.robots.join(",")}`);
  if (o.types.has("BreadcrumbList") && !n.types.has("BreadcrumbList")) add("breadcrumb-lost", "BreadcrumbList missing in new");
  return out;
}

/** Old path (/blog/x, /blog/category/c, /projects/p) -> new path with language prefix. */
export const mapOldToNew = (path, lang) => `/${lang}${path}`;

// ---------- CLI ----------
async function get(url) {
  const res = await fetch(url, { redirect: "follow", headers: { "User-Agent": "Mozilla/5.0 (compatible; parity-diff/1.0)" }, signal: AbortSignal.timeout(10000) });
  return { status: res.status, text: await res.text() };
}
async function pool(items, n, fn) {
  let i = 0;
  await Promise.all(Array.from({ length: n }, async () => { while (i < items.length) await fn(items[i++]); }));
}

export async function runCli(argv) {
  const arg = (k, d) => { const i = argv.indexOf(k); return i === -1 ? d : argv[i + 1]; };
  const oldBase = arg("--old", "https://alisadikinma.com").replace(/\/$/, "");
  const newBase = arg("--new", "http://127.0.0.1:4321").replace(/\/$/, "");
  const lang = arg("--lang", "en"); // old prerender serves lang="en" at /blog/x
  const lp = +arg("--limit-posts", 10), lc = +arg("--limit-categories", 3), lj = +arg("--limit-projects", 5);
  const api = async (p) => (await (await fetch(oldBase + p, { signal: AbortSignal.timeout(10000) })).json()).data ?? [];
  const posts = (await api(`/api/posts?per_page=${lp}&lang=${lang}`)).slice(0, lp);
  const cats = (await api(`/api/categories`)).filter((c) => (c.posts_count ?? 1) > 0).slice(0, lc);
  const projs = (await api(`/api/projects?per_page=${lj}`)).slice(0, lj);
  const paths = [...posts.map((p) => `/blog/${p.slug}`), ...cats.map((c) => `/blog/category/${c.slug}`), ...projs.map((p) => `/projects/${p.slug}`)];
  const results = [];
  const counts = {}; let bad = 0, wl = 0;
  await pool(paths, 4, async (path) => {
    const np = mapOldToNew(path, lang);
    const bust = (r) => r.catch((e) => ({ status: 0, text: "", err: e.message }));
    const [o, n] = await Promise.all([bust(get(oldBase + path)), bust(get(newBase + np))]);
    const rows = [];
    if (o.status !== 200) rows.push({ kind: "old-http", detail: `old status ${o.status} ${o.err ?? ""}`, whitelisted: true });
    else if (n.status !== 200) rows.push({ kind: "new-http", detail: `new status ${n.status} ${n.err ?? ""}`, whitelisted: false });
    else rows.push(...diffSeo(o.text, n.text, { oldUrl: oldBase + path, newUrl: newBase + np }));
    const lines = [`${path} -> ${np}${rows.length ? "" : "  OK"}`];
    for (const r of rows) {
      lines.push(`  ${r.whitelisted ? "[whitelisted]" : "[REGRESSION] "} ${r.kind}: ${r.detail}`);
      const key = `${r.kind}${r.whitelisted ? " (whitelisted)" : ""}`;
      counts[key] = (counts[key] ?? 0) + 1;
      r.whitelisted ? wl++ : bad++;
    }
    results.push([path, lines.join("\n")]);
  });
  console.log(results.sort((a, b) => paths.indexOf(a[0]) - paths.indexOf(b[0])).map((r) => r[1]).join("\n"));
  console.log(`\nparity-diff: ${paths.length} URL(s), lang=${lang}, ${bad} regression(s), ${wl} whitelisted`);
  for (const [k, v] of Object.entries(counts).sort()) console.log(`  ${k}: ${v}`);
  return bad ? 1 : 0;
}
if (process.argv[1] === fileURLToPath(import.meta.url)) runCli(process.argv.slice(2)).then((c) => process.exit(c));
