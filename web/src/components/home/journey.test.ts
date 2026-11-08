import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { ROUTES } from "../../../scripts/seo-gate.config.mjs";
import { checkHtml } from "../../../scripts/seo-gate.mjs";
import { resetApiCache } from "../../lib/api";
import { awardHref, ownMediaUrl } from "../../lib/career";
import Journey from "./Journey.astro";
import Stages from "./Stages.astro";
import Lightbox from "../Lightbox.astro";

type Lang = "id" | "en";
const HOST = "https://alisadikinma.com/storage";
const COUNTS: Record<number, number> = { 14: 8, 9: 5, 11: 7, 13: 6, 10: 4, 12: 11, 16: 5 };
const AWARD_COUNTS: Record<number, number> = { 13: 7, 10: 11, 11: 6, 7: 5, 8: 4 };
const thumb = (id: number) => `${HOST}/gallery/thumbnails/thumb-${id}.png`;

const variants = (name: string) => ({ "320w": `/storage/v/${name}-320.webp`, "640w": `/storage/v/${name}-640.webp`, "1920w": `/storage/v/${name}-1920.webp` });
// extra junk that must be filtered out of gallery 14: placeholder host, video, foreign host
let junk = false;
const gallery = (id: number) => ({
  id,
  thumbnail: thumb(id),
  // reversed on purpose: the component must order by sequence
  items: [
    ...Array.from({ length: COUNTS[id] }, (_, i) => ({
      type: "image", file_url: `${HOST}/g${id}-${i}.jpg`, sequence: i,
      image_variants: id === 14 && i === 0 ? variants("g14-0") : null,
    })).reverse(),
    ...(junk && id === 14
      ? [
          { type: "image", file_url: "https://via.placeholder.com/800x600/e5e7eb/6b7280?text=Image", sequence: 50 },
          { type: "video", file_url: `${HOST}/clip.mp4`, sequence: 51 },
          { type: "image", file_url: "https://evil.example/x.jpg", sequence: 52 },
        ]
      : []),
  ],
});
const awardGalleries = (id: number) => ({
  award: { id },
  galleries: [
    { id: id * 100, thumbnail: `${HOST}/at-${id}.png`, items: Array.from({ length: Math.ceil(AWARD_COUNTS[id] / 2) }, (_, i) => ({ type: "image", image: `${HOST}/a${id}-${i}.jpg`, sequence: i, image_variants: id === 13 && i === 0 ? variants("a13-0") : null })) },
    { id: id * 100 + 1, thumbnail: null, items: Array.from({ length: Math.floor(AWARD_COUNTS[id] / 2) }, (_, i) => ({ type: "image", image: `${HOST}/a${id}-b${i}.jpg`, sequence: i })) },
  ],
});

let failing: Set<string>;
let slugs: Record<number, string>;
function respond(url: string): Response {
  const u = new URL(url);
  const ok = (data: unknown) => new Response(JSON.stringify({ success: true, data }), { status: 200 });
  if (failing.has(u.pathname)) return new Response("boom", { status: 500 });
  let m: RegExpMatchArray | null;
  if ((m = u.pathname.match(/\/galleries\/(\d+)$/))) return ok(gallery(Number(m[1])));
  if ((m = u.pathname.match(/\/awards\/(\d+)\/galleries$/))) return ok(awardGalleries(Number(m[1])));
  if (u.pathname.endsWith("/awards")) return ok(Object.keys(AWARD_COUNTS).map((id) => ({ id: Number(id), ...(slugs[Number(id)] ? { slug: slugs[Number(id)] } : {}) })));
  return new Response("{}", { status: 404 });
}

beforeEach(() => {
  resetApiCache();
  failing = new Set();
  slugs = {};
  junk = false;
  vi.stubGlobal("fetch", vi.fn(async (url: string) => respond(String(url))));
  vi.spyOn(console, "warn").mockImplementation(() => undefined);
});
afterEach(() => vi.unstubAllGlobals());

async function render(C: unknown, lang: Lang) {
  const c = await AstroContainer.create();
  return c.renderToString(C as never, { props: { lang } });
}
const liOf = (html: string, marker: string) => {
  const chunks = html.split("<li");
  return chunks.find((c) => c.includes(marker)) ?? "";
};
const itemsOf = (chunk: string): string[] => {
  const m = chunk.match(/data-lightbox-items="([^"]*)"/);
  return m ? (JSON.parse(m[1].replace(/&quot;/g, '"').replace(/&amp;/g, "&")) as string[]) : [];
};
const anchor = (html: string, text: string) => [...html.matchAll(/<a [^>]*>([^<]*)<\/a>/g)].find((m) => m[1] === text)?.[0] ?? "";

