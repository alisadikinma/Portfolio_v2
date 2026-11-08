// @vitest-environment happy-dom
import { describe, it, expect, vi, beforeEach } from "vitest";
import { validateContact, validateField, submitContact, initContactForm } from "./contact-form";

const FORM = `
<form data-contact data-endpoint="/api/contact"
  data-sending="Mengirim…" data-rate="Terlalu banyak pesan. Coba lagi nanti (sekitar 15 menit)."
  data-network="Gagal terhubung, coba lagi." data-server="Terjadi kesalahan, coba lagi." data-fast="Tunggu sebentar lalu kirim lagi."
  data-required="Wajib diisi." data-invalid-email="Email tidak valid." data-invalid-wa="Nomor tidak valid."
  data-short-name="Nama minimal 2 huruf." data-short-subject="Subjek minimal 3 huruf." data-short-message="Pesan minimal 10 huruf."
  data-summary-title="Periksa isian berikut">
  <div data-summary tabindex="-1" role="alert"></div>
  <div data-fields>
    <div class="field"><label for="c-name">Nama</label><input id="c-name" name="name"><span class="err" data-err="name"></span></div>
    <div class="field"><label for="c-email">Email</label><input id="c-email" name="email" type="email"><span class="err" data-err="email"></span></div>
    <div class="field"><label for="c-wa">WhatsApp</label><input id="c-wa" name="whatsapp"><span class="err" data-err="whatsapp"></span></div>
    <div class="field"><label for="c-subject">Subjek</label><input id="c-subject" name="subject"><span class="err" data-err="subject"></span></div>
    <div class="field"><label for="c-message">Pesan</label><textarea id="c-message" name="message"></textarea><span class="err" data-err="message"></span></div>
    <input class="hp" name="website" tabindex="-1" autocomplete="off">
    <p data-status role="status"></p>
    <button type="submit">Kirim</button>
  </div>
  <div data-done tabindex="-1" style="display:none"><h3>Terima kasih</h3></div>
</form>`;

const flush = async () => { for (let i = 0; i < 10; i++) await new Promise((r) => setTimeout(r, 0)); };
const res = (status: number, body: unknown = {}) => new Response(JSON.stringify(body), { status, headers: { "content-type": "application/json" } });
const V = { name: "Budi", email: "budi@x.co", whatsapp: "+62 812-3456-7890", subject: "Tanya workshop", message: "Saya ingin workshop AI untuk tim kami." };

describe("validateContact", () => {
  it("accepts valid input", () => expect(validateContact(V)).toEqual({}));
  it("requires every field", () => {
    expect(validateContact({ name: " ", email: "", whatsapp: "", subject: "", message: "" })).toEqual({
      name: "required", email: "required", whatsapp: "required", subject: "required", message: "required",
    });
  });
  it("applies the server minimums (name 2, subject 3, message 10) and message max 5000", () => {
    expect(validateContact({ ...V, name: "A", subject: "ab", message: "pendek" })).toEqual({ name: "short", subject: "short", message: "short" });
    expect(validateContact({ ...V, message: "x".repeat(5001) })).toEqual({ message: "long" });
    expect(validateContact({ ...V, message: "x".repeat(5000) })).toEqual({});
  });
  it("email and whatsapp format (digits/space/+()- ; 8-15 digits)", () => {
    expect(validateContact({ ...V, email: "nope" })).toEqual({ email: "invalid" });
    for (const w of ["abc12345", "1234567", "1234567890123456", "+62 812 abc"])
      expect(validateContact({ ...V, whatsapp: w }), w).toEqual({ whatsapp: "invalid" });
    for (const w of ["08123456789", "+628123456789", "(021) 555-1234"])
      expect(validateContact({ ...V, whatsapp: w }), w).toEqual({});
  });
  it("validateField checks a single field", () => {
    expect(validateField("email", "x")).toBe("invalid");
    expect(validateField("email", "a@b.co")).toBeUndefined();
  });
});

