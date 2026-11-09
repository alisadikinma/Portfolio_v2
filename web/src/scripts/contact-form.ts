// Contact form island. Fields and rules mirror backend StoreContactRequest (POST /api/contact).
export interface ContactValues { name: string; email: string; whatsapp: string; subject: string; message: string }
export type FieldName = keyof ContactValues;
export type FieldCode = "required" | "invalid" | "short" | "long";

export type SubmitResult =
  | { kind: "ok" }
  | { kind: "fields"; fields: Partial<Record<FieldName, string>> }
  | { kind: "fast" }
  | { kind: "rate" }
  | { kind: "network" }
  | { kind: "error" };

type Fetch = (url: string, init: RequestInit) => Promise<Response>;

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const WA_CHARS_RE = /^[\d\s+\-()]+$/;
export const FIELDS: FieldName[] = ["name", "email", "whatsapp", "subject", "message"];
const MIN: Partial<Record<FieldName, number>> = { name: 2, subject: 3, message: 10 };
const MESSAGE_MAX = 5000;

export function validateField(f: FieldName, raw: string): FieldCode | undefined {
  const v = raw.trim();
  if (v === "") return "required";
  if (f === "email") return EMAIL_RE.test(v) ? undefined : "invalid";
  if (f === "whatsapp") {
    const digits = v.replace(/\D/g, "").length;
    return WA_CHARS_RE.test(v) && digits >= 8 && digits <= 15 ? undefined : "invalid";
  }
  if (v.length < (MIN[f] ?? 0)) return "short";
  if (f === "message" && v.length > MESSAGE_MAX) return "long";
  return undefined;
}

export function validateContact(v: ContactValues): Partial<Record<FieldName, FieldCode>> {
  const out: Partial<Record<FieldName, FieldCode>> = {};
  for (const f of FIELDS) {
    const c = validateField(f, v[f]);
    if (c) out[f] = c;
  }
  return out;
}

const API_FIELD: Record<string, FieldName> = {
  name: "name", email: "email", whatsapp_number: "whatsapp", subject: "subject", message: "message",
};

async function readJson(res: Response): Promise<unknown> {
  try {
    return await res.json();
  } catch {
    return null;
  }
}

export async function submitContact(doFetch: Fetch, endpoint: string, v: ContactValues, timestamp: number): Promise<SubmitResult> {
  let res: Response;
  try {
    res = await doFetch(endpoint, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({
        name: v.name.trim(),
        email: v.email.trim(),
        whatsapp_number: v.whatsapp.trim(),
        subject: v.subject.trim(),
        message: v.message.trim(),
        website: "", // honeypot: must stay empty
        form_timestamp: timestamp,
      }),
    });
  } catch {
    return { kind: "network" };
  }
  if (res.ok) return { kind: "ok" };
  if (res.status === 429) return { kind: "rate" };
  if (res.status === 422) {
    const body = (await readJson(res)) as { errors?: Record<string, string[]> } | null;
    if (body?.errors && typeof body.errors === "object") {
      const fields: Partial<Record<FieldName, string>> = {};
      for (const [k, msgs] of Object.entries(body.errors)) {
        const f = API_FIELD[k];
        if (f && Array.isArray(msgs) && typeof msgs[0] === "string") fields[f] = msgs[0];
      }
      if (Object.keys(fields).length > 0) return { kind: "fields", fields };
      if ("form_timestamp" in body.errors) return { kind: "fast" };
    }
  }
  return { kind: "error" };
}

