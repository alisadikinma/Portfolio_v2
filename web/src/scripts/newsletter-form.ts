export interface NewsletterValues { name: string; email: string; whatsapp: string }
export type FieldName = keyof NewsletterValues;
export type FieldCode = "required" | "invalid";

export type SubmitResult =
  | { kind: "ok" }
  | { kind: "fields"; fields: Partial<Record<FieldName, string>> }
  | { kind: "invalid"; message: string }
  | { kind: "duplicate" }
  | { kind: "rate" }
  | { kind: "network" }
  | { kind: "error" };

type Fetch = (url: string, init: RequestInit) => Promise<Response>;

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const WA_RE = /^\+[1-9]\d{6,14}$/;

export function validateNewsletter(v: NewsletterValues): Partial<Record<FieldName, FieldCode>> {
  const out: Partial<Record<FieldName, FieldCode>> = {};
  if (v.name.trim() === "") out.name = "required";
  if (v.email.trim() === "") out.email = "required";
  else if (!EMAIL_RE.test(v.email.trim())) out.email = "invalid";
  if (v.whatsapp.trim() === "") out.whatsapp = "required";
  else if (!WA_RE.test(v.whatsapp.trim())) out.whatsapp = "invalid";
  return out;
}

const API_FIELD: Record<string, FieldName> = { name: "name", email: "email", whatsapp_number: "whatsapp" };

async function readJson(res: Response): Promise<unknown> {
  try {
    return await res.json();
  } catch {
    return null;
  }
}

export async function submitNewsletter(
  doFetch: Fetch,
  endpoint: string,
  v: NewsletterValues,
  source: string,
): Promise<SubmitResult> {
  let res: Response;
  try {
    res = await doFetch(endpoint, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ name: v.name.trim(), email: v.email.trim(), whatsapp_number: v.whatsapp.trim(), source }),
    });
  } catch {
    return { kind: "network" };
  }
  if (res.ok) return { kind: "ok" };
  if (res.status === 429) return { kind: "rate" };
  if (res.status === 409) return { kind: "duplicate" };
  if (res.status === 422) {
    const body = (await readJson(res)) as { errors?: Record<string, string[]>; error?: { message?: string } } | null;
    if (body?.errors && typeof body.errors === "object") {
      const fields: Partial<Record<FieldName, string>> = {};
      for (const [k, msgs] of Object.entries(body.errors)) {
        const f = API_FIELD[k];
        if (f && Array.isArray(msgs) && typeof msgs[0] === "string") fields[f] = msgs[0];
      }
      if (Object.keys(fields).length > 0) return { kind: "fields", fields };
    }
    const message = body?.error?.message;
    if (typeof message === "string" && message !== "") return { kind: "invalid", message };
  }
  return { kind: "error" };
}

const FIELDS: FieldName[] = ["name", "email", "whatsapp"];

export function initNewsletterForm(form: HTMLFormElement, doFetch: Fetch = (u, i) => fetch(u, i)): void {
  const d = form.dataset;
  const button = form.querySelector<HTMLButtonElement>("button[type=submit], button:not([type])");
  const status = form.querySelector<HTMLElement>("[data-status]");
  if (!button) return;
  const idleLabel = button.textContent ?? "";
  let inFlight = false;

  const input = (f: FieldName) => form.elements.namedItem(f) as HTMLInputElement | null;
  const setStatus = (t: string) => { if (status) status.textContent = t; };
  const setErr = (f: FieldName, msg: string) => {
    const el = form.querySelector<HTMLElement>(`[data-err="${f}"]`);
    if (el) el.textContent = msg;
    el?.closest(".field")?.classList.toggle("invalid", msg !== "");
    input(f)?.setAttribute("aria-invalid", msg !== "" ? "true" : "false");
  };
  const clearErrs = () => { for (const f of FIELDS) setErr(f, ""); };
  const codeMsg = (f: FieldName, c: FieldCode) =>
    c === "required" ? (d.required ?? "") : f === "email" ? (d.invalidEmail ?? "") : (d.invalidWa ?? "");

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    if (inFlight) return;
    clearErrs();
    setStatus("");
    const values: NewsletterValues = {
      name: input("name")?.value ?? "",
      email: input("email")?.value ?? "",
      whatsapp: input("whatsapp")?.value ?? "",
    };
    const bad = validateNewsletter(values);
    const badKeys = FIELDS.filter((f) => bad[f]);
    if (badKeys.length > 0) {
      for (const f of badKeys) setErr(f, codeMsg(f, bad[f]!));
      input(badKeys[0])?.focus();
      return;
    }
    inFlight = true;
    button.disabled = true;
    button.textContent = d.sending ?? idleLabel;
    form.setAttribute("aria-busy", "true");
    let r: SubmitResult;
    try {
      r = await submitNewsletter(doFetch, d.endpoint ?? "/api/newsletter/subscribe", values, d.source ?? "homepage_join");
    } catch {
      r = { kind: "error" };
    }
    inFlight = false;
    button.disabled = false;
    button.textContent = idleLabel;
    form.removeAttribute("aria-busy");
    switch (r.kind) {
      case "ok": setStatus(d.ok ?? ""); form.reset(); break;
      case "fields": for (const f of FIELDS) if (r.fields[f]) setErr(f, r.fields[f]!); break;
      case "invalid": setStatus(r.message); break;
      case "duplicate": setStatus(d.dup ?? ""); break;
      case "rate": setStatus(d.rate ?? ""); break;
      case "network": setStatus(d.network ?? ""); break;
      default: setStatus(d.server ?? "");
    }
  });
}

export function initAllNewsletterForms(doc: Document = document): void {
  doc.querySelectorAll<HTMLFormElement>("form[data-newsletter]").forEach((f) => initNewsletterForm(f));
}