describe("submitContact", () => {
  it("posts JSON with the exact fields ContactController validates", async () => {
    const f = vi.fn().mockResolvedValue(res(201, { message: "ok" }));
    expect(await submitContact(f, "/api/contact", V, 1700000000)).toEqual({ kind: "ok" });
    const [url, init] = f.mock.calls[0];
    expect(url).toBe("/api/contact");
    expect(init.method).toBe("POST");
    expect(JSON.parse(init.body)).toEqual({
      name: "Budi", email: "budi@x.co", whatsapp_number: "+62 812-3456-7890", subject: "Tanya workshop",
      message: "Saya ingin workshop AI untuk tim kami.", website: "", form_timestamp: 1700000000,
    });
  });
  it("maps Laravel 422 errors to fields (whatsapp_number -> whatsapp)", async () => {
    const f = vi.fn().mockResolvedValue(res(422, { message: "x", errors: { whatsapp_number: ["WA salah"], email: ["Email salah"] } }));
    expect(await submitContact(f, "/x", V, 1)).toEqual({ kind: "fields", fields: { whatsapp: "WA salah", email: "Email salah" } });
  });
  it("form_timestamp-only 422 is the 'too fast' result", async () => {
    const f = vi.fn().mockResolvedValue(res(422, { errors: { form_timestamp: ["Please take a moment"] } }));
    expect((await submitContact(f, "/x", V, 1)).kind).toBe("fast");
  });
  it("maps 429, 5xx, unreadable 422 and network errors", async () => {
    expect((await submitContact(vi.fn().mockResolvedValue(res(429)), "/x", V, 1)).kind).toBe("rate");
    expect((await submitContact(vi.fn().mockResolvedValue(res(500, { message: "Failed" })), "/x", V, 1)).kind).toBe("error");
    expect((await submitContact(vi.fn().mockResolvedValue(new Response("<html>", { status: 422 })), "/x", V, 1)).kind).toBe("error");
    expect((await submitContact(vi.fn().mockRejectedValue(new TypeError("x")), "/x", V, 1)).kind).toBe("network");
  });
});

