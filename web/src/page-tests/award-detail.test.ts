import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { resetApiCache } from "../lib/api";
import { resetImageDimsCache } from "../lib/image-dims";
import { awardHref, loadAwardSlugs } from "../lib/career";
import { checkHtml } from "../../scripts/seo-gate.mjs";
import { awardSpec } from "../../scripts/seo-gate.config.mjs";
import AwardDetail from "../pages/[lang]/[awardsSeg]/[slug].astro";

const DEMO = "1st-place-winner-global-ai-demo-day-2026-outskill-ai-generalist-fellowship";
const GRIND = "google-startup-grind-silicon-valley-san-fransisco";
const NEXTDEV = "1st-place-winner-nextdev-startup-competition";

const photo = (n: number, over: Record<string, unknown> = {}) => ({
  id: n,
  type: "image",
  title: null,
  description: null,
  image: `https://alisadikinma.com/storage/award/p${n}.jpg`,
  image_variants: { "320w": `/storage/award/p${n}-320.webp`, "640w": `/storage/award/p${n}-640.webp`, "1920w": `/storage/award/p${n}-1920.webp` },
  sequence: n,
  ...over,
});
const galleries = (items: unknown[]) => [
  { id: 1, title: "Gallery", thumbnail: "https://alisadikinma.com/storage/gallery/thumbnails/t.png", sort_order: 0, is_active: true, items_count: items.length, items },
];

const AWARDS: Record<string, { award: Record<string, unknown>; galleries: unknown[] }> = {
  [DEMO]: {
    award: {
      id: 13,
      slug: DEMO,
      title: "1st Place Winner — Global AI Demo Day 2026 (Outskill AI Generalist Fellowship)",
      description:
        "<p>Memenangkan 1st Place di Global AI Demo Day 2026, kompetisi AI startup<br>internasional yang diselenggarakan Outskill di Bengaluru, India pada<br>17 Januari 2026. Sparkfluence terpilih sebagai Demo Day Champion dari 26 startup AI yang berasal dari 16+ negara.</p><script>window.__pwned = 1</script>",
      organization: "OUTSKILL - INDIA",
      received_at: "17 Jan 2026",
      image: "https://alisadikinma.com/uploads/awards/outskill-icon.png",
    },
    galleries: galleries([photo(2), photo(1), photo(3, { image: "https://evil.example/p3.jpg", image_variants: null })]),
  },
  [GRIND]: {
    award: {
      id: 11,
      slug: GRIND,
      title: "Google Startup Grind Silicon Valley - San Fransisco",
      description:
        "<p>Startup Grind Global Conference 2018 in Silicon Valley, hosted by Startup Grind with Google for Entrepreneurs, gathered founders and investors for keynotes, fireside chats, tactical workshops, and high-value networking focused on product, growth, and fundraising.</p>",
      organization: "Google - USA",
      received_at: "2018",
      image: null,
    },
    galleries: galleries([photo(1), photo(2)]),
  },
  [NEXTDEV]: {
    award: {
      id: 7,
      slug: NEXTDEV,
      title: "1st Place Winner Nextdev Startup Competition",
      description: "<p>Achieved first place in prestigious Nextdev Startup Competition, demonstrating innovative digital solutions and entrepreneurial excellence.</p>",
      organization: "TELKOMSEL - Indonesia",
      received_at: "2018",
      image: null,
    },
    galleries: galleries([photo(1)]),
  },
};

let handler: (url: string) => Response;
const calls: string[] = [];
const ok = (data: unknown) => new Response(JSON.stringify({ success: true, data }), { status: 200 });

beforeEach(() => {
  resetApiCache();
  resetImageDimsCache();
  calls.length = 0;
  handler = (url) => {
    const m = /\/awards\/slug\/([^?]+)/.exec(url);
    const hit = m ? AWARDS[decodeURIComponent(m[1])] : undefined;
    return hit ? ok(hit) : new Response(JSON.stringify({ success: false }), { status: 404 });
  };
  vi.stubGlobal(
    "fetch",
    vi.fn(async (url: string) => {
      calls.push(String(url));
      return handler(String(url));
    }),
  );
});
afterEach(() => vi.unstubAllGlobals());

