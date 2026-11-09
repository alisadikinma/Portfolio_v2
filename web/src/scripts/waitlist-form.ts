export interface WaitlistValues { name: string; email: string; whatsapp: string; newsletter: boolean }
export type WlField = "name" | "email" | "whatsapp";
export type WlCode = "required" | "invalid";

export type WlResult =
  | { kind: "ok" }
  | { kind: "fields"; fields: Partial<Record<WlField, string>> }
  | { kind: "invalid"; message: string }
  | { kind: "rate" }
  | { kind: "network" }
  | { kind: "error" };

type Fetch = (url: string, init: RequestInit) => Promise<Response>;

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const WA_RE = /^\+[1-9]\d{6,14}$/;
const FIELDS: WlField[] = ["name", "email", "whatsapp"];

export function validateWaitlist(v: WaitlistValues): Partial<Record<WlField, WlCode>> {
  const out: Partial<Record<WlField, WlCode>> = {};
  if (v.name.trim().length < 2) out.name = "required";
  if (v.email.trim() === "") out.email = "required";
  else if (!EMAIL_RE.test(v.email.trim())) out.email = "invalid";
  const wa = v.whatsapp.trim();
  if (wa === "") {
    if (v.newsletter) out.whatsapp = "required";
  } else if (!WA_RE.test(wa)) out.whatsapp = "invalid";
  return out;
}

// Real 422 body: { success:false, error:{ code:"VALIDATION", message:<first error> } } (no per-field map).
// A Laravel-style `errors` map is also accepted.
const API_FIELD: Record<string, WlField> = { name: "name", email: "email", whatsapp_number: "whatsapp" };
const MSG_FIELD: Array<[RegExp, WlField]> = [[/whatsapp/i, "whatsapp"], [/email/i, "email"], [/\bname\b/i, "name"]];

async function readJson(res: Response): Promise<unknown> {
  try { return await res.json(); } catch { return null; }
}

export async function submitWaitlist(doFetch: Fetch, endpoint: string, payload: Record<string, unknown>): Promise<WlResult> {
  let res: Response;
  try {
    res = await doFetch(endpoint, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify(payload),
    });
  } catch {
    return { kind: "network" };
  }
  if (res.ok) return { kind: "ok" }; // 201 new, 200 same client_key already stored
  if (res.status === 429) return { kind: "rate" };
  if (res.status === 422) {
    const body = (await readJson(res)) as { errors?: Record<string, string[]>; error?: { message?: string } } | null;
    const fields: Partial<Record<WlField, string>> = {};
    if (body?.errors && typeof body.errors === "object") {
      for (const [k, msgs] of Object.entries(body.errors)) {
        const f = API_FIELD[k];
        if (f && Array.isArray(msgs) && typeof msgs[0] === "string") fields[f] = msgs[0];
      }
    }
    const message = body?.error?.message;
    if (Object.keys(fields).length === 0 && typeof message === "string" && message !== "") {
      const hit = MSG_FIELD.find(([re]) => re.test(message));
      if (hit) fields[hit[1]] = message;
      else return { kind: "invalid", message };
    }
    if (Object.keys(fields).length > 0) return { kind: "fields", fields };
  }
  return { kind: "error" };
}

