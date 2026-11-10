import { matchAiBot } from "./lib/aiBots";
import { getConfig, requireInternalToken } from "./lib/config";
import { localizedPath, pickLang, type Lang } from "./lib/i18n";
import { apiGetPost } from "./lib/api";
import { newTabLinks } from "./lib/new-tab";

type Ctx = { request: Request; url?: URL };
type Next = () => Promise<Response>;

const CRAWLER_TIMEOUT_MS = 2000;
const WARN_INTERVAL_MS = 60_000;
let tokenWarned = false;
const lastWarn = new Map<string, number>();

/** Test seam: re-arm the warn-once flag and rate limiter. */
export function resetCrawlerWarnings(): void {
  tokenWarned = false;
  lastWarn.clear();
}

/** console.warn at most once per minute per key (status code or "error"). */
function warnLimited(key: string, ...args: unknown[]): void {
  const now = Date.now();
  const last = lastWarn.get(key);
  if (last !== undefined && now - last < WARN_INTERVAL_MS) return;
  lastWarn.set(key, now);
  console.warn("[crawler-hit]", ...args);
}

const STATIC_EXT = /\.(?:js|css|png|jpe?g|webp|avif|gif|svg|ico|txt|xml|json|webmanifest|woff2?|mp4|webm|map|pdf)$/i;
const isStatic = (p: string): boolean => p.startsWith("/_astro/") || p === "/_image" || p.startsWith("/api/") || STATIC_EXT.test(p);

const redirect = (location: string, status: 301 | 302, headers: Record<string, string> = {}): Response =>
  new Response(null, { status, headers: { Location: location, ...headers } });

function cookieLang(cookie: string | null): Lang | null {
  const m = cookie?.match(/(?:^|;\s*)lang_preference=(id|en)(?=;|$)/);
  return m ? (m[1] as Lang) : null;
}

/** Target for an unprefixed blog URL: en if translated, else first other, else en; with that language's own slug. */
async function blogTarget(slug: string): Promise<{ lang: Lang; slug: string }> {
  try {
    const post = await apiGetPost<{ available_translations?: unknown; slugs?: Record<string, unknown> | null }>(slug, { lang: "en" });
    const langs = Array.isArray(post?.available_translations) ? (post.available_translations as unknown[]) : [];
    const lang: Lang = langs.includes("en") ? "en" : (langs.find((l): l is Lang => l === "id") ?? "en");
    const own = post?.slugs?.[lang];
    return { lang, slug: typeof own === "string" && own !== "" ? own : slug };
  } catch {
    return { lang: "en", slug };
  }
}

const SECURITY_HEADERS: Record<string, string> = {
  "Referrer-Policy": "strict-origin-when-cross-origin",
  "Permissions-Policy": "camera=(), microphone=(), geolocation=(), payment=()",
};

/** Adds the security headers a page did not set itself. Immutable headers get a copied Response. */
function withSecurityHeaders(res: Response): Response {
  const missing = Object.entries(SECURITY_HEADERS).filter(([k]) => !res.headers.has(k));
  if (missing.length === 0) return res;
  try {
    for (const [k, v] of missing) res.headers.set(k, v);
    return res;
  } catch {
    const headers = new Headers(res.headers);
    for (const [k, v] of missing) headers.set(k, v);
    return new Response(res.body, { status: res.status, statusText: res.statusText, headers });
  }
}

/** Public HTML (GET 200, no own Cache-Control): Cloudflare may keep it 5 min and serve stale up to a day while it refreshes. Slow SSR then hits a crawler at most once per 5 min. */
const EDGE_CACHE = "public, max-age=0, s-maxage=300, stale-while-revalidate=86400";
function withEdgeCache(res: Response, method: string): Response {
  if (method !== "GET" || res.status !== 200 || res.headers.has("cache-control")) return res;
  if (!(res.headers.get("content-type") ?? "").toLowerCase().includes("text/html")) return res;
  try {
    res.headers.set("Cache-Control", EDGE_CACHE);
    return res;
  } catch {
    const headers = new Headers(res.headers);
    headers.set("Cache-Control", EDGE_CACHE);
    return new Response(res.body, { status: res.status, statusText: res.statusText, headers });
  }
}

