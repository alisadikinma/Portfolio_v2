// PF-2 5E e2e. Runs against the built Astro server (reads the prod API read-only).
// Browser: ONE headed Chrome started by the operator with --remote-debugging-port; every spec
// attaches over CDP and reuses that window's context + page. We never create or close a context.
// Every form POST is intercepted with page.route and answered with the real controller's shape.
import { test as base, expect, chromium, type Page, type Route } from "@playwright/test";
import { BASE_URL, CDP_URL } from "../playwright.config";

const test = base.extend<object, { cdpPage: Page }>({
  cdpPage: [
    async ({}, use) => {
      const browser = await chromium.connectOverCDP(CDP_URL);
      const context = browser.contexts()[0];
      const page = context.pages()[0];
      if (!page) throw new Error("No open page in the shared Chrome window");
      await use(page);
      // Deliberately no close(): the window outlives the run.
    },
    { scope: "worker" },
  ],
});
const it = test.extend<{ page: Page }>({
  page: async ({ cdpPage }, use) => {
    // Structural prod-write guard. Playwright runs the most recently registered matching route first,
    // so per-test mockPost() routes (registered later) win; anything unmocked that writes is aborted.
    await cdpPage.route("**/api/**", (r) => (r.request().method() === "GET" ? r.continue() : r.abort()));
    // Never send analytics beacons from tests.
    await cdpPage.route("**/*googletagmanager.com/**", (r) => r.abort());
    await cdpPage.route("**/*google-analytics.com/**", (r) => r.abort());
    // Deterministic desktop layout (the shared window can be < 960px, which hides the nav).
    await cdpPage.setViewportSize({ width: 1280, height: 800 });
    await use(cdpPage);
    // Clear the CDP device-metrics override so the shared window returns to its real size.
    const cdp = await cdpPage.context().newCDPSession(cdpPage);
    await cdp.send("Emulation.clearDeviceMetricsOverride").catch(() => {});
    await cdp.detach().catch(() => {});
    await cdpPage.unrouteAll({ behavior: "ignoreErrors" });
  },
});

const json = (route: Route, status: number, body: unknown) =>
  route.fulfill({ status, contentType: "application/json", body: JSON.stringify(body) });

interface Captured { body: Record<string, unknown>; method: string }
async function mockPost(page: Page, pattern: string, status: number, body: unknown): Promise<Captured[]> {
  const seen: Captured[] = [];
  await page.route(pattern, (route) => {
    const req = route.request();
    seen.push({ method: req.method(), body: req.postDataJSON() as Record<string, unknown> });
    return json(route, status, body);
  });
  return seen;
}