export function initWaitlistForm(
  form: HTMLFormElement,
  doFetch: Fetch = (u, i) => fetch(u, i),
  uuid: () => string = () => crypto.randomUUID(),
): void {
  const d = form.dataset;
  const button = form.querySelector<HTMLButtonElement>("button[type=submit]");
  const status = form.querySelector<HTMLElement>("[data-status]");
  const summary = form.querySelector<HTMLElement>("[data-summary]");
  const done = form.ownerDocument.querySelector<HTMLElement>("[data-done]");
  if (!button) return;
  const idleLabel = button.textContent ?? "";
  const clientKey = uuid(); // one per page load; reused on retry so the server dedupes
  let inFlight = false;

  const input = (n: string) => form.elements.namedItem(n) as HTMLInputElement | null;
  const val = (n: string) => input(n)?.value ?? "";
  const setStatus = (t: string) => { if (status) status.textContent = t; };
  const show = (el: HTMLElement | null, on: boolean) => {
    if (!el) return;
    el.hidden = !on;
    el.style.display = on ? "block" : "none";
  };
  const setErr = (f: WlField, msg: string) => {
    const el = form.querySelector<HTMLElement>(`[data-err="${f}"]`);
    if (el) el.textContent = msg;
    el?.closest(".field")?.classList.toggle("invalid", msg !== "");
    input(f)?.setAttribute("aria-invalid", msg !== "" ? "true" : "false");
  };
  const clearErrs = () => { for (const f of FIELDS) setErr(f, ""); };
  const codeMsg = (f: WlField, c: WlCode) =>
    c === "required" ? (f === "whatsapp" ? (d.waRequired ?? d.required ?? "") : (d.required ?? ""))
      : f === "email" ? (d.invalidEmail ?? "") : (d.invalidWa ?? "");
  const values = (): WaitlistValues => ({
    name: val("name"), email: val("email"), whatsapp: val("whatsapp"), newsletter: !!input("newsletter")?.checked,
  });

  const renderSummary = (errs: Partial<Record<WlField, string>>) => {
    const keys = FIELDS.filter((f) => errs[f]);
    if (!summary || keys.length === 0) return;
    const ul = summary.querySelector("ul");
    if (ul) {
      ul.innerHTML = "";
      for (const f of keys) {
        const li = form.ownerDocument.createElement("li");
        const a = form.ownerDocument.createElement("a");
        a.href = `#${input(f)?.id ?? ""}`;
        a.textContent = errs[f]!;
        li.appendChild(a);
        ul.appendChild(li);
      }
    }
    show(summary, true);
    summary.focus();
  };

  for (const f of FIELDS) {
    input(f)?.addEventListener("blur", () => {
      if (val(f) === "") return;
      const code = validateWaitlist(values())[f];
      setErr(f, code ? codeMsg(f, code) : "");
    });
  }

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    if (inFlight) return;
    clearErrs();
    setStatus("");
    show(summary, false);
    const bad = validateWaitlist(values());
    const msgs: Partial<Record<WlField, string>> = {};
    for (const f of FIELDS) if (bad[f]) { msgs[f] = codeMsg(f, bad[f]!); setErr(f, msgs[f]!); }
    if (Object.keys(msgs).length > 0) { renderSummary(msgs); return; }

    inFlight = true;
    button.disabled = true;
    button.textContent = d.sending ?? idleLabel;
    form.setAttribute("aria-busy", "true");
    const wa = val("whatsapp").trim();
    let r: WlResult;
    try {
      r = await submitWaitlist(doFetch, d.endpoint ?? "/api/training-waitlist", {
        name: val("name").trim(),
        company: val("company").trim(),
        email: val("email").trim(),
        whatsapp_number: wa === "" ? null : wa,
        interest: form.querySelector<HTMLInputElement>('input[name="interest"]:checked')?.value ?? "inhouse",
        note: val("note").trim(),
        source_page: d.sourcePage ?? "",
        locale: d.locale ?? "id",
        newsletter_opt_in: !!input("newsletter")?.checked,
        client_key: clientKey,
        website: val("website"),
      });
    } catch {
      r = { kind: "error" };
    }
    inFlight = false;
    button.disabled = false;
    button.textContent = idleLabel;
    form.removeAttribute("aria-busy");
    switch (r.kind) {
      case "ok":
        form.hidden = true;
        form.style.display = "none";
        show(done, true);
        done?.focus();
        break;
      case "fields":
        for (const f of FIELDS) if (r.fields[f]) setErr(f, r.fields[f]!);
        renderSummary(r.fields);
        break;
      case "invalid": setStatus(r.message); break;
      case "rate": setStatus(d.rate ?? ""); break;
      case "network": setStatus(d.network ?? ""); break;
      default: setStatus(d.server ?? "");
    }
  });
}

export function initAllWaitlistForms(doc: Document = document): void {
  doc.querySelectorAll<HTMLFormElement>("form[data-waitlist]").forEach((f) => initWaitlistForm(f));
}
