import { apiGet } from "./api";
import type { Lang } from "./i18n";

/**
 * wa.me link from settings site.contact_phone; "" when missing/invalid/unreachable (never fake a number).
 * `text` prefills the message, so a lead shows which page it came from.
 */
export async function waLink(lang: Lang, text?: string): Promise<string> {
  try {
    const site = await apiGet<{ contact_phone?: unknown }>("/settings/site", { lang });
    const phone = site?.contact_phone;
    const digits = typeof phone === "string" ? phone.replace(/\D/g, "") : "";
    if (digits === "") return "";
    return text ? `https://wa.me/${digits}?text=${encodeURIComponent(text)}` : `https://wa.me/${digits}`;
  } catch {
    return "";
  }
}