it.describe("public site", () => {
  it("safety guards: unmocked writes and analytics beacons never leave the browser", async ({ page }) => {
    await page.goto(`${BASE_URL}/id`, { waitUntil: "load" });
    const r = await page.evaluate(async () => {
      const post = await fetch("/api/__guard_probe", { method: "POST", body: "{}" }).then(() => "sent", () => "blocked");
      const ga = await fetch("https://www.googletagmanager.com/gtag/js?id=G-GUARD", { mode: "no-cors" }).then(() => "sent", () => "blocked");
      const ga2 = await fetch("https://www.google-analytics.com/g/collect", { method: "POST", mode: "no-cors" }).then(() => "sent", () => "blocked");
      return { post, ga, ga2 };
    });
    expect(r).toEqual({ post: "blocked", ga: "blocked", ga2: "blocked" });
  });

  it("home hero fits the viewport at several sizes", async ({ page }) => {
    // 390x844 is a narrow-desktop layout check via a CDP size override, NOT mobile emulation (no touch/UA/DPR).
    {
      for (const vp of [{ width: 1440, height: 900 }, { width: 1280, height: 720 }, { width: 1024, height: 768 }, { width: 390, height: 844 }]) {
        await page.setViewportSize(vp);
        await page.goto(`${BASE_URL}/id`, { waitUntil: "load" });
        await page.evaluate(() => window.scrollTo(0, 0));
        const m = await page.evaluate(() => {
          const r = (s: string) => document.querySelector(s)!.getBoundingClientRect();
          const ctas = Array.from(document.querySelectorAll(".hx .hero-ctas .btn")).map((e) => e.getBoundingClientRect());
          return {
            vh: window.innerHeight, vw: window.innerWidth,
            hero: r(".hx"), hud: r(".hx-hud"),
            ctaBottom: Math.max(...ctas.map((c) => c.bottom)),
            ctaRight: Math.max(...ctas.map((c) => c.right)), ctaLeft: Math.min(...ctas.map((c) => c.left)),
            ctaCount: ctas.length,
          };
        });
        const tag = `${vp.width}x${vp.height}`;
        expect(m.ctaCount, tag).toBeGreaterThan(0);
        expect(m.ctaBottom, `${tag} CTA bottom <= HUD top`).toBeLessThanOrEqual(m.hud.top + 1);
        expect(m.hud.bottom, `${tag} HUD bottom <= viewport`).toBeLessThanOrEqual(m.vh + 1);
        expect(m.ctaLeft, `${tag} CTA left`).toBeGreaterThanOrEqual(0);
        expect(m.ctaRight, `${tag} CTA right`).toBeLessThanOrEqual(m.vw + 1);
      }
    }
  });

  it("home scrollspy highlights the section in view", async ({ page }) => {
    await page.goto(`${BASE_URL}/id`, { waitUntil: "load" });
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.waitForFunction(() => window.scrollY === 0);
    await expect(page.locator(".nav a[data-spy].is-active")).toHaveCount(0); // hero = no highlight
    for (const id of ["layanan", "tulisan"]) {
      await page.locator(`#${id}`).scrollIntoViewIfNeeded();
      await page.evaluate((sid) => {
        const el = document.getElementById(sid)!;
        window.scrollTo(0, el.getBoundingClientRect().top + window.scrollY - window.innerHeight * 0.3);
      }, id);
      const link = page.locator(`.nav a[data-spy][href$="#${id}"]`);
      await expect(link).toHaveClass(/is-active/);
      await expect(link).toHaveAttribute("aria-current", "location");
      await expect(page.locator(".nav a[data-spy].is-active")).toHaveCount(1);
    }
  });

  it("lightbox opens from a trigger and closes (button, Escape) returning focus", async ({ page }) => {
    await page.goto(`${BASE_URL}/id`, { waitUntil: "load" });
    const trigger = page.locator("[data-lightbox][data-lightbox-items]:not([data-lightbox-items='[]'])").first();
    await expect(trigger, "home needs >=1 gallery item: the API under test (API_INTERNAL_URL) must return gallery data and the gallery section must render").toHaveCount(1);
    await trigger.scrollIntoViewIfNeeded();
    const dialog = page.locator("dialog#lb");
    await expect(dialog).not.toHaveAttribute("open", "");
    await trigger.click();
    await expect(dialog).toHaveAttribute("open", "");
    await expect(dialog.locator("figure img")).toHaveAttribute("src", /.+/);
    await expect(dialog.locator("[data-lb-count]")).toContainText(/^1 \/ \d+$/);
    await dialog.locator('[data-lb="close"]').click();
    await expect(dialog).not.toHaveAttribute("open", "");
    await expect(trigger).toBeFocused();
    await trigger.click();
    await expect(dialog).toHaveAttribute("open", "");
    await page.keyboard.press("Escape");
    await expect(dialog).not.toHaveAttribute("open", "");
  });

  it("training waitlist submit shows the success state", async ({ page }) => {
    const seen = await mockPost(page, "**/api/training-waitlist", 201, { success: true, data: { client_key: "00000000-0000-4000-8000-000000000000" } });
    await page.goto(`${BASE_URL}/id/pelatihan-ai`, { waitUntil: "load" });
    const form = page.locator("form[data-waitlist]");
    await form.locator("#f-name").fill("Budi Santoso");
    await form.locator("#f-company").fill("PT Contoh");
    await form.locator("#f-email").fill("budi@contoh.co.id");
    await form.locator("#f-wa").fill("+6281234567890");
    await form.locator('input[name="interest"][value="workshop"]').check();
    await form.locator("#f-note").fill("20 peserta, divisi operasi");
    await form.locator('button[type="submit"]').click();
    await expect(page.locator("[data-done]")).toBeVisible();
    await expect(form).toBeHidden();
    expect(seen).toHaveLength(1);
    expect(seen[0].method).toBe("POST");
    expect(seen[0].body).toMatchObject({
      name: "Budi Santoso", company: "PT Contoh", email: "budi@contoh.co.id", whatsapp_number: "+6281234567890",
      interest: "workshop", note: "20 peserta, divisi operasi", source_page: "/id/pelatihan-ai", locale: "id",
      newsletter_opt_in: false, website: "",
    });
    expect(String(seen[0].body.client_key)).toMatch(/^[0-9a-f-]{36}$/);
  });

  it("training waitlist blocks an invalid submit client-side (no request)", async ({ page }) => {
    const seen = await mockPost(page, "**/api/training-waitlist", 201, { success: true, data: { client_key: "x" } });
    await page.goto(`${BASE_URL}/id/pelatihan-ai`, { waitUntil: "load" });
    const form = page.locator("form[data-waitlist]");
    await form.locator("#f-email").fill("bukan-email");
    await form.locator('button[type="submit"]').click();
    await expect(form.locator("#f-name")).toHaveAttribute("aria-invalid", "true");
    await expect(form.locator("#f-email")).toHaveAttribute("aria-invalid", "true");
    expect(seen).toHaveLength(0);
  });

  it("newsletter submit posts the lead and confirms", async ({ page }) => {
    const seen = await mockPost(page, "**/api/newsletter/subscribe", 201, { success: true, message: "Successfully subscribed to the newsletter!" });
    await page.goto(`${BASE_URL}/id`, { waitUntil: "load" });
    const form = page.locator("form[data-newsletter]").first();
    await expect(form, "newsletter form missing: the `join-the-build` homepage section must be enabled in the API under test").toHaveCount(1);
    await form.scrollIntoViewIfNeeded();
    await form.locator('[name="name"]').fill("Siti Aminah");
    await form.locator('[name="email"]').fill("siti@contoh.co.id");
    await form.locator('[name="whatsapp"]').fill("+6281298765432");
    await form.locator("button").first().click();
    const okText = await form.getAttribute("data-ok");
    await expect(form.locator("[data-status]")).toHaveText(okText!);
    expect(seen).toHaveLength(1);
    expect(seen[0].body).toEqual({ name: "Siti Aminah", email: "siti@contoh.co.id", whatsapp_number: "+6281298765432", source: "homepage_join" });
    await expect(form.locator('[name="email"]')).toHaveValue("");
  });

  it("newsletter shows the duplicate message on 409", async ({ page }) => {
    const seen = await mockPost(page, "**/api/newsletter/subscribe", 409, { success: false, error: { code: "DUPLICATE", message: "This email or WhatsApp number is already subscribed." } });
    await page.goto(`${BASE_URL}/id`, { waitUntil: "load" });
    const form = page.locator("form[data-newsletter]").first();
    await expect(form, "newsletter form missing: the `join-the-build` homepage section must be enabled in the API under test").toHaveCount(1);
    await form.scrollIntoViewIfNeeded();
    await form.locator('[name="name"]').fill("Siti Aminah");
    await form.locator('[name="email"]').fill("siti@contoh.co.id");
    await form.locator('[name="whatsapp"]').fill("+6281298765432");
    await form.locator("button").first().click();
    await expect(form.locator("[data-status]")).toHaveText((await form.getAttribute("data-dup"))!);
    expect(seen).toHaveLength(1);
    expect(seen[0].body).toEqual({ name: "Siti Aminah", email: "siti@contoh.co.id", whatsapp_number: "+6281298765432", source: "homepage_join" });
  });

  it("contact submit posts the message and shows the done panel", async ({ page }) => {
    const seen = await mockPost(page, "**/api/contact", 201, {
      message: "Thank you for your message! We will get back to you soon.",
      data: { id: 1, name: "Rina Wijaya", email: "rina@contoh.co.id", subject: "Kerja sama pelatihan", message: "Halo, kami ingin workshop AI untuk tim.", is_read: false },
    });
    await page.goto(`${BASE_URL}/id/contact`, { waitUntil: "load" });
    const form = page.locator("form[data-contact]");
    await form.locator('[name="name"]').fill("Rina Wijaya");
    await form.locator('[name="email"]').fill("rina@contoh.co.id");
    await form.locator('[name="whatsapp"]').fill("+62 812-3456-7890");
    await form.locator('[name="subject"]').fill("Kerja sama pelatihan");
    await form.locator('[name="message"]').fill("Halo, kami ingin workshop AI untuk tim.");
    await form.locator("button").last().click();
    await expect(form.locator("[data-done]")).toBeVisible();
    expect(seen).toHaveLength(1);
    expect(seen[0].body).toMatchObject({
      name: "Rina Wijaya", email: "rina@contoh.co.id", whatsapp_number: "+62 812-3456-7890",
      subject: "Kerja sama pelatihan", message: "Halo, kami ingin workshop AI untuk tim.", website: "",
    });
    expect(Number.isInteger(seen[0].body.form_timestamp)).toBe(true);
  });

  it("contact surfaces backend field errors on 422", async ({ page }) => {
    const seen = await mockPost(page, "**/api/contact", 422, { message: "The given data was invalid.", errors: { email: ["The email field must be a valid email address."] } });
    await page.goto(`${BASE_URL}/id/contact`, { waitUntil: "load" });
    const form = page.locator("form[data-contact]");
    await form.locator('[name="name"]').fill("Rina Wijaya");
    await form.locator('[name="email"]').fill("rina@contoh.co.id");
    await form.locator('[name="whatsapp"]').fill("+6281234567890");
    await form.locator('[name="subject"]').fill("Kerja sama pelatihan");
    await form.locator('[name="message"]').fill("Halo, kami ingin workshop AI untuk tim.");
    await form.locator("button").last().click();
    await expect(form.locator('[name="email"]')).toHaveAttribute("aria-invalid", "true");
    await expect(form.locator("[data-summary]")).toBeVisible();
    expect(seen).toHaveLength(1);
    expect(seen[0].body).toMatchObject({
      name: "Rina Wijaya", email: "rina@contoh.co.id", whatsapp_number: "+6281234567890",
      subject: "Kerja sama pelatihan", message: "Halo, kami ingin workshop AI untuk tim.", website: "",
    });
  });

  it("training waitlist surfaces the real 422 shape (error.message only)", async ({ page }) => {
    const seen = await mockPost(page, "**/api/training-waitlist", 422, { success: false, error: { code: "VALIDATION", message: "The email field must be a valid email address." } });
    await page.goto(`${BASE_URL}/id/pelatihan-ai`, { waitUntil: "load" });
    const form = page.locator("form[data-waitlist]");
    await form.locator("#f-name").fill("Budi Santoso");
    await form.locator("#f-email").fill("budi@contoh.co.id"); // passes client check; server rejects
    await form.locator('button[type="submit"]').click();
    await expect(form.locator("#f-email")).toHaveAttribute("aria-invalid", "true");
    await expect(form.locator("[data-summary]")).toBeVisible();
    await expect(form.locator("[data-summary]")).toContainText("The email field must be a valid email address.");
    expect(seen).toHaveLength(1);
    expect(seen[0].body).toMatchObject({ name: "Budi Santoso", email: "budi@contoh.co.id" });
  });

  for (const [from, to] of [["/id", "/en"], ["/id/pelatihan-ai", "/en/ai-training"], ["/id/pembicara-ai", "/en/ai-speaker"], ["/id/contact", "/en/contact"], ["/en/ai-training", "/id/pelatihan-ai"]] as const) {
    it(`language switch ${from} -> ${to} keeps the page`, async ({ page }) => {
      await page.goto(`${BASE_URL}${from}`, { waitUntil: "load" });
      const sw = page.locator("a.lang");
      await expect(sw).toHaveAttribute("href", to);
      await sw.click();
      await page.waitForURL(`${BASE_URL}${to}`);
      await expect(page.locator("html")).toHaveAttribute("lang", to.startsWith("/en") ? "en" : "id");
      await expect(page.locator("h1")).toHaveCount(1);
    });
  }
});
