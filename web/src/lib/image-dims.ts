import { imageSize } from "image-size";

export const DIMS_CACHE_MAX = 1000;
export const FAIL_TTL_MS = 10 * 60 * 1000;
const FETCH_TIMEOUT_MS = 2000;
const RANGE_BYTES = 65536;
const CONCURRENCY = 6;

interface Dims {
  w: number;
  h: number;
}

// Success entries never expire (CMS images are immutable); bounded LRU via Map insertion order.
const ok = new Map<string, Dims>();
// Failures are remembered briefly so a dead image does not cost 2 s on every render.
const failed = new Map<string, number>();
const inflight = new Map<string, Promise<Dims | null>>();

export function resetImageDimsCache(): void {
  ok.clear();
  failed.clear();
  inflight.clear();
}

const PRIVATE_HOST = /^(localhost|.*\.local|.*\.internal|\[.*\]|0\.0\.0\.0|127\.\d+\.\d+\.\d+|10\.\d+\.\d+\.\d+|192\.168\.\d+\.\d+|169\.254\.\d+\.\d+|172\.(1[6-9]|2\d|3[01])\.\d+\.\d+)$/i;

function fetchable(src: string): boolean {
  try {
    const u = new URL(src);
    return (u.protocol === "http:" || u.protocol === "https:") && !PRIVATE_HOST.test(u.hostname);
  } catch {
    return false;
  }
}

/** Reads at most RANGE_BYTES: a server that ignores Range must not make us download the file. */
async function readHead(res: Response): Promise<Uint8Array> {
  if (!res.body) return new Uint8Array(await res.arrayBuffer()).subarray(0, RANGE_BYTES);
  const reader = res.body.getReader();
  const chunks: Uint8Array[] = [];
  let total = 0;
  while (total < RANGE_BYTES) {
    const { done, value } = await reader.read();
    if (done) break;
    chunks.push(value);
    total += value.length;
  }
  void reader.cancel().catch(() => undefined);
  const out = new Uint8Array(Math.min(total, RANGE_BYTES));
  let at = 0;
  for (const c of chunks) {
    const take = Math.min(c.length, out.length - at);
    out.set(c.subarray(0, take), at);
    at += take;
    if (at >= out.length) break;
  }
  return out;
}

async function load(src: string, timeoutMs: number): Promise<Dims | null> {
  const ctl = new AbortController();
  const timer = setTimeout(() => ctl.abort(), timeoutMs);
  try {
    const res = await fetch(src, { headers: { Range: `bytes=0-${RANGE_BYTES - 1}` }, signal: ctl.signal });
    if (res.status !== 200 && res.status !== 206) return null;
    const head = await readHead(res);
    const size = imageSize(head);
    let w = size.width;
    let h = size.height;
    if (!w || !h) return null;
    // Browsers apply EXIF orientation, so 90/270 degree images display swapped.
    if ((size.orientation ?? 1) >= 5) [w, h] = [h, w];
    return { w, h };
  } catch {
    return null;
  } finally {
    clearTimeout(timer);
  }
}

function dimsFor(src: string, timeoutMs: number): Promise<Dims | null> {
  const hit = ok.get(src);
  if (hit) {
    ok.delete(src);
    ok.set(src, hit);
    return Promise.resolve(hit);
  }
  const bad = failed.get(src);
  if (bad !== undefined) {
    if (Date.now() < bad) return Promise.resolve(null);
    failed.delete(src);
  }
  const pending = inflight.get(src);
  if (pending) return pending;
  const p = load(src, timeoutMs).then((d) => {
    inflight.delete(src);
    if (d) {
      ok.set(src, d);
      while (ok.size > DIMS_CACHE_MAX) {
        const oldest = ok.keys().next().value;
        if (oldest === undefined) break;
        ok.delete(oldest);
      }
    } else {
      failed.set(src, Date.now() + FAIL_TTL_MS);
      if (failed.size > DIMS_CACHE_MAX) failed.delete(failed.keys().next().value as string);
    }
    return d;
  });
  inflight.set(src, p);
  return p;
}

const IMG_TAG = /<img\b(?:"[^"]*"|'[^']*'|[^'">])*>/gi;
const attr = (tag: string, name: string): string | undefined => {
  const m = new RegExp(`\\s${name}\\s*=\\s*(?:"([^"]*)"|'([^']*)'|([^\\s"'>]+))`, "i").exec(tag);
  return m ? (m[1] ?? m[2] ?? m[3]) : undefined;
};
const hasAttr = (tag: string, name: string) => {
  const v = attr(tag, name);
  return v !== undefined && v.trim() !== "";
};
const decodeAmp = (s: string) => s.replace(/&amp;/g, "&");

/**
 * For every <img> in already-sanitized HTML that lacks width/height and has an http(s) src,
 * reads the real size from the first bytes and injects it (prevents layout shift, satisfies the SEO gate).
 * Any failure leaves the tag unchanged: sizes are never guessed.
 */
export async function addImageDimensions(html: string, opts: { timeoutMs?: number } = {}): Promise<string> {
  if (!html || !html.includes("<img")) return html;
  const timeoutMs = opts.timeoutMs ?? FETCH_TIMEOUT_MS;
  const tags = [...new Set(html.match(IMG_TAG) ?? [])];
  const todo = tags
    .map((tag) => ({ tag, src: attr(tag, "src") }))
    .filter((t): t is { tag: string; src: string } => !!t.src && !(hasAttr(t.tag, "width") && hasAttr(t.tag, "height")))
    .map((t) => ({ ...t, url: decodeAmp(t.src) }))
    .filter((t) => fetchable(t.url));
  if (todo.length === 0) return html;

  const replaced = new Map<string, string>();
  let next = 0;
  const worker = async () => {
    while (next < todo.length) {
      const t = todo[next++];
      const d = await dimsFor(t.url, timeoutMs);
      if (!d) continue;
      const w = Number(attr(t.tag, "width"));
      const h = Number(attr(t.tag, "height"));
      let width = d.w;
      let height = d.h;
      if (w > 0) {
        width = w;
        height = Math.max(1, Math.round((w * d.h) / d.w));
      } else if (h > 0) {
        height = h;
        width = Math.max(1, Math.round((h * d.w) / d.h));
      }
      const stripped = t.tag.replace(/\s(?:width|height)\s*=\s*(?:"[^"]*"|'[^']*'|[^\s"'>]+)/gi, "");
      replaced.set(t.tag, stripped.replace(/\s*\/?>$/, ` width="${width}" height="${height}" />`));
    }
  };
  await Promise.all(Array.from({ length: Math.min(CONCURRENCY, todo.length) }, worker));
  return html.replace(IMG_TAG, (tag) => replaced.get(tag) ?? tag);
}

/** Real pixel size of one remote image (cached), or null. Never guessed. */
export async function imageDimensions(src: string, opts: { timeoutMs?: number } = {}): Promise<{ w: number; h: number } | null> {
  if (!fetchable(src)) return null;
  return dimsFor(src, opts.timeoutMs ?? FETCH_TIMEOUT_MS);
}
