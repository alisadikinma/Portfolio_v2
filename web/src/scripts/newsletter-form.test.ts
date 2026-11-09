// @vitest-environment happy-dom
import { describe, it, expect, vi, beforeEach } from "vitest";
import { validateNewsletter, submitNewsletter, initNewsletterForm } from "./newsletter-form";

const FORM = `
<form data-newsletter data-endpoint="/api/newsletter/subscribe" data-source="homepage_join"
  data-sending="Mengirim…" data-ok="Terima kasih, cek email Anda." data-rate="Terlalu banyak percobaan, coba lagi nanti."
  data-network="Gagal terhubung, coba lagi." data-dup="Email atau nomor ini sudah terdaftar." data-server="Terjadi kesalahan, coba lagi."
  data-required="Wajib diisi." data-invalid-email="Email tidak valid." data-invalid-wa="Format +628123456789.">
  <div class="field"><input name="name"><span class="err" data-err="name"></span></div>
  <div class="field"><input name="email" type="email"><span class="err" data-err="email"></span></div>
  <div class="field"><input name="whatsapp"><span class="err" data-err="whatsapp"></span></div>
  <p data-status role="status"></p>
  <button type="submit">Langganan</button>
</form>`;

const flush = async () => { for (let i = 0; i < 10; i++) await new Promise((r) => setTimeout(r, 0)); };
const res = (status: number, body: unknown = {}) => new Response(JSON.stringify(body), { status, headers: { "content-type": "application/json" } });
const V = { name: "Budi", email: "budi@x.co", whatsapp: "+628123456789" };

describe("validateNewsletter", () => {
  it("accepts valid input", () => {
    expect(validateNewsletter(V)).toEqual({});
  });
  it("requires name, email, whatsapp", () => {
    expect(validateNewsletter({ name: " ", email: "", whatsapp: "" })).toEqual({ name: "required", email: "required", whatsapp: "required" });
  });
  it("rejects bad email and non-E.164 whatsapp", () => {
    expect(validateNewsletter({ ...V, email: "nope" })).toEqual({ email: "invalid" });
    for (const w of ["08123456789", "+0123456789", "+12345", "+1234567890123456", "+62 812"]) {
      expect(validateNewsletter({ ...V, whatsapp: w }), w).toEqual({ whatsapp: "invalid" });
    }
    expect(validateNewsletter({ ...V, whatsapp: "+1234567" })).toEqual({});
  });
});

describe("submitNewsletter", () => {
  it("posts JSON with whatsapp_number and source", async () => {
    const f = vi.fn().mockResolvedValue(res(201, { success: true }));
    expect(await submitNewsletter(f, "/api/newsletter/subscribe", V, "homepage_join")).toEqual({ kind: "ok" });
    const [url, init] = f.mock.calls[0];
    expect(url).toBe("/api/newsletter/subscribe");
    expect(init.method).toBe("POST");
    expect(JSON.parse(init.body)).toEqual({ name: "Budi", email: "budi@x.co", whatsapp_number: "+628123456789", source: "homepage_join" });
  });
  it("maps Laravel 422 errors to fields", async () => {
    const f = vi.fn().mockResolvedValue(res(422, { errors: { whatsapp_number: ["bad"], email: ["bad2"] } }));
    expect(await submitNewsletter(f, "/x", V, "s")).toEqual({ kind: "fields", fields: { whatsapp: "bad", email: "bad2" } });
  });
  it("maps project 422 shape to a form message", async () => {
    const f = vi.fn().mockResolvedValue(res(422, { success: false, error: { code: "VALIDATION", message: "The email must be valid." } }));
    expect(await submitNewsletter(f, "/x", V, "s")).toEqual({ kind: "invalid", message: "The email must be valid." });
  });
  it("maps 409, 429, 5xx and network errors", async () => {
    expect((await submitNewsletter(vi.fn().mockResolvedValue(res(409, { error: { code: "DUPLICATE" } })), "/x", V, "s")).kind).toBe("duplicate");
    expect((await submitNewsletter(vi.fn().mockResolvedValue(res(429)), "/x", V, "s")).kind).toBe("rate");
    expect((await submitNewsletter(vi.fn().mockResolvedValue(res(500)), "/x", V, "s")).kind).toBe("error");
    expect((await submitNewsletter(vi.fn().mockRejectedValue(new TypeError("fail")), "/x", V, "s")).kind).toBe("network");
  });
  it("422 with unreadable body is a generic error, not a crash", async () => {
    const f = vi.fn().mockResolvedValue(new Response("<html>", { status: 422 }));
    expect((await submitNewsletter(f, "/x", V, "s")).kind).toBe("error");
  });
});

