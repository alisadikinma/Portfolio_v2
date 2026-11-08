#!/usr/bin/env node
// SEO/GEO acceptance gate. Pure `checkHtml(html, routeSpec)` + CLI.
// No HTML parser dependency is installed, so a small tolerant tokenizer lives here.
import { SITE, PERSON_ID, ORG_ID, NAME, ROUTES, TITLE_MAX } from "./seo-gate.config.mjs";

const VOID = new Set(["area", "base", "br", "col", "embed", "hr", "img", "input", "link", "meta", "source", "track", "wbr"]);
const RAW = new Set(["script", "style"]);
// Opening one of these implicitly closes an open <p> (HTML parsing rule).
const CLOSES_P = new Set([
  "p", "div", "section", "article", "aside", "header", "footer", "main", "nav", "ul", "ol", "dl", "li", "dd", "dt",
  "h1", "h2", "h3", "h4", "h5", "h6", "table", "form", "fieldset", "figure", "figcaption", "blockquote", "pre",
  "hr", "address", "details", "summary", "menu",
]);
const escapeRe = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");

const NAMED = {
  nbsp: " ", quot: '"', apos: "'", lt: "<", gt: ">", rsquo: "\u2019", lsquo: "\u2018", ldquo: "\u201C", rdquo: "\u201D",
  ndash: "\u2013", mdash: "\u2014", hellip: "\u2026",
};
// Single pass, so "&amp;lt;" stays "&lt;" (no double decoding).
const decode = (s) =>
  s.replace(/&(?:#(\d{1,7})|#[xX]([0-9a-fA-F]{1,6})|([a-zA-Z]+)|(amp));/g, (m, dec, hex, name, amp) => {
    if (amp) return "&";
    if (name) return NAMED[name.toLowerCase()] ?? m;
    const n = dec !== undefined ? parseInt(dec, 10) : parseInt(hex, 16);
    return n > 0 && n < 0x110000 ? String.fromCodePoint(n) : m;
  });

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
    if (CLOSES_P.has(tag)) {
      for (let n = cur; n && n.parent; n = n.parent) {
        if (n.tag === "p") { cur = n.parent; break; }
        if (CLOSES_P.has(n.tag) || n.tag === "td" || n.tag === "th") break;
      }
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
export const norm = (s) => decode(s).replace(/\s+/g, " ").trim();
const words = (s) => norm(s).split(" ").filter(Boolean).length;
const hasKw = (text, kws) => kws.some((k) => text.toLowerCase().includes(k.toLowerCase()));
export const nodesOf = (v) => (Array.isArray(v) ? v : v ? [v] : []);
const typesOf = (n) => nodesOf(n["@type"]);
const refId = (v) => nodesOf(v).some((x) => x && x["@id"] === ORG_ID);
const HIDDEN_STYLE = /(?:^|;)\s*(?:display\s*:\s*none|visibility\s*:\s*hidden)\s*(?:!important\s*)?(?:;|$)/i;
const selfHidden = (e) =>
  e.attrs.hidden !== undefined || e.attrs["aria-hidden"] === "true" || HIDDEN_STYLE.test(e.attrs.style ?? "");
/** Hidden from users: noscript/template content, or hidden itself or via an ancestor. */
const isHidden = (el) => {
  for (let n = el; n && n.parent; n = n.parent) if (n.tag === "noscript" || n.tag === "template" || selfHidden(n)) return true;
  return false;
};
const headerGet = (h, name) => (!h ? "" : typeof h.get === "function" ? h.get(name) ?? "" : h[name] ?? h[name.toLowerCase()] ?? "");
const robotTokens = (v) => String(v).toLowerCase().split(/[\s,]+/).filter(Boolean);
const hasToken = (tokens, t) => tokens.includes(t) || tokens.includes("none");
const walk = (v, fn) => {
  if (Array.isArray(v)) v.forEach((x) => walk(x, fn));
  else if (v && typeof v === "object") for (const [k, x] of Object.entries(v)) { fn(k, v); walk(x, fn); }
};
const normPath = (p) => (p.length > 1 ? p.replace(/\/+$/, "") || "/" : p);
const abs = (u) => /^https:\/\/[^/]+/.test(u || "");

export const GATE_ITEMS = [
  "lang", "title", "description", "h1", "lede", "canonical-hreflang",
  "jsonld", "og", "img", "backlinks", "internal-links", "noindex",
];

/** @returns {{item:string, detail:string}[]} empty array = pass */
export function checkHtml(html, spec, headers = {}) {
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
    if (title.length > TITLE_MAX) fail("title", `${title.length} chars, max ${TITLE_MAX}`);
    if (!hasKw(title, kws.slice(0, 1))) fail("title", `missing primary keyword "${kws[0]}"`);
    if (spec.titleName !== false && !title.includes(NAME)) fail("title", `missing "${NAME}"`);
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
    if (!hasKw(t, kws.slice(0, 1))) fail("h1", `missing primary keyword "${kws[0]}" in "${t}"`);
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
  if (alts["x-default"] && alts["x-default"] !== SITE + spec.alternates?.en)
    fail("canonical-hreflang", `x-default is ${alts["x-default"]}, want ${SITE + spec.alternates?.en}`);
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
    walk(nodes, (key, owner) => {
      if (key === "aggregateRating" || key === "review")
        fail("jsonld", `${key} found on ${typesOf(owner).join(",") || "nested"} node`);
    });
  }

  // 8 OG
  for (const p of ["og:title", "og:description", "og:image", "og:url"]) {
    const c = meta("property", p)?.attrs.content;
    if (!c) fail("og", `missing ${p}`);
    else if (p === "og:image" && !abs(c)) fail("og", `og:image not absolute https: ${c}`);
    else if (p === "og:url" && c !== canonicalUrl) fail("og", `og:url "${c}", want ${canonicalUrl}`);
  }

  const ogW = Number(meta("property", "og:image:width")?.attrs.content);
  const ogH = Number(meta("property", "og:image:height")?.attrs.content);
  if (meta("property", "og:image:width") && meta("property", "og:image:height") && (!(ogW >= 1200) || !(ogH >= 630)))
    fail("og", `og:image ${ogW}x${ogH}, want >= 1200x630`);

  // 9 images
  for (const im of q("img")) {
    const src = im.attrs.src ?? "(no src)";
    const alt = im.attrs.alt;
    const decorative = alt === "" && im.attrs["aria-hidden"] === "true";
    if (alt === undefined || (alt.trim() === "" && !decorative)) fail("img", `${src}: empty/missing alt (decorative needs alt="" + aria-hidden="true")`);
    if (!im.attrs.width || !im.attrs.height) fail("img", `${src}: width/height not set`);
  }

  // 10 backlinks
  const robotsMeta = robotTokens(
    [meta("name", "robots")?.attrs.content, meta("name", "googlebot")?.attrs.content].filter(Boolean).join(","),
  );
  const robotsHeader = robotTokens(String(headerGet(headers, "x-robots-tag")).replace(/[a-z0-9_-]+\s*:/gi, ","));
  if (hasToken(robotsMeta, "nofollow")) fail("backlinks", "meta robots has nofollow, links will not be followed");
  if (hasToken(robotsHeader, "nofollow")) fail("backlinks", "X-Robots-Tag has nofollow, links will not be followed");
  const anchors = q("a").filter((a) => !isHidden(a)).map((a) => ({ ...a, href: a.attrs.href ?? "", label: norm(a.text), rel: (a.attrs.rel ?? "").toLowerCase().split(/\s+/) }));
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
    if (/^\/(?:api|storage)(?:\/|$)/.test(u.startsWith(SITE) ? u.slice(SITE.length) : u)) continue;
    if (u.startsWith(SITE)) u = u.slice(SITE.length) || "/";
    if (!u.startsWith("/") || u.startsWith("//")) continue;
    const path = normPath(u.split(/[?#]/)[0]);
    if (!path || path === normPath(spec.route)) continue;
    internal.add(path);
  }
  if (internal.size < 2) fail("internal-links", `${internal.size} contextual internal link(s), want >= 2`);

  // 12 noindex
  if (hasToken(robotsMeta, "noindex")) fail("noindex", `meta robots: ${robotsMeta.join(",")}`);
  if (hasToken(robotsHeader, "noindex")) fail("noindex", `X-Robots-Tag: ${headerGet(headers, "x-robots-tag")}`);

  // Extras from brief section 3 (not part of the 12): FAQ first question, no prices.
  if (spec.faq) {
    const faq = nodes.find((n) => typesOf(n).includes("FAQPage"));
    const first = faq && nodesOf(faq.mainEntity)[0]?.name;
    if (!first) fail("faq", "no FAQPage JSON-LD with a first question");
    else if (!hasKw(first, kws)) fail("faq", `first question lacks keyword: "${first}"`);
  }
  // News articles and case studies quote prices ("$10", "Rp 67"); the rule is about Ali's own services.
  const body = decode(q("body")[0]?.text ?? "");
  const price = spec.checkPrice === false ? null : body.match(/\b(?:Rp\.?|IDR|USD|US\$)\s?\d[\d.,]*|\$\s?\d[\d.,]*/i);
  if (price) fail("no-price", `price-like text "${price[0]}"`);

  return fails;
}

// ---------- CLI ----------

async function get(url) {
  const res = await fetch(url, {
    redirect: "manual",
    headers: { "User-Agent": "Mozilla/5.0 (compatible; GPTBot/1.0)" },
    signal: AbortSignal.timeout(10000),
  });
  return { status: res.status, text: await res.text(), headers: res.headers };
}

/**
 * RFC 9309: consecutive User-agent lines share one group; the group for the most
 * specific matching agent (else "*") applies; longest matching rule wins, Allow wins ties.
 */
export function robotsBlocks(robotsTxt, path, agent = "*") {
  const groups = [];
  let g = null;
  let inRules = false;
  for (const raw of robotsTxt.split(/\r?\n/)) {
    const line = raw.split("#")[0].trim();
    const i = line.indexOf(":");
    if (i === -1) continue;
    const k = line.slice(0, i).trim().toLowerCase();
    const v = line.slice(i + 1).trim();
    if (k === "user-agent") {
      if (!g || inRules) { g = { agents: [], rules: [] }; groups.push(g); inRules = false; }
      g.agents.push(v.toLowerCase());
    } else if ((k === "allow" || k === "disallow") && g) {
      inRules = true;
      if (v) g.rules.push({ allow: k === "allow", pattern: v });
    }
  }
  const want = agent.toLowerCase();
  let matched = groups.filter((x) => x.agents.includes(want));
  if (!matched.length) matched = groups.filter((x) => x.agents.includes("*"));
  const toRe = (pat) => new RegExp("^" + pat.split("*").map(escapeRe).join(".*").replace(/\\\$$/, "$"));
  let best = null;
  for (const grp of matched)
    for (const r of grp.rules) {
      if (!toRe(r.pattern).test(path)) continue;
      const len = r.pattern.length;
      if (!best || len > best.len || (len === best.len && r.allow)) best = { len, allow: r.allow };
    }
  return !!best && !best.allow;
}

async function sitemapUrls(base) {
  const out = new Set();
  const errors = [];
  const loc = (t) => [...t.matchAll(/<loc>\s*([^<]+?)\s*<\/loc>/g)].map((m) => m[1]);
  const root = await get(`${base}/sitemap.xml`);
  if (root.status !== 200 || !root.text.trim()) { errors.push(`sitemap.xml status ${root.status}${root.text.trim() ? "" : ", empty body"}`); return { urls: out, errors }; }
  for (const u of loc(root.text)) {
    if (u.endsWith(".xml")) {
      try {
        const sub = await get(u.replace(SITE, base));
        if (sub.status !== 200) errors.push(`${u} status ${sub.status}`);
        else loc(sub.text).forEach((x) => out.add(x));
      } catch (e) { errors.push(`${u} ${e.message}`); }
    } else out.add(u);
  }
  return { urls: out, errors };
}

export async function runCli(argv) {
  const arg = (n, d) => { const i = argv.indexOf(n); return i === -1 ? d : argv[i + 1]; };
  const base = arg("--base", "http://127.0.0.1:4321").replace(/\/$/, "");
  const group = arg("--routes", "all");
  const specs = ROUTES[group];
  if (!specs) { console.error(`unknown route group "${group}" (${Object.keys(ROUTES).join(", ")})`); return 2; }
  const lines = [];
  const robots = await get(`${base}/robots.txt`).catch((e) => ({ status: 0, text: "", err: e.message }));
  if (robots.status !== 200 || !robots.text.trim())
    lines.push(`* | noindex | robots.txt status ${robots.status}${robots.status === 200 ? ", empty body" : ""} ${robots.err ?? ""}`.trim());
  const sm = await sitemapUrls(base).catch((e) => ({ urls: new Set(), errors: [`sitemap.xml ${e.message}`] }));
  for (const err of sm.errors) lines.push(`* | noindex | ${err}`);
  const sitemap = sm.urls;
  for (const spec of specs) {
    const out = (item, detail) => lines.push(`${spec.route} | ${item} | ${detail}`);
    const res = await get(base + spec.route).catch((e) => ({ status: 0, text: "", err: e.message }));
    if (res.status !== 200) { out("http", `status ${res.status} ${res.err ?? ""}`.trim()); continue; }
    for (const f of checkHtml(res.text, spec, res.headers)) out(f.item, f.detail);
    // reciprocal hreflang: each alternate must link back to this page
    for (const l of ["id", "en"]) {
      const altRoute = spec.alternates[l];
      if (altRoute === spec.route) continue;
      const other = await get(base + altRoute).catch(() => null);
      const target = escapeRe(SITE + spec.route);
      const back = other && new RegExp(`hreflang=["']${spec.lang}["'][^>]*href=["']${target}["']|href=["']${target}["'][^>]*hreflang=["']${spec.lang}["']`).test(other.text);
      if (!back) out("canonical-hreflang", `${altRoute} does not link back to ${spec.route} (hreflang ${spec.lang})`);
    }
    for (const ua of ["*", "GPTBot", "Googlebot"])
      if (robots.status === 200 && robotsBlocks(robots.text, spec.route, ua)) out("noindex", `robots.txt disallows route for ${ua}`);
    if (sitemap.size && !sitemap.has(SITE + spec.route)) out("noindex", "route missing from sitemap");
  }
  if (lines.length) { console.log(lines.join("\n")); return 1; }
  console.log(`seo-gate: ${specs.length} route(s) passed`);
  return 0;
}

import { fileURLToPath } from "node:url";
if (process.argv[1] === fileURLToPath(import.meta.url)) runCli(process.argv.slice(2)).then((c) => process.exit(c));