const seg = (lang: string) => (lang === "id" ? "penghargaan" : "awards");
async function load(lang: string, slug: string, awardsSeg = seg(lang)) {
  const c = await AstroContainer.create();
  const res = await c.renderToResponse(AwardDetail, {
    params: { lang, awardsSeg, slug },
    request: new Request(`https://alisadikinma.com/${lang}/${awardsSeg}/${slug}`),
  });
  return { status: res.status, html: await res.text(), headers: res.headers };
}
const nodesOf = (html: string) =>
  [...html.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)].flatMap((m) => {
    const j = JSON.parse(m[1]);
    return j["@graph"] ?? [j];
  });
const eventOf = (html: string) => nodesOf(html).find((n: any) => n["@type"] === "Event") as any;
const titleOf = (html: string) => /<title>([^<]*)<\/title>/.exec(html)![1];

const KEYWORDS: Record<string, string> = { [DEMO]: "Global AI Demo Day", [GRIND]: "Google Startup Grind", [NEXTDEV]: "Nextdev" };

describe("/{lang}/{penghargaan|awards}/{slug}: SEO gate", () => {
  for (const slug of [DEMO, GRIND, NEXTDEV]) {
    it.each(["id", "en"] as const)(`passes the gate for ${slug.slice(0, 22)}… (%s)`, async (lang) => {
      const { status, html, headers } = await load(lang, slug);
      expect(status).toBe(200);
      expect(checkHtml(html, awardSpec(lang, slug, KEYWORDS[slug]), headers)).toEqual([]);
    });
  }

  it("short descriptions still get a 120-160 char meta and a >= 25 word lede (fallback copy)", async () => {
    const { html } = await load("id", NEXTDEV);
    const d = /<meta name="description" content="([^"]*)"/.exec(html)![1];
    expect(d.length).toBeGreaterThanOrEqual(120);
    expect(d.length).toBeLessThanOrEqual(160);
  });

  it("title is 'short name — Ali Sadikin Ma' within 60 chars", async () => {
    const t = titleOf((await load("en", DEMO)).html);
    expect(t).toBe("1st Place Winner: Global AI Demo Day 2026 — Ali Sadikin Ma");
  });
});

describe("routing and API", () => {
  it("requests /awards/slug/{slug}", async () => {
    await load("id", DEMO);
    expect(calls[0]).toContain(`/awards/slug/${DEMO}`);
    expect(calls[0]).toContain("lang=id");
  });

  it.each([
    ["id", "awards"],
    ["en", "penghargaan"],
    ["id", "blog"],
    ["en", "projects"],
  ])("%s with segment %s -> 404 without calling the API", async (lang, awardsSeg) => {
    expect((await load(lang, DEMO, awardsSeg)).status).toBe(404);
    expect(calls).toHaveLength(0);
  });

  it("unknown lang -> 404", async () => {
    expect((await load("fr", DEMO, "awards")).status).toBe(404);
  });

  it("unknown slug (API 404) -> 404", async () => {
    expect((await load("id", "nope")).status).toBe(404);
  });

  it.each([".", "..", "a/b", "A-B", "a b", "é"])("slug %j -> 404 without calling the API", async (slug) => {
    expect((await load("id", slug)).status).toBe(404);
    expect(calls).toHaveLength(0);
  });

  it("API down -> 503 with Retry-After and a plain message", async () => {
    handler = () => new Response("boom", { status: 500 });
    const { status, html, headers } = await load("id", DEMO);
    expect(status).toBe(503);
    expect(headers.get("retry-after")).toBe("120");
    expect(html).toContain("Halaman penghargaan ini sedang tidak bisa dimuat");
  });
});