describe("initNewsletterForm", () => {
  let form: HTMLFormElement;
  const fill = (v = V) => {
    (form.elements.namedItem("name") as HTMLInputElement).value = v.name;
    (form.elements.namedItem("email") as HTMLInputElement).value = v.email;
    (form.elements.namedItem("whatsapp") as HTMLInputElement).value = v.whatsapp;
  };
  const submit = () => form.dispatchEvent(new Event("submit", { cancelable: true, bubbles: true }));
  const btn = () => form.querySelector("button")!;
  const status = () => form.querySelector("[data-status]")!.textContent;
  beforeEach(() => { document.body.innerHTML = FORM; form = document.querySelector("form")!; });

  it("shows field errors and sends nothing when invalid", async () => {
    const f = vi.fn();
    initNewsletterForm(form, f as never);
    submit(); await flush();
    expect(f).not.toHaveBeenCalled();
    expect(form.querySelector('[data-err="name"]')!.textContent).toBe("Wajib diisi.");
    expect(form.querySelector('[data-err="name"]')!.closest(".field")!.classList.contains("invalid")).toBe(true);
  });
  it("disables the button with 'Mengirim…' while pending, then confirms", async () => {
    let resolve!: (r: Response) => void;
    const f = vi.fn().mockReturnValue(new Promise<Response>((r) => { resolve = r; }));
    initNewsletterForm(form, f as never);
    fill(); submit(); await flush();
    expect(btn().disabled).toBe(true);
    expect(btn().textContent).toBe("Mengirim…");
    resolve(res(201, { success: true })); await flush();
    expect(status()).toBe("Terima kasih, cek email Anda.");
    expect(btn().textContent).toBe("Langganan");
  });
  it("double submit sends exactly one request", async () => {
    const f = vi.fn().mockReturnValue(new Promise<Response>(() => {}));
    initNewsletterForm(form, f as never);
    fill(); submit(); submit(); submit(); await flush();
    expect(f).toHaveBeenCalledTimes(1);
  });
  it("429 shows rate message and re-enables button", async () => {
    initNewsletterForm(form, vi.fn().mockResolvedValue(res(429)) as never);
    fill(); submit(); await flush();
    expect(status()).toBe("Terlalu banyak percobaan, coba lagi nanti.");
    expect(btn().disabled).toBe(false);
  });
  it("network error shows retry message, keeps input, allows resubmit", async () => {
    const f = vi.fn().mockRejectedValueOnce(new TypeError("x")).mockResolvedValueOnce(res(201));
    initNewsletterForm(form, f as never);
    fill(); submit(); await flush();
    expect(status()).toBe("Gagal terhubung, coba lagi.");
    expect((form.elements.namedItem("email") as HTMLInputElement).value).toBe("budi@x.co");
    submit(); await flush();
    expect(f).toHaveBeenCalledTimes(2);
    expect(status()).toBe("Terima kasih, cek email Anda.");
  });
  it("server field errors are shown under the field", async () => {
    initNewsletterForm(form, vi.fn().mockResolvedValue(res(422, { errors: { whatsapp_number: ["WA salah"] } })) as never);
    fill(); submit(); await flush();
    expect(form.querySelector('[data-err="whatsapp"]')!.textContent).toBe("WA salah");
  });
});