export function initContactForm(form: HTMLFormElement, doFetch: Fetch = (u, i) => fetch(u, i), now: () => number = Date.now): void {
  const d = form.dataset;
  const button = form.querySelector<HTMLButtonElement>("button[type=submit], button:not([type])");
  const status = form.querySelector<HTMLElement>("[data-status]");
  const summary = form.querySelector<HTMLElement>("[data-summary]");
  const fieldsBox = form.querySelector<HTMLElement>("[data-fields]");
  const done = form.querySelector<HTMLElement>("[data-done]");
  if (!button) return;
  const idleLabel = button.textContent ?? "";
  // Backend rejects submits under 3s after this. Prefer the server-rendered timestamp so a
  // visitor's skewed clock cannot trip that check; fall back to the local clock.
  const serverTs = Number(d.ts);
  const loadedAt = Number.isInteger(serverTs) && serverTs > 0 ? serverTs : Math.floor(now() / 1000);
  let inFlight = false;

  const input = (f: FieldName) => form.elements.namedItem(f) as HTMLInputElement | null;
  const setStatus = (t: string) => { if (status) status.textContent = t; };
  const setErr = (f: FieldName, msg: string) => {
    const el = form.querySelector<HTMLElement>(`[data-err="${f}"]`);
    if (el) el.textContent = msg;
    el?.closest(".field")?.classList.toggle("invalid", msg !== "");
    input(f)?.setAttribute("aria-invalid", msg !== "" ? "true" : "false");
  };
  const codeMsg = (f: FieldName, c: FieldCode): string => {
    if (c === "required") return d.required ?? "";
    if (c === "invalid") return (f === "email" ? d.invalidEmail : d.invalidWa) ?? "";
    if (c === "long") return d.longMessage ?? d.shortMessage ?? "";
    return (f === "name" ? d.shortName : f === "subject" ? d.shortSubject : d.shortMessage) ?? "";
  };
  const labelOf = (f: FieldName) => {
    const id = input(f)?.id;
    return (id ? form.querySelector(`label[for="${id}"]`)?.textContent : "")?.trim() ?? f;
  };
  const clearSummary = () => {
    if (!summary) return;
    summary.replaceChildren();
    summary.style.display = "none";
  };
  const showSummary = (errs: Partial<Record<FieldName, string>>) => {
    if (!summary) return;
    summary.replaceChildren();
    const h = document.createElement("h3");
    h.textContent = d.summaryTitle ?? "";
    const ul = document.createElement("ul");
    for (const f of FIELDS) {
      const msg = errs[f];
      if (!msg) continue;
      const a = document.createElement("a");
      a.href = `#${input(f)?.id ?? f}`;
      a.textContent = `${labelOf(f)}: ${msg}`;
      const li = document.createElement("li");
      li.append(a);
      ul.append(li);
    }
    summary.append(h, ul);
    summary.style.display = "block";
    summary.focus();
  };

  form.addEventListener("focusout", (e) => {
    const t = e.target as HTMLInputElement | null;
    const f = FIELDS.find((x) => x === t?.name);
    if (!f) return;
    const c = validateField(f, t!.value);
    setErr(f, c ? codeMsg(f, c) : "");
  });

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    if (inFlight) return;
    for (const f of FIELDS) setErr(f, "");
    clearSummary();
    setStatus("");
    const values = Object.fromEntries(FIELDS.map((f) => [f, input(f)?.value ?? ""])) as unknown as ContactValues;
    const bad = validateContact(values);
    if (Object.keys(bad).length > 0) {
      const msgs: Partial<Record<FieldName, string>> = {};
      for (const f of FIELDS) if (bad[f]) { msgs[f] = codeMsg(f, bad[f]!); setErr(f, msgs[f]!); }
      showSummary(msgs);
      return;
    }
    inFlight = true;
    button.disabled = true;
    button.textContent = d.sending ?? idleLabel;
    form.setAttribute("aria-busy", "true");
    let r: SubmitResult;
    try {
      r = await submitContact(doFetch, d.endpoint ?? "/api/contact", values, loadedAt);
    } catch {
      r = { kind: "error" };
    }
    inFlight = false;
    button.disabled = false;
    button.textContent = idleLabel;
    form.removeAttribute("aria-busy");
    switch (r.kind) {
      case "ok":
        form.reset();
        if (fieldsBox) fieldsBox.hidden = true;
        if (done) { done.style.display = "block"; done.focus(); }
        break;
      case "fields":
        for (const f of FIELDS) if (r.fields[f]) setErr(f, r.fields[f]!);
        showSummary(r.fields);
        break;
      case "fast": setStatus(d.fast ?? ""); break;
      case "rate": setStatus(d.rate ?? ""); break;
      case "network": setStatus(d.network ?? ""); break;
      default: setStatus(d.server ?? "");
    }
  });
}

export function initAllContactForms(doc: Document = document): void {
  doc.querySelectorAll<HTMLFormElement>("form[data-contact]").forEach((f) => initContactForm(f));
}
