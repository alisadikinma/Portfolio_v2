#!/usr/bin/env node
// SEO/GEO acceptance gate. Pure `checkHtml(html, routeSpec)` + CLI.
// No HTML parser dependency is installed, so a small tolerant tokenizer lives here.
import { SITE, PERSON_ID, ORG_ID, NAME, ROUTES } from "./seo-gate.config.mjs";

const VOID = new Set(["area", "base", "br", "col", "embed", "hr", "img", "input", "link", "meta", "source", "track", "wbr"]);
const RAW = new Set(["script", "style"]);

const decode = (s) =>
  s.replace(/&nbsp;/g, " ").replace(/&quot;/g, '"').replace(/&#0?39;|&apos;/g, "'")
    .replace(/&lt;/g, "<").replace(/&gt;/g, ">").replace(/&amp;/g, "&");

function parseAttrs(src) {
  const attrs = {};
  const re = /([^\s=/>"']+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s"'>]+)))?/g;
  let m;
  while ((m = re.exec(src))) attrs[m[1].toLowerCase()] = decode(m[2] ?? m[3] ?? m[4] ?? "");
  return attrs;
}

export function parseHtml(html) {
  const root = { tag: "#root", attrs: {}, parent: null, children: [], text: "" };
  const els = [];
  let cur = root;
  const re = /<!--[\s\S]*?-->|<(\/?)([a-zA-Z][a-zA-Z0-9-]*)((?:"[^"]*"|'[^']*'|[^'">])*)>|([^<]+|<)/g;
  let m;
  while ((m = re.exec(html))) {
    if (m[0].startsWith("<!--")) continue;
    if (m[4] !== undefined) {
      for (let n = cur; n; n = n.parent) n.text += m[4];
      continue;
    }
    const closing = m[1] === "/";
    const tag = m[2].toLowerCase();
    if (closing) {
      let n = cur;
      while (n && n.tag !== tag) n = n.parent;
      if (n && n.parent) cur = n.parent;
      continue;
    }
    const el = { tag, attrs: parseAttrs(m[3].replace(/\/$/, "")), parent: cur, children: [], text: "", idx: els.length };
    els.push(el);
    cur.children.push(el);
    if (RAW.has(tag)) {
      const end = html.toLowerCase().indexOf(`</${tag}`, re.lastIndex);
      const stop = end === -1 ? html.length : end;
      el.raw = html.slice(re.lastIndex, stop);
      re.lastIndex = stop;
      continue;
    }
    if (!VOID.has(tag) && !m[3].trimEnd().endsWith("/")) cur = el;
  }
  return { root, els };
}

const within = (el, tags) => { for (let n = el.parent; n; n = n.parent) if (tags.includes(n.tag)) return true; return false; };
const norm = (s) => s.replace(/\s+/g, " ").trim();
const words = (s) => norm(s).split(" ").filter(Boolean).length;
const hasKw = (text, kws) => kws.some((k) => text.toLowerCase().includes(k.toLowerCase()));
const nodesOf = (v) => (Array.isArray(v) ? v : v ? [v] : []);
const typesOf = (n) => nodesOf(n["@type"]);
const refId = (v) => nodesOf(v).some((x) => x && x["@id"] === ORG_ID);
const abs = (u) => /^https:\/\/[^/]+/.test(u || "");

export const GATE_ITEMS = [
  "lang", "title", "description", "h1", "lede", "canonical-hreflang",
  "jsonld", "og", "img", "backlinks", "internal-links", "noindex",
];

/** @returns {{item:string, detail:string}[]} empty array = pass */
export function checkHtml(html, spec) {
  const fails = [];
  const fail = (item, detail) => fails.push({ item, detail });
  const { els } = parseHtml(html);
  const q = (tag) => els.filter((e) => e.tag === tag);
  const meta = (key, val) => q("meta").find((e) => e.attrs[key] === val);
  const kws = spec.keywords;
  const canonicalUrl = SITE + spec.route;

  // 1 lang
  const htmlEl = q("html")[0];
  if (!htmlEl || htmlEl.attrs.lang !== spec.lang) fail("lang", `<html lang> is "${htmlEl?.attrs.lang ?? ""}", want "${spec.lang}"`);

  // 2 title
  const title = norm(q("title")[0]?.text ?? "");
  if (!title) fail("title", "missing <title>");
  else {
    if (title.length > 60) fail("title", `${title.length} chars, max 60`);
    if (!hasKw(title, kws)) fail("title", `no keyword (${kws.join(" | ")})`);
    if (!title.includes(NAME)) fail("title", `missing "${NAME}"`);
  }

  // 3 description
  const desc = (meta("name", "description")?.attrs.content ?? "").trim();
  if (desc.length < 120 || desc.length > 160) fail("description", `${desc.length} chars, want 120-160`);
  if (!hasKw(desc, kws)) fail("description", `no keyword (${kws.join(" | ")})`);

  // 4 h1
  const h1s = q("h1");
  if (h1s.length !== 1) fail("h1", `${h1s.length} <h1>, want exactly 1`);
  else {
    const t = norm(h1s[0].text);
    if (!hasKw(t, kws)) fail("h1", `no keyword in "${t}"`);
    if (spec.isHome && !t.includes(NAME)) fail("h1", `home H1 missing "${NAME}"`);
  }

  // 5 lede: first <p> after the H1 in document order
  if (h1s[0]) {
    const p = els.find((e) => e.tag === "p" && e.idx > h1s[0].idx);
    const n = p ? words(p.text) : 0;
    if (n < 25) fail("lede", `first paragraph after H1 has ${n} words, want >= 25`);
  } else fail("lede", "no H1, cannot locate first paragraph");

  // 6 canonical + hreflang
  const canon = q("link").find((e) => e.attrs.rel === "canonical")?.attrs.href;
  if (canon !== canonicalUrl) fail("canonical-hreflang", `canonical "${canon ?? ""}", want ${canonicalUrl}`);
  const alts = {};
  for (const e of q("link")) if (e.attrs.rel === "alternate" && e.attrs.hreflang) alts[e.attrs.hreflang] = e.attrs.href;
  for (const l of ["id", "en", "x-default"]) if (!alts[l]) fail("canonical-hreflang", `missing hreflang ${l}`);
  for (const l of ["id", "en"]) {
    const want = spec.alternates?.[l] ? SITE + spec.alternates[l] : null;
    if (alts[l] && want && alts[l] !== want) fail("canonical-hreflang", `hreflang ${l} is ${alts[l]}, want ${want}`);
  }
  for (const [l, u] of Object.entries(alts)) if (!abs(u)) fail("canonical-hreflang", `hreflang ${l} not absolute https: ${u}`);

  // 7 JSON-LD
  const nodes = [];
  let parsedOk = true;
  for (const s of q("script").filter((e) => e.attrs.type === "application/ld+json")) {
    try {
      const j = JSON.parse(s.raw);
      for (const top of nodesOf(j)) { nodes.push(top); nodes.push(...nodesOf(top["@graph"])); }
    } catch (e) { parsedOk = false; fail("jsonld", `JSON-LD does not parse: ${e.message}`); }
  }
  if (parsedOk && !nodes.length) fail("jsonld", "no JSON-LD found");
  if (nodes.length) {
    const person = nodes.find((n) => n["@id"] === PERSON_ID && typesOf(n).includes("Person"));
    if (!person) fail("jsonld", `no Person node with @id ${PERSON_ID}`);
    else if (!refId(person.worksFor) && !refId(person.founder)) fail("jsonld", `Person worksFor/founder must be {"@id":"${ORG_ID}"}`);
    if (!nodes.some((n) => typesOf(n).includes(spec.pageType))) fail("jsonld", `no ${spec.pageType} node`);
    for (const n of nodes)
      if (typesOf(n).some((t) => t === "Person" || t === "Organization") && ("aggregateRating" in n || "review" in n))
        fail("jsonld", `aggregateRating/review on ${typesOf(n).join(",")} node`);
  }

  // 8 OG
  for (const p of ["og:title", "og:description", "og:image", "og:url"]) {
    const c = meta("property", p)?.attrs.content;
    if (!c) fail("og", `missing ${p}`);
    else if (p === "og:image" && !abs(c)) fail("og", `og:image not absolute https: ${c}`);
    else if (p === "og:url" && c !== canonicalUrl) fail("og", `og:url "${c}", want ${canonicalUrl}`);
  }

  // 9 images
  for (const im of q("img")) {
    const src = im.attrs.src ?? "(no src)";
    const alt = im.attrs.alt;
    const decorative = alt === "" && im.attrs["aria-hidden"] === "true";
    if (alt === undefined || (alt.trim() === "" && !decorative)) fail("img", `${src}: empty/missing alt (decorative needs alt="" + aria-hidden="true")`);
    if (!im.attrs.width || !im.attrs.height) fail("img", `${src}: width/height not set`);
  }

  // 10 backlinks
  const anchors = q("a").map((a) => ({ ...a, href: a.attrs.href ?? "", label: norm(a.text), rel: (a.attrs.rel ?? "").toLowerCase().split(/\s+/) }));
  for (const need of spec.backlinks ?? []) {
    const toHref = anchors.filter((a) => a.href === need.href);
    const exact = toHref.filter((a) => a.label === need.anchor);
    if (!toHref.length) fail("backlinks", `missing link to ${need.href} ("${need.anchor}")`);
    else if (!exact.length) fail("backlinks", `wrong anchor for ${need.href}: want "${need.anchor}", have ${toHref.map((a) => `"${a.label}"`).join(", ")}`);
    else if (!exact.some((a) => !a.rel.some((r) => ["nofollow", "ugc", "sponsored"].includes(r))))
      fail("backlinks", `"${need.anchor}" -> ${need.href} is not dofollow (rel="${exact[0].rel.join(" ")}")`);
  }

  // 11 internal contextual links
  const internal = new Set();
  for (const a of anchors) {
    if (within(a, ["nav", "header", "footer"])) continue;
    let u = a.href;
    if (u.startsWith(SITE)) u = u.slice(SITE.length) || "/";
    if (!u.startsWith("/") || u.startsWith("//")) continue;
    const path = u.split(/[?#]/)[0];
    if (!path || path === spec.route) continue;
    internal.add(path);
  }
  if (internal.size < 2) fail("internal-links", `${internal.size} contextual internal link(s), want >= 2`);

  // 12 noindex
  const robots = meta("name", "robots")?.attrs.content ?? "";
  if (/noindex/i.test(robots)) fail("noindex", `meta robots: ${robots}`);

  // Extras from brief section 3 (not part of the 12): FAQ first question, no prices.
  if (spec.faq) {
    const faq = nodes.find((n) => typesOf(n).includes("FAQPage"));
    const first = faq && nodesOf(faq.mainEntity)[0]?.name;
    if (!first) fail("faq", "no FAQPage JSON-LD with a first question");
    else if (!hasKw(first, kws)) fail("faq", `first question lacks keyword: "${first}"`);
  }
  const body = q("body")[0]?.text ?? "";
  const price = body.match(/\b(?:Rp\.?|IDR|USD|US\$)\s?\d[\d.,]*|\$\s?\d[\d.,]*/i);
  if (price) fail("no-price", `price-like text "${price[0]}"`);

  return fails;
}

// ---------- CLI ----------

async function get(url) {
  const res = await fetch(url, {
    redirect: "manual",
    headers: { "User-Agent": "Mozilla/5.0 (compatible; GPTBot/1.0)" },
  });
  return { status: res.status, text: await res.text() };
}

function robotsBlocks(robotsTxt, path) {
  let applies = false;
  for (const raw of robotsTxt.split("\n")) {
    const line = raw.split("#")[0].trim();
    const [k, ...r] = line.split(":");
    const v = r.join(":").trim();
    if (/^user-agent$/i.test(k)) applies = v === "*";
    else if (applies && /^disallow$/i.test(k) && v && path.startsWith(v)) return true;
  }
  return false;
}

async function sitemapUrls(base) {
  const out = new Set();
  const loc = (t) => [...t.matchAll(/<loc>\s*([^<]+?)\s*<\/loc>/g)].map((m) => m[1]);
  const root = await get(`${base}/sitemap.xml`);
  if (root.status !== 200) return out;
  for (const u of loc(root.text)) {
    if (u.endsWith(".xml")) {
      try { const sub = await get(u.replace(SITE, base)); loc(sub.text).forEach((x) => out.add(x)); } catch { /* ignore */ }
    } else out.add(u);
  }
  return out;
}

export async function runCli(argv) {
  const arg = (n, d) => { const i = argv.indexOf(n); return i === -1 ? d : argv[i + 1]; };
  const base = arg("--base", "http://127.0.0.1:4321").replace(/\/$/, "");
  const group = arg("--routes", "all");
  const specs = ROUTES[group];
  if (!specs) { console.error(`unknown route group "${group}" (${Object.keys(ROUTES).join(", ")})`); return 2; }
  const lines = [];
  const robots = await get(`${base}/robots.txt`).catch(() => ({ status: 0, text: "" }));
  const sitemap = await sitemapUrls(base).catch(() => new Set());
  for (const spec of specs) {
    const out = (item, detail) => lines.push(`${spec.route} | ${item} | ${detail}`);
    const res = await get(base + spec.route).catch((e) => ({ status: 0, text: "", err: e.message }));
    if (res.status !== 200) { out("http", `status ${res.status} ${res.err ?? ""}`.trim()); continue; }
    for (const f of checkHtml(res.text, spec)) out(f.item, f.detail);
    // reciprocal hreflang: each alternate must link back to this page
    for (const l of ["id", "en"]) {
      const altRoute = spec.alternates[l];
      if (altRoute === spec.route) continue;
      const other = await get(base + altRoute).catch(() => null);
      const back = other && new RegExp(`hreflang=["']${spec.lang}["'][^>]*href=["']${SITE}${spec.route}["']|href=["']${SITE}${spec.route}["'][^>]*hreflang=["']${spec.lang}["']`).test(other.text);
      if (!back) out("canonical-hreflang", `${altRoute} does not link back to ${spec.route} (hreflang ${spec.lang})`);
    }
    if (robotsBlocks(robots.text, spec.route)) out("noindex", "robots.txt disallows route");
    if (sitemap.size && !sitemap.has(SITE + spec.route)) out("noindex", "route missing from sitemap");
  }
  if (lines.length) { console.log(lines.join("\n")); return 1; }
  console.log(`seo-gate: ${specs.length} route(s) passed`);
  return 0;
}

import { fileURLToPath } from "node:url";
if (process.argv[1] === fileURLToPath(import.meta.url)) runCli(process.argv.slice(2)).then((c) => process.exit(c));
