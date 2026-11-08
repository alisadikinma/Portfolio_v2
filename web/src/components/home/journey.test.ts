import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { ROUTES } from "../../../scripts/seo-gate.config.mjs";
import { resetApiCache } from "../../lib/api";
import { awardHref } from "../../lib/career";
import Journey from "./Journey.astro";
import Stages from "./Stages.astro";
import Lightbox from "../Lightbox.astro";

type Lang = "id" | "en";
const HOST = "https://alisadikinma.com/storage";
const COUNTS: Record<number, number> = { 14: 8, 9: 5, 11: 7, 13: 6, 10: 4, 12: 11, 16: 5 };
const AWARD_COUNTS: Record<number, number> = { 13: 7, 10: 11, 11: 6, 7: 5, 8: 4 };
const thumb = (id: number) => `${HOST}/gallery/thumbnails/thumb-${id}.png`;

const gallery = (id: number) => ({
  id,
  thumbnail: thumb(id),
  // reversed on purpose: the component must order by sequence
  items: Array.from({ length: COUNTS[id] }, (_, i) => ({ file_url: `${HOST}/g${id}-${i}.jpg`, sequence: i })).reverse(),
});
const awardGalleries = (id: number) => ({
  award: { id },
  galleries: [
    { id: id * 100, thumbnail: `${HOST}/at-${id}.png`, items: Array.from({ length: Math.ceil(AWARD_COUNTS[id] / 2) }, (_, i) => ({ image: `${HOST}/a${id}-${i}.jpg`, sequence: i })) },
    { id: id * 100 + 1, thumbnail: null, items: Array.from({ length: Math.floor(AWARD_COUNTS[id] / 2) }, (_, i) => ({ image: `${HOST}/a${id}-b${i}.jpg`, sequence: i })) },
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
  vi.stubGlobal("fetch", vi.fn(async (url: string) => respond(String(url))));
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

  it("uses gallery thumbnails for Singapore and Satnusa covers with object-position 50% 15%", async () => {
    const html = await render(Journey, lang);
    for (const id of [14, 16]) {
      const tag = html.match(new RegExp(`<img[^>]*src="${thumb(id)}"[^>]*>`))?.[0] ?? "";
      expect(tag, `gallery ${id}`).toContain("object-position:50% 15%");
      expect(tag).toContain('loading="lazy"');
    }
  });

  it("Marlin: static cover first + 33 gallery photos = 34, galleries in order", async () => {
    const html = await render(Journey, lang);
    const li = liOf(html, 'data-career="marlin"');
    expect(li).toContain('src="/images/career-marlin.jpg"');
    const items = itemsOf(li);
    expect(items).toHaveLength(34);
    expect(items[0]).toBe("/images/career-marlin.jpg");
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
    expect(li).toContain("indusia-avi.webp");
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
    const sg = liOf(html, "jr-yr");
    expect((html.match(/<li/g) ?? []).length).toBe(4);
    expect(html).toContain(lang === "id" ? "Software Consultant → Solution Support" : "Software Consultant");
    expect(html).toContain(lang === "id" ? "Delapan tahun" : "Eight years");
    expect(sg).toBeTruthy();
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
    const lis = html.split("<li").slice(1);
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

  it("survives /api/awards failing (no slugs)", async () => {
    failing.add("/api/awards");
    const html = await render(Stages, lang);
    expect(html.split("<li").length - 1).toBe(5);
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
  });
});