describe("initContactForm", () => {
  let form: HTMLFormElement;
  const el = (n: string) => form.elements.namedItem(n) as HTMLInputElement;
  const fill = (v = V) => { for (const k of Object.keys(v) as (keyof typeof v)[]) el(k).value = v[k]; };
  const submit = () => form.dispatchEvent(new Event("submit", { cancelable: true, bubbles: true }));
  const btn = () => form.querySelector("button")!;
  const status = () => form.querySelector("[data-status]")!.textContent;
  const summary = () => form.querySelector<HTMLElement>("[data-summary]")!;
  const err = (f: string) => form.querySelector(`[data-err="${f}"]`)!.textContent;
  beforeEach(() => { document.body.innerHTML = FORM; form = document.querySelector("form")!; });

  it("validates a field on blur and clears the error once fixed", () => {
    initContactForm(form, vi.fn() as never);
    el("email").value = "nope";
    el("email").dispatchEvent(new Event("focusout", { bubbles: true }));
    expect(err("email")).toBe("Email tidak valid.");
    expect(el("email").getAttribute("aria-invalid")).toBe("true");
    expect(el("email").closest(".field")!.classList.contains("invalid")).toBe(true);
    el("email").value = "a@b.co";
    el("email").dispatchEvent(new Event("focusout", { bubbles: true }));
    expect(err("email")).toBe("");
    expect(el("email").getAttribute("aria-invalid")).toBe("false");
  });
  it("failed submit sends nothing, lists each error with a link to its field and focuses the summary", async () => {
    const f = vi.fn();
    initContactForm(form, f as never);
    el("name").value = "A"; el("email").value = "x";
    submit(); await flush();
    expect(f).not.toHaveBeenCalled();
    expect(summary().style.display).toBe("block");
    expect(summary().textContent).toContain("Periksa isian berikut");
    const links = [...summary().querySelectorAll("a")];
    expect(links.map((a) => a.getAttribute("href"))).toEqual(["#c-name", "#c-email", "#c-wa", "#c-subject", "#c-message"]);
    expect(links[0].textContent).toBe("Nama: Nama minimal 2 huruf.");
    expect(links[1].textContent).toBe("Email: Email tidak valid.");
    expect(document.activeElement).toBe(summary());
    expect(err("name")).toBe("Nama minimal 2 huruf.");
  });
  it("disables the button with 'Mengirim…' while pending, then shows the success state", async () => {
    let resolve!: (r: Response) => void;
    const f = vi.fn().mockReturnValue(new Promise<Response>((r) => { resolve = r; }));
    initContactForm(form, f as never);
    fill(); submit(); await flush();
    expect(btn().disabled).toBe(true);
    expect(btn().textContent).toBe("Mengirim…");
    expect(form.getAttribute("aria-busy")).toBe("true");
    resolve(res(201, { message: "ok" })); await flush();
    expect(form.querySelector<HTMLElement>("[data-done]")!.style.display).toBe("block");
    expect(form.querySelector<HTMLElement>("[data-fields]")!.hidden).toBe(true);
    expect(document.activeElement).toBe(form.querySelector("[data-done]"));
  });
  it("double click sends exactly one request", async () => {
    const f = vi.fn().mockReturnValue(new Promise<Response>(() => {}));
    initContactForm(form, f as never);
    fill(); submit(); submit(); submit(); await flush();
    expect(f).toHaveBeenCalledTimes(1);
  });
  it("sends a form_timestamp at least 3s old once the user has waited", async () => {
    const f = vi.fn().mockResolvedValue(res(201));
    const now = vi.fn().mockReturnValueOnce(1_700_000_000_000).mockReturnValue(1_700_000_010_000);
    initContactForm(form, f as never, now);
    fill(); submit(); await flush();
    expect(JSON.parse(f.mock.calls[0][1].body).form_timestamp).toBe(1_700_000_000);
  });
  it("prefers the server-rendered data-ts over the visitor's clock (clock skew)", async () => {
    form.dataset.ts = "1699999000";
    const f = vi.fn().mockResolvedValue(res(201));
    initContactForm(form, f as never, () => 1_800_000_000_000);
    fill(); submit(); await flush();
    expect(JSON.parse(f.mock.calls[0][1].body).form_timestamp).toBe(1_699_999_000);
  });
  it("422 puts server messages under the fields and in the summary", async () => {
    initContactForm(form, vi.fn().mockResolvedValue(res(422, { errors: { whatsapp_number: ["WA salah"] } })) as never);
    fill(); submit(); await flush();
    expect(err("whatsapp")).toBe("WA salah");
    expect(summary().querySelector("a")!.getAttribute("href")).toBe("#c-wa");
    expect(document.activeElement).toBe(summary());
    expect(btn().disabled).toBe(false);
  });
  it("429 shows the throttle message and re-enables the button", async () => {
    initContactForm(form, vi.fn().mockResolvedValue(res(429)) as never);
    fill(); submit(); await flush();
    expect(status()).toBe("Terlalu banyak pesan. Coba lagi nanti (sekitar 15 menit).");
    expect(btn().disabled).toBe(false);
    expect(btn().textContent).toBe("Kirim");
  });
  it("network error keeps input and allows resubmit", async () => {
    const f = vi.fn().mockRejectedValueOnce(new TypeError("x")).mockResolvedValueOnce(res(201));
    initContactForm(form, f as never);
    fill(); submit(); await flush();
    expect(status()).toBe("Gagal terhubung, coba lagi.");
    expect(el("message").value).toBe(V.message);
    submit(); await flush();
    expect(f).toHaveBeenCalledTimes(2);
    expect(form.querySelector<HTMLElement>("[data-done]")!.style.display).toBe("block");
  });
  it("500 and 'too fast' show their own messages", async () => {
    initContactForm(form, vi.fn().mockResolvedValueOnce(res(500)).mockResolvedValueOnce(res(422, { errors: { form_timestamp: ["x"] } })) as never);
    fill(); submit(); await flush();
    expect(status()).toBe("Terjadi kesalahan, coba lagi.");
    submit(); await flush();
    expect(status()).toBe("Tunggu sebentar lalu kirim lagi.");
  });
});