describe.each(["id", "en"] as const)("Journey (%s)", (lang) => {
  it("renders 4 chapters in an ordered list under #perjalanan", async () => {
    const html = await render(Journey, lang);
    expect(html).toContain('id="perjalanan"');
    expect(html).toMatch(/<ol class="journey/);
    expect((html.match(/<li/g) ?? []).length).toBe(4);
  });

  it("journey illustration (from indusia.ai/founder) sits between the lead and the chapter list: sized, lazy, alt", async () => {
    const html = await render(Journey, lang);
    const fig = html.match(/<figure class="jr-illus"[\s\S]*?<\/figure>/)?.[0] ?? "";
    expect(fig).not.toBe("");
    const img = fig.match(/<img [^>]*>/)![0];
    expect(img).toMatch(/founder-journey/);
    expect(img).toMatch(/alt="[^"]{30,}"/);
    expect(img).toContain('width="');
    expect(img).toContain('height="');
    expect(img).toContain('loading="lazy"');
    expect(html.indexOf('class="lead"')).toBeLessThan(html.indexOf("jr-illus"));
    expect(html.indexOf("jr-illus")).toBeLessThan(html.indexOf('<ol class="journey"'));
  });

  it("illustration is an animated loop (Ali 2026-11-09): muted, inline, looping video over a still with alt", async () => {
    const html = await render(Journey, lang);
    const fig = html.match(/<figure class="jr-illus"[\s\S]*?<\/figure>/)?.[0] ?? "";
    const video = fig.match(/<video[^>]*>/)?.[0] ?? "";
    for (const a of ["autoplay", "muted", "loop", "playsinline", 'aria-hidden="true"']) expect(video).toContain(a);
    expect(video).toMatch(/poster="[^"]+"/);
    expect(fig).toContain('src="/videos/journey-loop.webm" type="video/webm"');
    expect(fig).toContain('src="/videos/journey-loop.mp4" type="video/mp4"');
    expect(fig.match(/<img [^>]*class="jr-still"[^>]*>/)?.[0]).toMatch(/alt="[^"]{30,}"/);
  });

  it("each island in the illustration is clickable: opens that chapter's gallery; INDUSIA.ai links out", async () => {
    // Ali 2026-11-08: "di ilustrasi tiap section bisa diklik untuk buka gallery"
    const html = await render(Journey, lang);
    const fig = html.match(/<figure class="jr-illus"[\s\S]*?<\/figure>/)?.[0] ?? "";
    const spots = [...fig.matchAll(/<button[^>]*data-hotspot="([^"]+)"[^>]*>/g)];
    expect(spots.map((m) => m[1])).toEqual(["singapore", "marlin", "satnusa"]);
    const itemsOfTag = (t: string) => JSON.parse((t.match(/data-lightbox-items="([^"]*)"/)?.[1] ?? "[]").replace(/&quot;/g, '"').replace(/&amp;/g, "&"));
    for (const [tag, key] of spots.map((m) => [m[0], m[1]] as const)) {
      expect(tag).toContain("data-lightbox");
      expect(tag).toMatch(/aria-label="[^"]+"/);
      const card = html.match(new RegExp(`<button[^>]*class="jr-card"[^>]*data-career="${key === "singapore" ? "14" : key === "satnusa" ? "16" : "marlin"}"[^>]*>`))![0];
      expect(itemsOfTag(tag)).toEqual(itemsOfTag(card));
    }
    const out = fig.match(/<a [^>]*data-hotspot="indusia"[^>]*>/)?.[0] ?? "";
    expect(out).toMatch(/href="https:\/\/indusia\.ai\//);
    expect(out).toMatch(/aria-label="[^"]+"/);
  });

  it("one board: chapter text sits under the islands, no duplicate photo cards (Ali 2026-11-08)", async () => {
    const html = await render(Journey, lang);
    const board = html.match(/<div class="jr-board"[\s\S]*<\/ol>/)?.[0] ?? "";
    expect(board).toContain('class="jr-illus"');
    expect(board).toContain('<ol class="journey"');
    const list = html.match(/<ol class="journey"[\s\S]*?<\/ol>/)![0];
    expect(list).not.toMatch(/<img|<svg/);
    expect([...list.matchAll(/<li class="(c\d)/g)].map((m) => m[1])).toEqual(["c1", "c2", "c3", "c4"]);
  });

  it("lightbox items use the 1920w variant when present, the original otherwise", async () => {
    const html = await render(Journey, lang);
    const items = itemsOf(liOf(html, 'data-career="14"'));
    expect(items[0]).toBe("https://alisadikinma.com/storage/v/g14-0-1920.webp");
    expect(items[1]).toBe(`${HOST}/g14-1.jpg`);
  });

  it("drops placeholder, video and foreign-host items", async () => {
    junk = true;
    const html = await render(Journey, lang);
    const li = liOf(html, 'data-career="14"');
    expect(itemsOf(li)).toHaveLength(8);
    expect(li).toContain(lang === "id" ? "8 foto" : "8 photos");
    expect(html).not.toMatch(/via\.placeholder|evil\.example|clip\.mp4/);
  });

  it("ownMediaUrl also accepts media on the configured SITE_URL host (local/staging stack)", () => {
    vi.stubEnv("SITE_URL", "http://localhost:8000");
    try {
      expect(ownMediaUrl("http://localhost:8000/storage/gallery/a.jpg")).toBe("http://localhost:8000/storage/gallery/a.jpg");
      expect(ownMediaUrl("https://evil.example/storage/a.jpg")).toBeNull();
    } finally {
      vi.unstubAllEnvs();
    }
  });

  it("ownMediaUrl accepts only site/API origin media", () => {
    expect(ownMediaUrl("https://alisadikinma.com/storage/a.jpg")).toBe("https://alisadikinma.com/storage/a.jpg");
    expect(ownMediaUrl("/storage/a.jpg")).toBe("https://alisadikinma.com/storage/a.jpg");
    for (const bad of ["https://via.placeholder.com/1x1", "javascript:alert(1)", "", null, 3]) expect(ownMediaUrl(bad)).toBeNull();
  });

  it("Marlin: static cover first + 33 gallery photos = 34, galleries in order", async () => {
    const html = await render(Journey, lang);
    const li = liOf(html, 'data-career="marlin"');
    const items = itemsOf(li);
    expect(items).toHaveLength(34);
    expect(items[0]).toContain("career-marlin");
    expect(items[1]).toBe(`${HOST}/g9-0.jpg`);
    expect(items[6]).toBe(`${HOST}/g11-0.jpg`);
    expect(li).toContain(lang === "id" ? "34 foto" : "34 photos");
  });

  it("shows real photo counts for Singapore (8) and Satnusa (5)", async () => {
    const html = await render(Journey, lang);
    const n = (k: number) => (lang === "id" ? `${k} foto` : `${k} photos`);
    expect(liOf(html, 'data-career="14"')).toContain(n(8));
    expect(liOf(html, 'data-career="16"')).toContain(n(5));
    expect(itemsOf(liOf(html, 'data-career="14"'))).toHaveLength(8);
  });

  it("INDUSIA chapter: dofollow exact backlinks, no nested anchors", async () => {
    const html = await render(Journey, lang);
    const page = ROUTES.home.find((r: { lang: string }) => r.lang === lang)!;
    for (const key of ["vision", "fleet", "chatbot"]) {
      const want = ({ vision: "machine vision Indonesia", fleet: "fleet management system", chatbot: lang === "id" ? "chatbot WhatsApp AI" : "WhatsApp AI chatbot" })[key]!;
      const b = page.backlinks.find((x: { anchor: string }) => x.anchor === want)!;
      expect(b, key).toBeDefined();
      const a = anchor(html, b.anchor);
      expect(a).toContain(`href="${b.href}"`);
      expect(a).not.toMatch(/\srel=/);
    }
    const li = liOf(html, "jr-now");
    expect(li).not.toContain("PLACEHOLDER");
    // one link to the INDUSIA home in the chapter text
    expect((li.match(new RegExp(`href="${page.backlinks[0].href.replace(/[/.]/g, "\\$&")}"`, "g")) ?? []).length).toBe(1);
    expect(li).toContain(lang === "id" ? "2026–sekarang" : "2026–present");
    expect(li).not.toMatch(/<a[^>]*>(?:(?!<\/a>)[\s\S])*<a /);
  });

  it("has no chapter for the 2019–2021 gap and no forbidden facts", async () => {
    const html = await render(Journey, lang);
    expect(html).not.toContain("2019–2021");
    expect(html).not.toMatch(/56 produk AI|56 AI products/i);
    expect(html).toMatch(/56\+/);
  });

  it("a failed gallery hides its count but keeps the chapter text; others still render", async () => {
    failing.add("/api/galleries/14");
    failing.add("/api/galleries/11");
    const html = await render(Journey, lang);
    expect((html.match(/<li/g) ?? []).length).toBe(4);
    expect(html).toContain(lang === "id" ? "Software Consultant → Solution Support" : "Software Consultant");
    expect(html).toContain(lang === "id" ? "Delapan tahun" : "Eight years");
    // Singapore: no count, no lightbox trigger
    expect(html).not.toContain('data-career="14"');
    // Marlin: gallery 11 missing -> no (misleading) count, still opens with what loaded
    const marlin = liOf(html, 'data-career="marlin"');
    expect(marlin).not.toMatch(/\d+ (foto|photos)/);
    expect(itemsOf(marlin)).toHaveLength(34 - 7);
    // Satnusa unaffected
    expect(liOf(html, 'data-career="16"')).toMatch(lang === "id" ? /5 foto/ : /5 photos/);
  });

  it("survives every gallery failing", async () => {
    for (const id of Object.keys(COUNTS)) failing.add(`/api/galleries/${id}`);
    const html = await render(Journey, lang);
    expect((html.match(/<li/g) ?? []).length).toBe(4);
    expect(html).toContain("Marlin Booking");
    expect(html).not.toMatch(/\d+ (foto|photos)/);
  });
});

describe.each(["id", "en"] as const)("Stages (%s)", (lang) => {
  it("renders awards 13 (main), 10, 11, 7, 8 in order with gold locks on 13, 7, 8", async () => {
    const html = await render(Stages, lang);
    const lis = html.split("<li").slice(1);
    expect(lis).toHaveLength(5);
    expect(lis[0]).toContain("bt-main");
    expect(lis[0]).toContain("Global AI Demo Day");
    expect(lis[1]).toContain("UN-UNCTAD");
    expect(lis[2]).toContain("Google Startup Grind");
    expect(lis[3]).toContain("Telkomsel NextDev");
    expect(lis[4]).toContain("Fenox");
    const locked = lis.map((l) => l.includes("bt-lock"));
    expect(locked).toEqual([true, false, false, true, true]);
    expect(lis[3]).toContain("gold-sm");
  });

  it("labels Startup Grind as attended, never performed", async () => {
    const html = await render(Stages, lang);
    const grind = html.split("<li")[3];
    expect(grind).toContain(lang === "id" ? "peserta" : "attendee");
    expect(html).not.toMatch(/tampil|speaker at Google|spoke at Google/i);
    expect(html.match(/peserta|attendee/g)?.length).toBe(1);
  });

  it("without slugs every card is a lightbox-only <button> with the real gallery photos", async () => {
    const html = await render(Stages, lang);
    expect(html).not.toMatch(/\/penghargaan\/|\/awards\//);
    const lis = html.split("</ul>")[0].split("<li").slice(1); // bento only, not the "see all" link after it
    for (const [idx, id] of [13, 10, 11, 7, 8].entries()) {
      expect(lis[idx]).toMatch(/<button[^>]*data-lightbox/);
      expect(lis[idx]).not.toMatch(/<a /);
      expect(itemsOf(lis[idx])).toHaveLength(AWARD_COUNTS[id]);
      expect(lis[idx]).toContain(lang === "id" ? `${AWARD_COUNTS[id]} foto` : `${AWARD_COUNTS[id]} photos`);
    }
  });

  it("with a slug the card is an <a href> and a SEPARATE button opens the lightbox (no nesting)", async () => {
    slugs = { 13: "1st-place-global-ai-demo-day-2026" };
    const html = await render(Stages, lang);
    const href = lang === "id" ? "/id/penghargaan/1st-place-global-ai-demo-day-2026" : "/en/awards/1st-place-global-ai-demo-day-2026";
    const lis = html.split("<li").slice(1);
    expect(lis[0]).toContain(`<a`);
    expect(lis[0]).toContain(`href="${href}"`);
    const a = lis[0].match(/<a[\s\S]*?<\/a>/)![0];
    expect(a).not.toContain("<button");
    expect(a).not.toContain("data-lightbox");
    expect(lis[0]).toMatch(/<button[^>]*data-lightbox/);
    // other awards (no slug) keep the button-only card
    expect(lis[1]).not.toMatch(/<a /);
  });

  it("a failed award gallery keeps the card text but offers no lightbox or count", async () => {
    failing.add("/api/awards/10/galleries");
    const html = await render(Stages, lang);
    const lis = html.split("<li").slice(1);
    expect(lis).toHaveLength(5);
    expect(lis[1]).toContain("UN-UNCTAD");
    expect(lis[1]).not.toContain("data-lightbox");
    expect(lis[1]).not.toMatch(/\d+ (foto|photos)/);
    expect(lis[0]).toContain("data-lightbox");
  });

  it("main card cover uses the first photo's variants (srcset + sizes), lightbox the 1920w variant", async () => {
    const html = await render(Stages, lang);
    const main = html.split("<li").slice(1)[0];
    const img = main.match(/<img[^>]*>/)![0];
    expect(img).toContain("a13-0-320.webp 320w");
    expect(img).toMatch(/sizes="/);
    expect(img).toMatch(/width="\d+" height="\d+"/);
    expect(itemsOf(main)[0]).toBe("https://alisadikinma.com/storage/v/a13-0-1920.webp");
    expect(itemsOf(main)[1]).toBe(`${HOST}/a13-b0.jpg`);
  });

  it("card buttons keep their visible content as the name (no aria-label) and add a hidden open hint", async () => {
    const html = await render(Stages, lang);
    const lis = html.split("<li").slice(1);
    for (const l of lis) {
      expect(l.match(/<button[^>]*>/)![0]).not.toContain("aria-label");
      expect(l).toContain(lang === "id" ? "buka galeri" : "open gallery");
    }
    expect(lis[2]).toContain(lang === "id" ? "peserta" : "attendee");
  });

  it("gold lock text is available to screen readers inside the caption", async () => {
    const html = await render(Stages, lang);
    const lis = html.split("<li").slice(1);
    const lock = lang === "id" ? "Juara 1 Wild Card" : "1st place Wild Card";
    expect(lis[4]).toMatch(new RegExp(`class="sr"[^>]*>${lock}<`));
    expect(lis[1]).not.toContain('class="sr"><');
  });

  it("covers use neutral alts (no claim tied to one photo)", async () => {
    const html = await render(Stages, lang);
    expect(html).not.toMatch(/menerima|receiving|juara 1 Telkomsel|1st place at Telkomsel/i);
    expect(html).toContain(lang === "id" ? "Ali Sadikin Ma di Global AI Demo Day 2026, Bengaluru" : "Ali Sadikin Ma at Global AI Demo Day 2026, Bengaluru");
  });

  it(".bt-open accessible name includes its visible count text", async () => {
    slugs = { 13: "abc-def" };
    const html = await render(Stages, lang);
    const btn = html.match(/<button[^>]*class="bt-open"[^>]*>[^<]*<\/button>/)![0];
    const visible = btn.match(/>([^<]*)<\/button>/)![1];
    const aria = btn.match(/aria-label="([^"]*)"/)![1];
    expect(visible).toBe(lang === "id" ? "7 foto" : "7 photos");
    expect(aria.startsWith(visible)).toBe(true);
    expect(aria).toContain("Global AI Demo Day");
  });

  it("survives /api/awards failing (no slugs)", async () => {
    failing.add("/api/awards");
    const html = await render(Stages, lang);
    expect(html.split("<li").length - 1).toBe(5);
  });
});

describe("awardHref (slug safety)", () => {
  it("rejects slugs outside [a-z0-9-]", () => {
    for (const slug of ["A", "a/b", "a b", "../x", "a?b", "é"]) expect(awardHref({ slug }, "id")).toBeNull();
  });
});

describe("awardHref", () => {
  it("returns localized URLs only for a non-empty string slug", () => {
    expect(awardHref({ slug: "abc" }, "id")).toBe("/id/penghargaan/abc");
    expect(awardHref({ slug: "abc" }, "en")).toBe("/en/awards/abc");
    for (const slug of [undefined, null, "", 5, {}]) expect(awardHref({ slug }, "id")).toBeNull();
    expect(awardHref({}, "en")).toBeNull();
  });
});

describe("Lightbox.astro", () => {
  it.each(["id", "en"] as const)("renders the dialog hooks the script needs (%s)", async (lang) => {
    const html = await render(Lightbox, lang);
    expect(html).toMatch(/<dialog[^>]*id="lb"/);
    for (const hook of ['data-lb="close"', 'data-lb="prev"', 'data-lb="next"', "data-lb-title", "data-lb-count"]) expect(html).toContain(hook);
    expect(html).toContain(lang === "id" ? 'aria-label="Tutup galeri"' : 'aria-label="Close gallery"');
    expect(html).toContain('data-alt="');
    expect(html).toContain("data-lb-error");
    // no <img> in the server HTML: nothing for the SEO gate to flag, created on first open
    expect(html).not.toContain("<img");
  });

  it("passes the SEO gate img rules when embedded in a page", async () => {
    const html = await render(Lightbox, "id");
    const page = `<!doctype html><html lang="id"><head><title>t</title></head><body>${html}</body></html>`;
    const spec = (ROUTES.home as { lang: string }[])[0];
    const issues = (checkHtml(page, spec as never, new Headers()) as { rule?: string; id?: string }[]).filter((i) => JSON.stringify(i).includes("img"));
    expect(issues).toEqual([]);
  });
});