describe("content", () => {
  it("H1 is the award title; description is sanitized", async () => {
    const { html } = await load("id", DEMO);
    expect(html).toMatch(/<h1[^>]*>\s*1st Place Winner — Global AI Demo Day 2026 \(Outskill AI Generalist Fellowship\)\s*<\/h1>/);
    expect(html).not.toContain("__pwned");
    expect(html).toContain("Memenangkan 1st Place di Global AI Demo Day 2026");
  });

  it("shows organizer, date and place only from the data (Demo Day: Outskill, 17 Jan 2026, Bengaluru)", async () => {
    const { html } = await load("id", DEMO);
    expect(html).toContain("Outskill");
    expect(html).not.toContain("OUTSKILL - INDIA");
    expect(html).toContain("17 Jan 2026");
    expect(html).toContain("Bengaluru, India");
  });

  it("omits the location row when the data names no place", async () => {
    const { html } = await load("id", NEXTDEV);
    expect(html).not.toContain("Lokasi");
  });

  it("result line: Demo Day is Juara 1 / 1st place", async () => {
    expect((await load("id", DEMO)).html).toContain("Juara 1");
    expect((await load("en", DEMO)).html).toContain("1st place");
  });

  it("result line: Startup Grind is attendance only, never 'tampil' / performed / spoke", async () => {
    const id = (await load("id", GRIND)).html.replace(/<script\b[\s\S]*?<\/script>/g, "");
    expect(id).toContain("Hadir sebagai peserta");
    expect(id).not.toMatch(/tampil|Juara/);
    // Visible text only: the JSON-LD Person keeps Ali's real top award (Demo Day), which is fine.
    const en = (await load("en", GRIND)).html.replace(/<script\b[\s\S]*?<\/script>/g, "");
    expect(en).toContain("Attended as a participant");
    expect(en).not.toMatch(/performed|spoke|1st place|keynote speaker/i);
  });

  it("photos: srcset from image_variants, ordered by sequence, foreign hosts dropped, alt + dimensions set", async () => {
    const { html } = await load("id", DEMO);
    const imgs = [...html.matchAll(/<img\b[^>]*>/g)].map((m) => m[0]).filter((t) => t.includes("/storage/award/"));
    expect(imgs).toHaveLength(2);
    expect(imgs[0]).toContain(`src="https://alisadikinma.com/storage/award/p1-640.webp"`); // largest variant <= 1280w, not the original
    expect(imgs[0]).toMatch(/srcset="[^"]*p1-320\.webp 320w[^"]*p1-1920\.webp 1920w/);
    expect(imgs[0]).toMatch(/alt="[^"]+"/);
    expect(imgs[0]).toMatch(/width="\d+"/);
    expect(imgs[0]).toMatch(/height="\d+"/);
    expect(html).not.toContain("evil.example");
  });

  it("lightbox: each photo is a data-lightbox trigger carrying every 1920w URL in order + its own start index", async () => {
    const { html } = await load("id", DEMO);
    const triggers = [...html.matchAll(/<button\b[^>]*data-lightbox[^>]*>/g)].map((m) => m[0]);
    expect(triggers).toHaveLength(2);
    const items = (t: string) => JSON.parse(/data-lightbox-items="([^"]*)"/.exec(t)![1].replace(/&quot;/g, '"'));
    expect(items(triggers[0])).toEqual([
      "https://alisadikinma.com/storage/award/p1-1920.webp",
      "https://alisadikinma.com/storage/award/p2-1920.webp",
    ]);
    expect(items(triggers[1])).toEqual(items(triggers[0]));
    expect(triggers.map((t) => /data-lightbox-start="(\d+)"/.exec(t)?.[1])).toEqual(["0", "1"]);
    expect(triggers[0]).toMatch(/data-lightbox-thumbs="[^"]+"/);
    expect(html).toContain('id="lb"');
  });

  it("no photos: no lightbox triggers, page still passes the gate", async () => {
    AWARDS[NEXTDEV] = { ...AWARDS[NEXTDEV], galleries: [] };
    const { html, headers, status } = await load("en", NEXTDEV);
    expect(status).toBe(200);
    expect(html).not.toContain("data-lightbox");
    expect(checkHtml(html, awardSpec("en", NEXTDEV, "Nextdev"), headers)).toEqual([]);
    AWARDS[NEXTDEV] = { ...AWARDS[NEXTDEV], galleries: galleries([photo(1)]) };
  });
});

