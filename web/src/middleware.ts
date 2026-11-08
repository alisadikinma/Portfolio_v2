import { matchAiBot } from "./lib/aiBots";
import { getConfig, requireInternalToken } from "./lib/config";
import { localizedPath, pickLang } from "./lib/i18n";

type Ctx = { request: Request; url?: URL };
type Next = () => Promise<Response>;

const CRAWLER_TIMEOUT_MS = 2000;
let tokenWarned = false;

/** Test seam: re-arm the warn-once flag. */
export function resetCrawlerWarnings(): void {
  tokenWarned = false;
}

const isStatic = (p: string): boolean =>
  p.startsWith("/_astro/") || p.startsWith("/api/") || /\.[A-Za-z0-9]+$/.test(p);

const redirect = (location: string, status: 301 | 302): Response =>
  new Response(null, { status, headers: { Location: location } });

/** Fire-and-forget. Never throws, never awaited by the caller. */
function logCrawlerHit(bot: string): void {
  let token = "";
  try {
    token = requireInternalToken();
  } catch {
    token = "";
  }
  if (token === "") {
    if (!tokenWarned) {
      tokenWarned = true;
      console.warn("[crawler-hit] INTERNAL_API_TOKEN not set; AI crawler hits are not being reported");
    }
    return;
  }
  try {
    const url = getConfig().apiInternalUrl.replace(/\/+$/, "") + "/internal/crawler-hit";
    Promise.resolve(
      fetch(url, {
        method: "POST",
        headers: { "X-Internal-Token": token, "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({ bot }),
        signal: AbortSignal.timeout(CRAWLER_TIMEOUT_MS),
      }),
    )
      .then((res) => {
        if (res.status !== 202) console.warn("[crawler-hit] unexpected status", res.status);
      })
      .catch((e: unknown) => console.warn("[crawler-hit]", e instanceof Error ? e.message : "request failed"));
  } catch (e) {
    console.warn("[crawler-hit]", e instanceof Error ? e.message : "request failed");
  }
}

export async function onRequest(context: Ctx, next: Next): Promise<Response> {
  const url = context.url ?? new URL(context.request.url);
  const path = url.pathname;
  if (isStatic(path)) return next();

  const bot = matchAiBot(context.request.headers.get("user-agent"));
  if (bot) logCrawlerHit(bot);

  const norm = path.length > 1 ? path.replace(/\/+$/, "") : path;

  if (norm === "/") {
    return redirect(localizedPath("/", pickLang(context.request.headers.get("accept-language"))) + "/", 302);
  }
  if (/^\/(id|en)\/awards-debug$/.test(norm)) return new Response("Not found", { status: 404 });

  const m = norm.match(/^\/(id|en)\/(awards|penghargaan|gallery)$/);
  if (m && (m[2] === "gallery" || m[2] === (m[1] === "en" ? "awards" : "penghargaan"))) {
    return redirect(`/${m[1]}/work?tab=${m[2] === "gallery" ? "gallery" : "awards"}`, 301);
  }

  if (!/^\/(id|en)(\/|$)/.test(norm)) return redirect(localizedPath(norm, "en") + url.search, 301);

  return next();
}
