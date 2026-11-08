import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { readFileSync } from "node:fs";
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { resetApiCache } from "../lib/api";
import { checkHtml } from "../../scripts/seo-gate.mjs";
import { ROUTES } from "../../scripts/seo-gate.config.mjs";
import { SESSIONS } from "../lib/speaking";
import { id } from "../content/copy/id";
import { en } from "../content/copy/en";
// One dynamic file serves both /{lang}/pelatihan-ai|ai-training and /{lang}/pembicara-ai|ai-speaker
// (two sibling dynamic files at the same level would conflict in Astro routing).
import Page from "../pages/[lang]/[trainingSeg].astro";

const SLUGS = [
  { id: 13, slug: "1st-place-winner-global-ai-demo-day-2026-outskill-ai-generalist-fellowship" },
  { id: 10, slug: "alibaba-efounders-fellowship-hangzhou-china" },
  { id: 11, slug: "google-startup-grind-silicon-valley-san-fransisco" },
  { id: 7, slug: "1st-place-winner-nextdev-startup-competition" },
  { id: 8, slug: "1st-place-winner-wild-card-fenox-startup-world-cup-competition" },
];
let awardsDown = false;
let galleries = false;
// award id -> file names in its gallery (only the ones the page picks matter)
const FILES: Record<number, string[]> = {
  13: ["1777871190_item_20_3.jpg", "1777871190_item_20_0.png", "1777871190_item_20_4.jpg"],
  10: ["1761931433_item_12_0.jpg", "1761931433_item_12_3.jpg", "1761931433_item_12_9.jpg"],
  7: ["1761930891_item_9_1.jpg", "1761930891_item_9_2.jpg"],
  8: ["1761931360_item_10_1.jpg"],
};
let phone: string | undefined = "+62 812-3456 7890";
const ok = (data: unknown) => new Response(JSON.stringify({ success: true, data }), { status: 200 });

beforeEach(() => {
  resetApiCache();
  awardsDown = false;
  galleries = false;
  phone = "+62 812-3456 7890";
  vi.stubGlobal("fetch", vi.fn(async (url: string) => {
    const u = String(url);
    if (/\/settings\/site/.test(u)) return ok(phone ? { contact_phone: phone } : {});
    if (/\/awards(\?|$)/.test(u)) return awardsDown ? new Response("x", { status: 500 }) : ok(SLUGS);
    const g = u.match(/\/awards\/(\d+)\/galleries/);
    if (g && galleries) {
      const files = FILES[Number(g[1])] ?? [];
      return ok({ galleries: [{ id: 1, items: files.map((f, i) => ({ type: "image", file_url: `https://alisadikinma.com/storage/gallery/items/${f}`, sequence: i })) }] });
    }
    return ok({});
  }));
});
afterEach(() => vi.unstubAllGlobals());

async function load(lang: string, seg: string) {
  const c = await AstroContainer.create();
  const res = await c.renderToResponse(Page, {
    params: { lang, trainingSeg: seg },
    request: new Request(`https://alisadikinma.com/${lang}/${seg}`),
  });
  return { status: res.status, html: await res.text(), headers: res.headers };
}
const nodes = (html: string) => JSON.parse(html.match(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/)![1])["@graph"] as { "@type": string }[];
const presented = (html: string) => html.match(/<ul class="stages">([\s\S]*?)<\/ul>/)![1];
const CASES = [["id", "pembicara-ai"], ["en", "ai-speaker"]] as const;