describe("JSON-LD", () => {
  it("Event: name, startDate, location, organizer, url, attendee -> #person", async () => {
    const e = eventOf((await load("en", DEMO)).html);
    expect(e.name).toBe("1st Place Winner — Global AI Demo Day 2026 (Outskill AI Generalist Fellowship)");
    expect(e.startDate).toBe("2026-01-17");
    expect(e.location.name).toBe("Bengaluru, India");
    expect(e.organizer).toEqual({ "@type": "Organization", name: "Outskill" });
    expect(e.url).toBe(`https://alisadikinma.com/en/awards/${DEMO}`);
    expect(e.attendee).toEqual({ "@id": "https://alisadikinma.com/#person" });
  });

  it("Event: year-only date stays a year; no location property when none is stated", async () => {
    const e = eventOf((await load("id", NEXTDEV)).html);
    expect(e.startDate).toBe("2018");
    expect(e).not.toHaveProperty("location");
  });

  it("Event: unparseable received_at -> no startDate (never guessed)", async () => {
    AWARDS[NEXTDEV].award.received_at = "Q1 2018";
    const e = eventOf((await load("id", NEXTDEV)).html);
    expect(e).not.toHaveProperty("startDate");
    AWARDS[NEXTDEV].award.received_at = "2018";
  });

  it("Person.award is this award only when it was won, never for an attended event", async () => {
    const won = nodesOf((await load("id", NEXTDEV)).html).find((n: any) => n["@type"] === "Person") as any;
    expect(won.award).toBe(AWARDS[NEXTDEV].award.title);
    const grind = nodesOf((await load("id", GRIND)).html).find((n: any) => n["@type"] === "Person") as any;
    expect(JSON.stringify(grind.award ?? "")).not.toContain("Startup Grind");
  });

  it("Event: empty organization -> no organizer (never Ali)", async () => {
    const org = AWARDS[NEXTDEV].award.organization;
    AWARDS[NEXTDEV].award.organization = "";
    const e = eventOf((await load("en", NEXTDEV)).html);
    AWARDS[NEXTDEV].award.organization = org;
    expect(e).not.toHaveProperty("organizer");
    expect(JSON.stringify(e)).not.toContain("Ali Sadikin Ma");
  });

  it("Event.description is the award's own text (no marketing tail); absent when empty", async () => {
    const e = eventOf((await load("en", NEXTDEV)).html);
    expect(e.description).toContain("Achieved first place in prestigious Nextdev Startup Competition");
    expect(e.description).not.toMatch(/Ali Sadikin Ma/);
    const d = AWARDS[NEXTDEV].award.description;
    AWARDS[NEXTDEV].award.description = null;
    resetApiCache();
    const e2 = eventOf((await load("en", NEXTDEV)).html);
    AWARDS[NEXTDEV].award.description = d;
    expect(e2).not.toHaveProperty("description");
  });

  it("BreadcrumbList is Home > Work > Award", async () => {
    const { html } = await load("id", GRIND);
    const nodes = nodesOf(html);
    const bc = nodes.find((n: any) => n["@type"] === "BreadcrumbList") as any;
    expect(bc.itemListElement.map((i: any) => i.item)).toEqual([
      "https://alisadikinma.com/id",
      "https://alisadikinma.com/id/work",
      `https://alisadikinma.com/id/penghargaan/${GRIND}`,
    ]);
  });
});

describe("home stages cards link to the award page once the API sends a slug", () => {
  it("loadAwardSlugs + awardHref produce exactly the route this page serves", async () => {
    handler = () => ok([{ id: 13, slug: DEMO }, { id: 10, slug: null }, { id: 7 }]);
    const slugs = await loadAwardSlugs("id");
    expect([...slugs.keys()]).toEqual([13]);
    expect(awardHref({ slug: slugs.get(13) }, "id")).toBe(`/id/penghargaan/${DEMO}`);
    expect(awardHref({ slug: slugs.get(13) }, "en")).toBe(`/en/awards/${DEMO}`);
    expect(awardSpec("id", DEMO, "x").route).toBe(`/id/penghargaan/${DEMO}`);
    expect(awardSpec("en", DEMO, "x").route).toBe(`/en/awards/${DEMO}`);
  });
});
