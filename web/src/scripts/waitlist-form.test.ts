// @vitest-environment happy-dom
import { describe, it, expect, vi } from "vitest";
import { validateWaitlist, submitWaitlist, initWaitlistForm } from "./waitlist-form";

const FORM = `
<form data-waitlist data-endpoint="/api/training-waitlist" data-source-page="/id/pelatihan-ai" data-locale="id"
  data-sending="Mengirim…" data-required="Wajib diisi." data-invalid-email="Email tidak valid."
  data-invalid-wa="Format +628123456789." data-wa-required="WhatsApp wajib bila ikut newsletter."
  data-rate="Terlalu banyak percobaan, coba lagi nanti." data-network="Koneksi gagal." data-server="Terjadi kesalahan."
  data-summary-title="Ada yang perlu diperbaiki">
  <div id="wl-errors" data-summary role="alert" tabindex="-1" hidden><h3>x</h3><ul></ul></div>
  <div class="field"><input id="wl-name" name="name"><span class="err" data-err="name"></span></div>
  <div class="field"><input id="wl-company" name="company"></div>
  <div class="field"><input id="wl-email" name="email" type="email"><span class="err" data-err="email"></span></div>
  <div class="field"><input id="wl-wa" name="whatsapp"><span class="err" data-err="whatsapp"></span></div>
  <input type="radio" name="interest" value="inhouse" checked><input type="radio" name="interest" value="workshop">
  <textarea name="note"></textarea>
  <input class="hp" name="website">
  <input type="checkbox" name="newsletter">
  <p data-status role="status"></p>
  <button type="submit">Daftar waitlist</button>
</form>
<div id="wl-done" data-done hidden tabindex="-1"><h3>Anda sudah masuk waitlist</h3></div>`;

const flush = async () => { for (let i = 0; i < 10; i++) await new Promise((r) => setTimeout(r, 0)); };
const res = (status: number, body: unknown = {}) => new Response(JSON.stringify(body), { status, headers: { "content-type": "application/json" } });
const V = { name: "Budi", email: "budi@x.co", whatsapp: "", newsletter: false };

function mount(fetchImpl: (u: string, i: RequestInit) => Promise<Response>, uuid = () => "11111111-1111-4111-8111-111111111111") {
  document.body.innerHTML = FORM;
  const form = document.querySelector("form") as HTMLFormElement;
  initWaitlistForm(form, fetchImpl, uuid);
  const set = (n: string, v: string) => ((form.elements.namedItem(n) as HTMLInputElement).value = v);
  return { form, set, btn: form.querySelector("button") as HTMLButtonElement };
}
const fill = (set: (n: string, v: string) => void) => { set("name", "Budi"); set("email", "budi@x.co"); };
const submit = async (form: HTMLFormElement) => { form.dispatchEvent(new Event("submit", { cancelable: true, bubbles: true })); await flush(); };

describe("validateWaitlist", () => {
  it("accepts name+email only", () => expect(validateWaitlist(V)).toEqual({}));
  it("requires name (min 2) and valid email", () => {
    expect(validateWaitlist({ ...V, name: " ", email: "" })).toEqual({ name: "required", email: "required" });
    expect(validateWaitlist({ ...V, name: "B" })).toEqual({ name: "required" });
    expect(validateWaitlist({ ...V, email: "nope" })).toEqual({ email: "invalid" });
  });
  it("whatsapp optional but E.164 when given", () => {
    expect(validateWaitlist({ ...V, whatsapp: "0812" })).toEqual({ whatsapp: "invalid" });
    expect(validateWaitlist({ ...V, whatsapp: "+628123456789" })).toEqual({});
  });
  it("newsletter checkbox makes whatsapp required", () => {
    expect(validateWaitlist({ ...V, newsletter: true })).toEqual({ whatsapp: "required" });
    expect(validateWaitlist({ ...V, newsletter: true, whatsapp: "+628123456789" })).toEqual({});
  });
});

describe("submitWaitlist", () => {
  const payload = { name: "Budi" };
  it("ok on 201 and 200 (duplicate client_key)", async () => {
    for (const s of [201, 200]) {
      expect(await submitWaitlist(vi.fn().mockResolvedValue(res(s, { success: true })), "/x", payload)).toEqual({ kind: "ok" });
    }
  });
  it("422 with errors object -> fields; with error.message -> invalid", async () => {
    expect(await submitWaitlist(vi.fn().mockResolvedValue(res(422, { errors: { whatsapp_number: ["bad"] } })), "/x", payload))
      .toEqual({ kind: "fields", fields: { whatsapp: "bad" } });
    expect(await submitWaitlist(vi.fn().mockResolvedValue(res(422, { success: false, error: { code: "VALIDATION", message: "The email field must be a valid email address." } })), "/x", payload))
      .toEqual({ kind: "fields", fields: { email: "The email field must be a valid email address." } });
    expect(await submitWaitlist(vi.fn().mockResolvedValue(res(422, { error: { message: "Something odd." } })), "/x", payload))
      .toEqual({ kind: "invalid", message: "Something odd." });
  });
  it("429 -> rate, fetch throw -> network, 500 -> error", async () => {
    expect(await submitWaitlist(vi.fn().mockResolvedValue(res(429)), "/x", payload)).toEqual({ kind: "rate" });
    expect(await submitWaitlist(vi.fn().mockRejectedValue(new Error("x")), "/x", payload)).toEqual({ kind: "network" });
    expect(await submitWaitlist(vi.fn().mockResolvedValue(res(500)), "/x", payload)).toEqual({ kind: "error" });
  });
});

