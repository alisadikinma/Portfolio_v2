import { apiGet } from "./api";
import type { Lang } from "./i18n";

/** wa.me link from settings site.contact_phone; "" when missing/invalid/unreachable (never fake a number). */
export async function waLink(lang: Lang): Promise<string> {
  try {
    const site = await apiGet<{ contact_phone?: unknown }>("/settings/site", { lang });
    const phone = site?.contact_phone;
    const digits = typeof phone === "string" ? phone.replace(/\D/g, "") : "";
    return digits !== "" ? `https://wa.me/${digits}` : "";
  } catch {
    return "";
  }
}
