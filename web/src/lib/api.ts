import { getConfig } from "./config";

export type Lang = "id" | "en";

export interface ApiGetOptions {
  lang: Lang;
  /** cache lifetime in ms (default 60 000) */
  ttl?: number;
}

export interface ApiTextOptions {
  ttl?: number;
}

export interface PageResult<T> {
  data: T[];
  meta: Record<string, unknown> | null;
  links: Record<string, unknown> | null;
}

export const DEFAULT_TTL_MS = 60_000;
export const TIMEOUT_MS = 4000;
export const CACHE_MAX_ENTRIES = 500;
/** An expired entry may still serve as a stale fallback for this long after it expired. */
export const MAX_STALE_MS = 24 * 60 * 60 * 1000;

export class ApiError extends Error {
  status: number;
  url: string;
  constructor(status: number, url: string, message?: string) {
    super(message ?? `ApiError ${status} ${url}`);
    this.name = "ApiError";
    this.status = status;
    this.url = url;
  }
}

interface Entry {
  value: unknown;
  expires: number;
}

type Kind = "data" | "page" | "text";

// Map keeps insertion order: re-inserting on hit makes the first key the LRU one.
// Expired entries are kept on purpose: they are the stale fallback (bounded by MAX_STALE_MS).
const cache = new Map<string, Entry>();
const inflight = new Map<string, Promise<unknown>>();

export function resetApiCache(): void {
  cache.clear();
  inflight.clear();
}

function remember(key: string, entry: Entry): void {
  cache.delete(key);
  cache.set(key, entry);
  while (cache.size > CACHE_MAX_ENTRIES) {
    const oldest = cache.keys().next().value;
    if (oldest === undefined) break;
    cache.delete(oldest);
  }
}

function assertPath(path: string): void {
  if (!path.startsWith("/")) throw new Error(`api path must start with "/": ${path}`);
}

/** Tagged template: apiPath`/posts/${slug}` encodes every interpolated segment. */
export function apiPath(strings: TemplateStringsArray, ...segments: string[]): string {
  let out = strings[0];
  segments.forEach((seg, i) => {
    if (seg === "" || seg === "." || seg === "..") throw new Error(`invalid path segment: "${seg}"`);
    out += encodeURIComponent(seg) + strings[i + 1];
  });
  return out;
}

async function fetchBody(
  url: string,
  lang: Lang | null,
  kind: Kind,
): Promise<{ notFound: boolean; value: unknown }> {
  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), TIMEOUT_MS);
  const timeout = (e: unknown) =>
    new ApiError(0, url, `ApiError network/timeout ${url}: ${(e as Error).message}`);
  try {
    const headers: Record<string, string> = {
      Accept: kind === "text" ? "text/plain, application/xml, */*" : "application/json",
    };
    if (lang) headers.Cookie = `lang_preference=${lang}`;
    let res: Response;
    try {
      res = await fetch(url, { headers, signal: ctrl.signal });
    } catch (e) {
      throw timeout(e);
    }
    if (res.status === 404) return { notFound: true, value: null };
    if (!res.ok) throw new ApiError(res.status, url);
    if (kind === "text") {
      try {
        return { notFound: false, value: await res.text() };
      } catch (e) {
        throw ctrl.signal.aborted ? timeout(e) : new ApiError(res.status, url, `ApiError unreadable body ${url}`);
      }
    }
    let body: unknown;
    try {
      body = await res.json();
    } catch (e) {
      if (ctrl.signal.aborted) throw timeout(e);
      throw new ApiError(res.status, url, `ApiError malformed JSON ${url}`);
    }
    if (body === null || typeof body !== "object" || !("data" in body)) {
      throw new ApiError(res.status, url, `ApiError malformed envelope ${url}`);
    }
    const env = body as { data: unknown; meta?: unknown; links?: unknown };
    if (kind === "page") {
      return {
        notFound: false,
        value: {
          data: env.data,
          meta: (env.meta as Record<string, unknown>) ?? null,
          links: (env.links as Record<string, unknown>) ?? null,
        },
      };
    }
    return { notFound: false, value: env.data };
  } finally {
    clearTimeout(timer);
  }
}

async function cached<T>(url: string, lang: Lang | null, kind: Kind, ttl: number): Promise<T | null> {
  const key = `${kind}|${url}`;
  const hit = cache.get(key);
  if (hit && hit.expires > Date.now()) {
    remember(key, hit);
    return hit.value as T | null;
  }

  const pending = inflight.get(key);
  if (pending) return pending as Promise<T | null>;

  const p = (async (): Promise<T | null> => {
    try {
      const r = await fetchBody(url, lang, kind);
      remember(key, { value: r.value, expires: Date.now() + ttl });
      return r.value as T | null;
    } catch (e) {
      const stale = cache.get(key);
      if (
        stale &&
        stale.value !== null &&
        e instanceof ApiError &&
        Date.now() - stale.expires <= MAX_STALE_MS
      ) {
        console.warn("[api] stale", url, e.status);
        return stale.value as T | null;
      }
      throw e;
    }
  })().finally(() => inflight.delete(key));

  inflight.set(key, p);
  return p;
}

function langUrl(path: string, lang: Lang): string {
  assertPath(path);
  const { apiInternalUrl } = getConfig();
  const sep = path.includes("?") ? "&" : "?";
  return `${apiInternalUrl}${path}${sep}lang=${lang}`;
}

export async function apiGet<T = unknown>(path: string, opts: ApiGetOptions): Promise<T | null> {
  return cached<T>(langUrl(path, opts.lang), opts.lang, "data", opts.ttl ?? DEFAULT_TTL_MS);
}

/** Paginated list endpoints: returns data + meta + links. */
export async function apiGetPage<T = unknown>(path: string, opts: ApiGetOptions): Promise<PageResult<T> | null> {
  return cached<PageResult<T>>(langUrl(path, opts.lang), opts.lang, "page", opts.ttl ?? DEFAULT_TTL_MS);
}

/** Post detail for SSR: track=0 stops the backend from counting a view. */
export async function apiGetPost<T = unknown>(slug: string, opts: ApiGetOptions): Promise<T | null> {
  return apiGet<T>(`${apiPath`/posts/${slug}`}?track=0`, opts);
}

/** Plain text/XML endpoints (/sitemap*.xml, /llms*.txt): no envelope, no lang. */
export async function apiGetText(path: string, opts: ApiTextOptions = {}): Promise<string | null> {
  assertPath(path);
  const { apiInternalUrl } = getConfig();
  return cached<string>(`${apiInternalUrl}${path}`, null, "text", opts.ttl ?? DEFAULT_TTL_MS);
}