describe("initWaitlistForm", () => {
  it("posts the full payload with source_page, locale and a uuid client_key", async () => {
    const f = vi.fn().mockResolvedValue(res(201, { success: true }));
    const { form, set } = mount(f);
    fill(set); set("company", "PT X"); set("note", "10 orang");
    await submit(form);
    const body = JSON.parse(f.mock.calls[0][1].body);
    expect(f.mock.calls[0][0]).toBe("/api/training-waitlist");
    expect(body).toEqual({
      name: "Budi", company: "PT X", email: "budi@x.co", whatsapp_number: null, interest: "inhouse", note: "10 orang",
      source_page: "/id/pelatihan-ai", locale: "id", newsletter_opt_in: false,
      client_key: "11111111-1111-4111-8111-111111111111", website: "",
    });
  });

  it("generates ONE client_key per page load and reuses it on retry", async () => {
    const uuid = vi.fn().mockReturnValue("22222222-2222-4222-8222-222222222222");
    const f = vi.fn().mockResolvedValueOnce(res(500)).mockResolvedValueOnce(res(201, { success: true }));
    const { form, set } = mount(f, uuid);
    fill(set);
    await submit(form); await submit(form);
    expect(uuid).toHaveBeenCalledTimes(1);
    expect(JSON.parse(f.mock.calls[0][1].body).client_key).toBe(JSON.parse(f.mock.calls[1][1].body).client_key);
  });

  it("blur validates a filled field and clears when fixed", () => {
    const { form, set } = mount(vi.fn());
    const email = form.elements.namedItem("email") as HTMLInputElement;
    set("email", "nope");
    email.dispatchEvent(new Event("blur"));
    expect(form.querySelector('[data-err="email"]')!.textContent).toBe("Email tidak valid.");
    expect(email.getAttribute("aria-invalid")).toBe("true");
    set("email", "a@b.co");
    email.dispatchEvent(new Event("blur"));
    expect(form.querySelector('[data-err="email"]')!.textContent).toBe("");
  });

  it("failed submit shows error summary (links to fields), focuses it, sends nothing", async () => {
    const f = vi.fn();
    const { form } = mount(f);
    await submit(form);
    const summary = document.getElementById("wl-errors") as HTMLElement;
    expect(f).not.toHaveBeenCalled();
    expect(summary.hidden).toBe(false);
    expect(document.activeElement).toBe(summary);
    const links = [...summary.querySelectorAll("li a")].map((a) => a.getAttribute("href"));
    expect(links).toEqual(["#wl-name", "#wl-email"]);
  });

  it("newsletter checked without WhatsApp -> blocked, WhatsApp error", async () => {
    const f = vi.fn();
    const { form, set } = mount(f);
    fill(set);
    (form.elements.namedItem("newsletter") as HTMLInputElement).checked = true;
    await submit(form);
    expect(f).not.toHaveBeenCalled();
    expect(form.querySelector('[data-err="whatsapp"]')!.textContent).toBe("WhatsApp wajib bila ikut newsletter.");
  });

  it("disables button with 'Mengirim…' while pending; double click -> one request", async () => {
    let resolve!: (r: Response) => void;
    const f = vi.fn().mockReturnValue(new Promise<Response>((r) => (resolve = r)));
    const { form, set, btn } = mount(f);
    fill(set);
    form.dispatchEvent(new Event("submit", { cancelable: true }));
    form.dispatchEvent(new Event("submit", { cancelable: true }));
    await flush();
    expect(f).toHaveBeenCalledTimes(1);
    expect(btn.disabled).toBe(true);
    expect(btn.textContent).toBe("Mengirim…");
    resolve(res(201, { success: true }));
    await flush();
  });

  it("success hides form and shows the done state focused", async () => {
    const { form, set } = mount(vi.fn().mockResolvedValue(res(201, { success: true })));
    fill(set);
    await submit(form);
    const done = document.getElementById("wl-done") as HTMLElement;
    expect(form.hidden).toBe(true);
    expect(done.hidden).toBe(false);
    expect(document.activeElement).toBe(done);
  });

  it("422 puts field errors inline and in the summary; button re-enabled", async () => {
    const { form, set, btn } = mount(vi.fn().mockResolvedValue(res(422, { errors: { email: ["Email sudah salah."] } })));
    fill(set);
    await submit(form);
    expect(form.querySelector('[data-err="email"]')!.textContent).toBe("Email sudah salah.");
    expect(document.getElementById("wl-errors")!.hidden).toBe(false);
    expect(btn.disabled).toBe(false);
    expect(btn.textContent).toBe("Daftar waitlist");
  });

  it("429 shows throttle message; client_key kept", async () => {
    const { form, set } = mount(vi.fn().mockResolvedValue(res(429)));
    fill(set);
    await submit(form);
    expect(form.querySelector("[data-status]")!.textContent).toBe("Terlalu banyak percobaan, coba lagi nanti.");
    expect(form.hidden).toBe(false);
  });
});
