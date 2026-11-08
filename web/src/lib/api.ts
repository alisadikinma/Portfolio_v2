import { getConfig } from "./config";

export type Lang = "id" | "en";

export interface ApiGetOptions {
  lang: Lang;
  /** cache lifetime in ms (default 60 000) */
  ttl?: number;
}

export const DEFAULT_TTL_MS = 60_000;
export const TIMEOUT_MS = 4000;

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

// Expired entries are kept on purpose: they are the stale fallback.
const cache = new Map<string, Entry>();
const inflight = new Map<string, Promise<unknown>>();

export function resetApiCache(): void {
  cache.clear();
  inflight.clear();
}

async function fetchData(url: string, lang: Lang): Promise<{ notFound: boolean; data: unknown }> {
  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), TIMEOUT_MS);
  try {
    let res: Response;
    try {
      res = await fetch(url, {
        headers: { Accept: "application/json", Cookie: `lang_preference=${lang}` },
        signal: ctrl.signal,
      });
    } catch (e) {
      throw new ApiError(0, url, `ApiError network/timeout ${url}: ${(e as Error).message}`);
    }
    if (res.status === 404) return { notFound: true, data: null };
    if (!res.ok) throw new ApiError(res.status, url);
    let body: unknown;
    try {
      body = await res.json();
    } catch {
      throw new ApiError(res.status, url, `ApiError malformed JSON ${url}`);
    }
    if (body === null || typeof body !== "object" || !("data" in body)) {
      throw new ApiError(res.status, url, `ApiError malformed envelope ${url}`);
    }
    return { notFound: false, data: (body as { data: unknown }).data };
  } finally {
    clearTimeout(timer);
  }
}

export async function apiGet<T = unknown>(path: string, opts: ApiGetOptions): Promise<T | null> {
  const { apiInternalUrl } = getConfig();
  const sep = path.includes("?") ? "&" : "?";
  const url = `${apiInternalUrl}${path}${sep}lang=${opts.lang}`;
  const ttl = opts.ttl ?? DEFAULT_TTL_MS;

  const hit = cache.get(url);
  if (hit && hit.expires > Date.now()) return hit.value as T | null;

  const pending = inflight.get(url);
  if (pending) return pending as Promise<T | null>;

  const p = (async (): Promise<T | null> => {
    try {
      const r = await fetchData(url, opts.lang);
      const value = r.notFound ? null : r.data;
      cache.set(url, { value, expires: Date.now() + ttl });
      return value as T | null;
    } catch (e) {
      const stale = cache.get(url);
      if (stale && e instanceof ApiError) {
        console.warn("[api] stale", url, e.status);
        return stale.value as T | null;
      }
      throw e;
    }
  })().finally(() => inflight.delete(url));

  inflight.set(url, p);
  return p;
}