describe("speaker page", () => {
  it.each(CASES)("passes the SEO gate (%s)", async (lang, seg) => {
    const { status, html, headers } = await load(lang, seg);
    expect(status).toBe(200);
    const spec = (ROUTES as unknown as { speaker: { lang: string }[] }).speaker.find((s) => s.lang === lang);
    expect(checkHtml(html, spec, headers)).toEqual([]);
  });

  it.each([["en", "pembicara-ai"], ["id", "ai-speaker"], ["fr", "pembicara-ai"], ["id", "x"]])("404 for %s/%s", async (lang, seg) => {
    expect((await load(lang, seg)).status).toBe(404);
  });

  it("training routes still resolve to the training page", async () => {
    expect((await load("id", "pelatihan-ai")).html).toContain("Segera dibuka");
    expect((await load("en", "ai-training")).status).toBe(200);
    expect((await load("id", "pelatihan-ai")).html).not.toContain('class="stages"');
  });

  it.each(CASES)("presented-at lists exactly the 4 events Ali presented at, never Startup Grind (%s)", async (lang, seg) => {
    const list = presented((await load(lang, seg)).html);
    for (const t of ["Global AI Demo Day", "UN-UNCTAD × Alibaba eFounders", "Telkomsel NextDev", "Fenox Startup World Cup"]) {
      expect(list).toContain(t);
    }
    expect((list.match(/<li/g) ?? []).length).toBe(4);
    expect(list).not.toMatch(/startup grind/i);
    expect(list).not.toContain("google-startup-grind");
  });

  it("each event links its award page (id: penghargaan, en: awards)", async () => {
    const idList = presented((await load("id", "pembicara-ai")).html);
    expect(idList).toContain('href="/id/penghargaan/alibaba-efounders-fellowship-hangzhou-china"');
    expect(idList).toContain('href="/id/penghargaan/1st-place-winner-nextdev-startup-competition"');
    const enList = presented((await load("en", "ai-speaker")).html);
    expect(enList).toContain('href="/en/awards/1st-place-winner-wild-card-fenox-startup-world-cup-competition"');
    expect((enList.match(/href="\/en\/awards\//g) ?? []).length).toBe(4);
  });

  it("awards API down: events still listed, unlinked, page still 200", async () => {
    awardsDown = true;
    const { status, html } = await load("id", "pembicara-ai");
    expect(status).toBe(200);
    const list = presented(html);
    expect(list).toContain("Telkomsel NextDev");
    expect(list).not.toContain("<a ");
  });

  it("hero shows Ali on stage at Global AI Demo Day (real gallery photo), not the studio portrait", async () => {
    galleries = true;
    const { html } = await load("id", "pembicara-ai");
    const hero = html.match(/<section class="sp-hero[\s\S]*?<\/section>/)![0];
    expect(hero).toContain("item_20_0");
    expect(hero).not.toContain("ali-sadikin-ma-pakar-ai-indonesia.jpg");
    expect(hero).toMatch(/<img [^>]*alt="[^"]{40,}"/);
    expect(hero).toContain(id.stages.demoDayLock);
    for (const k of ["cred1", "cred2", "cred3"] as const) expect(hero).toContain(id.speaker[k]);
  });

  it("hero without gallery data: no broken image, page still complete", async () => {
    const { html } = await load("en", "ai-speaker");
    const hero = html.match(/<section class="sp-hero[\s\S]*?<\/section>/)![0];
    expect(hero).not.toMatch(/<img/);
    expect(hero).toContain(en.speaker.h1);
  });

  it("each event card shows a real photo and opens that event's gallery", async () => {
    galleries = true;
    const list = presented((await load("id", "pembicara-ai")).html);
    expect((list.match(/<img /g) ?? []).length).toBe(4);
    // UNCTAD: Ali speaking with a mic, face visible (Ali 2026-11-09), not the 12_9 stranger
    for (const f of ["item_20_3", "item_12_3", "item_9_1", "item_10_1"]) expect(list).toContain(f);
    expect((list.match(/data-lightbox-items=/g) ?? []).length).toBe(4);
    expect(list).toContain("data-lightbox-thumbs=");
    expect(list).not.toContain("item_12_9.jpg\" alt");
  });

  it("the photo cards open: the page ships the lightbox dialog (Ali 2026-11-09: click did nothing)", async () => {
    galleries = true;
    const { html } = await load("id", "pembicara-ai");
    expect(html).toMatch(/<dialog class="lb" id="lb"/);
  });

  it("no gallery data: no lightbox dialog either", async () => {
    const { html } = await load("en", "ai-speaker");
    expect(html).not.toContain('id="lb"');
  });

  it("no video section and no claim of keynote / formats not in the source facts", async () => {
    for (const [l, s] of CASES) {
      const { html } = await load(l, s);
      expect(html).not.toMatch(/keynote/i);
      expect(html).not.toMatch(/<iframe|<video|youtube\.com\/embed/i);
    }
  });

  it("press kit: short + long bio, resume PDF in the page language, bio text download; no photo download (Ali 2026-11-09)", async () => {
    const { html } = await load("id", "pembicara-ai");
    const kit = html.match(/<section[^>]*id="press"[\s\S]*?<\/section>/)![0];
    expect(kit).toContain(id.speaker.shortBioPost.trim().slice(0, 30));
    expect(kit).toContain(id.speaker.longBio1.slice(0, 30));
    expect(kit).toMatch(/href="\/press\/ali-sadikin-ma-resume-id\.pdf"[^>]*download/);
    expect(kit).not.toMatch(/href="\/images\/[^"]*"[^>]*download/);
    expect(kit).toContain("ali-sadikin-ma-pakar-ai-indonesia.jpg"); // portrait still shown
    expect((await load("en", "ai-speaker")).html).toMatch(/href="\/press\/ali-sadikin-ma-resume-en\.pdf"[^>]*download/);
    expect(kit).toMatch(/href="\/press\/ali-sadikin-ma-bio\.txt"[^>]*download/);
    const en2 = (await load("en", "ai-speaker")).html;
    expect(en2).toContain(en.speaker.longBio1.slice(0, 30));
  });

  it("resume PDFs exist and are not the job-application CV", () => {
    for (const l of ["en", "id"]) {
      const pdf = readFileSync(new URL(`../../public/press/ali-sadikin-ma-resume-${l}.pdf`, import.meta.url));
      expect(pdf.subarray(0, 5).toString()).toBe("%PDF-");
      expect(pdf.toString("latin1")).not.toMatch(/Suzhou|Kunling/i);
    }
  });

  it("bio text file carries both languages' bios", () => {
    const txt = readFileSync(new URL("../../public/press/ali-sadikin-ma-bio.txt", import.meta.url), "utf8");
    expect(txt).toContain(id.speaker.longBio1);
    expect(txt).toContain(en.speaker.longBio1);
  });

  it("WhatsApp invite CTA links wa.me; no number means no link (never fake)", async () => {
    expect((await load("id", "pembicara-ai")).html).toContain('href="https://wa.me/6281234567890"');
    phone = undefined;
    resetApiCache();
    const { status, html } = await load("en", "ai-speaker");
    expect(status).toBe(200);
    expect(html).not.toContain("wa.me");
  });

  it("Service + FAQPage + Person JSON-LD, no offers/price", async () => {
    const { html } = await load("id", "pembicara-ai");
    const types = nodes(html).map((n) => n["@type"]);
    expect(types).toEqual(expect.arrayContaining(["Service", "FAQPage", "Person", "WebSite", "BreadcrumbList"]));
    expect(JSON.stringify(nodes(html))).not.toContain('"offers"');
    expect(html).not.toMatch(/Rp\s?\d/);
  });

  it.each([["id", "pembicara-ai", "https://indusia.ai/id/"], ["en", "ai-speaker", "https://indusia.ai/"]])(
    "bio line has dofollow founder INDUSIA.ai anchor (%s)", async (lang, seg, href) => {
      const { html } = await load(lang, seg);
      const m = html.match(/<a ([^>]*)>founder INDUSIA\.ai<\/a>/);
      expect(m).not.toBeNull();
      expect(m![1]).toContain(`href="${href}"`);
      expect(m![1]).not.toContain("rel=");
    });

  it("hreflang points to the paired URL", async () => {
    const { html } = await load("id", "pembicara-ai");
    expect(html).toContain('hreflang="en" href="https://alisadikinma.com/en/ai-speaker"');
  });
});

describe("speaking sessions data", () => {
  it("only the 4 presented events, only filled fields, no Startup Grind", () => {
    expect(SESSIONS.map((s) => s.event)).toEqual([
      "Global AI Demo Day", "UN-UNCTAD × Alibaba eFounders", "Telkomsel NextDev", "Fenox Startup World Cup",
    ]);
    expect(JSON.stringify(SESSIONS)).not.toMatch(/startup grind/i);
    for (const s of SESSIONS) {
      for (const v of Object.values(s)) expect(v === "" || v === null || v === undefined).toBe(false);
    }
  });
});

describe("FAQ illustration (Ali 2026-11-09)", () => {
  it.each([["id", "pembicara-ai"], ["en", "ai-speaker"], ["id", "pelatihan-ai"], ["en", "ai-training"]])("%s/%s: clay illustration next to the questions", async (lang, seg) => {
    const { html } = await load(lang, seg);
    const faq = html.match(/<section[^>]*aria-labelledby="faq-h"[\s\S]*?<\/section>/)![0];
    const img = faq.match(/<img [^>]*class="faq-illus"[^>]*>/)?.[0] ?? "";
    expect(img).toMatch(/faq-ali-sadikin-ma/);
    expect(img).toMatch(/alt="[^"]{40,}"/);
    expect(img).toContain('loading="lazy"');
    expect(faq.indexOf('id="faq-h"')).toBeLessThan(faq.indexOf("faq-illus"));
  });
});

describe("og:image per main page (Ali 2026-11-09: stage photo + proof)", () => {
  it.each([["id", "pembicara-ai", "speaker-id"], ["en", "ai-speaker", "speaker-en"], ["id", "pelatihan-ai", "training-id"], ["en", "ai-training", "training-en"]])("%s/%s uses /og/%s.jpg 1200x630", async (lang, seg, file) => {
    const { html } = await load(lang, seg);
    expect(html).toContain(`<meta property="og:image" content="https://alisadikinma.com/og/${file}.jpg">`);
    expect(html).toContain('<meta property="og:image:width" content="1200">');
    expect(html).toContain('<meta property="og:image:height" content="630">');
    expect(readFileSync(new URL(`../../public/og/${file}.jpg`, import.meta.url)).length).toBeGreaterThan(20000);
  });
});