/** www.<site host> is a duplicate of the whole site; Google indexed it. Send it to the canonical origin in one hop. */
function wwwRedirect(request: Request, url: URL): Response | null {
  const site = new URL(getConfig().siteUrl);
  const host = (request.headers.get("host") ?? url.host).toLowerCase().replace(/:\d+$/, "");
  if (host !== "www." + site.hostname) return null;
  return redirect(site.origin + url.pathname + url.search, 301);
}

/** HTML pages: every link opens a new tab (lib/new-tab.ts). Other bodies pass through untouched. */
async function withNewTabLinks(res: Response): Promise<Response> {
  if (!(res.headers.get("content-type") ?? "").toLowerCase().includes("text/html") || res.body === null) return res;
  const headers = new Headers(res.headers);
  headers.delete("content-length");
  return new Response(newTabLinks(await res.text()), { status: res.status, statusText: res.statusText, headers });
}

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
        if (res.status !== 202) warnLimited(String(res.status), "unexpected status", res.status);
      })
      .catch((e: unknown) => warnLimited("error", e instanceof Error ? e.message : "request failed"));
  } catch (e) {
    warnLimited("error", e instanceof Error ? e.message : "request failed");
  }
}

export async function onRequest(context: Ctx, next: Next): Promise<Response> {
  const url = context.url ?? new URL(context.request.url);
  const path = url.pathname;
  const toApex = wwwRedirect(context.request, url);
  if (toApex) return toApex;
  if (isStatic(path)) return next();

  const bot = matchAiBot(context.request.headers.get("user-agent"));
  if (bot) logCrawlerHit(bot);

  const search = url.search;

  if (path === "/") {
    const lang = cookieLang(context.request.headers.get("cookie")) ?? pickLang(context.request.headers.get("accept-language"));
    return redirect(`/${lang}${search}`, 302, {
      "Cache-Control": "private, no-store",
      Vary: "Accept-Language, Cookie",
    });
  }

  const norm = path.replace(/\/+$/, "") || "/";

  // Legacy URLs first: one hop straight to the final target, any language combination.
  const legacy = norm.match(/^\/(?:(id|en)\/)?(awards|penghargaan|gallery|awards-debug)$/i);
  if (legacy) {
    if (legacy[2].toLowerCase() === "awards-debug") return new Response("Not found", { status: 404 });
    const lang = (legacy[1]?.toLowerCase() ?? "en") as Lang;
    // Keep the visitor's query (UTM etc.); the mapped tab always wins over an old ?tab=.
    const q = new URLSearchParams(search);
    q.delete("tab");
    const rest = q.toString();
    const tab = legacy[2].toLowerCase() === "gallery" ? "gallery" : "awards";
    return redirect(`/${lang}/work?tab=${tab}${rest ? `&${rest}` : ""}`, 301);
  }

  const prefixed = norm.match(/^\/(id|en)(?=\/|$)/i);
  if (prefixed) {
    const fixed = prefixed[1].toLowerCase() + norm.slice(prefixed[0].length);
    const target = "/" + fixed + search;
    if (path !== "/" + fixed) return redirect(target, 301);
    return withEdgeCache(withSecurityHeaders(await withNewTabLinks(await next())), context.request.method);
  }

  const blog = norm.match(/^\/blog\/([^/]+)$/);
  if (blog) {
    let slug = blog[1];
    try {
      slug = decodeURIComponent(slug);
    } catch {
      /* keep raw */
    }
    const t = await blogTarget(slug);
    return redirect(`/${t.lang}/blog/${encodeURIComponent(t.slug)}${search}`, 301);
  }
  return redirect(localizedPath(norm, "en") + search, 301);
}
