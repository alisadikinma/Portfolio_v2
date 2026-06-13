// Postiz /public/v1 API client — the only path that can publish IG video
// carousels. See docs/plans/2026-06-13-postiz-local-node-crosspost.md (Phase I).
//
// Contracts source-verified against gitroomhq/postiz-app (2026-06-13):
//   POST /public/v1/upload      (multipart `file`)        → {id, name, path, ...}
//   POST /public/v1/posts       (full envelope)           → Post (use .id)
//   GET  /public/v1/posts       (?startDate&endDate)      → [{id, state, releaseURL, ...}]
//   GET  /public/v1/integrations                          → [{id, identifier, profile, disabled, ...}]
// Auth: API key in the Authorization header (Settings → Developers).

/** Retry once on HTTP 429 (Postiz API_LIMIT) after a short backoff. */
async function withRateLimitRetry(doFetch, { sleep = (ms) => new Promise((r) => setTimeout(r, ms)) } = {}) {
  let res = await doFetch();
  if (res.status === 429) {
    await sleep(2000);
    res = await doFetch();
  }
  return res;
}

export class PostizClient {
  /**
   * @param {{baseUrl:string, apiKey:string, fetchImpl?:typeof fetch, sleep?:(ms:number)=>Promise<void>}} opts
   *   baseUrl e.g. http://localhost:3000 (Postiz backend root; /public/v1 appended)
   */
  constructor({ baseUrl, apiKey, fetchImpl = fetch, sleep }) {
    this.base = `${baseUrl.replace(/\/$/, '')}/public/v1`;
    this.apiKey = apiKey;
    this.fetchImpl = fetchImpl;
    this.sleep = sleep;
  }

  get #authHeaders() {
    return { Authorization: this.apiKey };
  }

  /**
   * Fetch a media URL (HTTPS, app-hosted) and upload its bytes to Postiz.
   * @returns {Promise<{id:string, path:string}>}
   */
  async uploadFromUrl(url, name) {
    const mediaRes = await this.fetchImpl(url, { method: 'GET' });
    if (!mediaRes.ok) {
      throw new Error(`Media fetch failed (${name}): HTTP ${mediaRes.status}`);
    }
    const blob = await mediaRes.blob();

    const form = new FormData();
    form.append('file', blob, name);

    const res = await withRateLimitRetry(
      () => this.fetchImpl(`${this.base}/upload`, { method: 'POST', headers: this.#authHeaders, body: form }),
      { sleep: this.sleep }
    );
    if (!res.ok) {
      throw new Error(`Postiz upload failed (${name}): HTTP ${res.status}`);
    }
    const data = await res.json();
    return { id: data.id, path: data.path };
  }

  /** POST /public/v1/posts — returns the created Post id (async; confirm via getPosts). */
  async createPost(envelope) {
    const res = await withRateLimitRetry(
      () => this.fetchImpl(`${this.base}/posts`, {
        method: 'POST',
        headers: { ...this.#authHeaders, 'Content-Type': 'application/json' },
        body: JSON.stringify(envelope),
      }),
      { sleep: this.sleep }
    );
    if (!res.ok) {
      throw new Error(`Postiz createPost failed: HTTP ${res.status}`);
    }
    const data = await res.json();
    // Postiz returns the created post(s); normalize to a single id.
    if (Array.isArray(data)) {
      return data[0]?.id ?? data[0]?.postId;
    }
    return data.id ?? data.postId;
  }

  /** GET /public/v1/posts?startDate&endDate — date-range list for confirm-poll. */
  async getPosts(startDate, endDate) {
    const url = `${this.base}/posts?startDate=${encodeURIComponent(startDate)}&endDate=${encodeURIComponent(endDate)}`;
    const res = await this.fetchImpl(url, { method: 'GET', headers: this.#authHeaders });
    if (!res.ok) {
      throw new Error(`Postiz getPosts failed: HTTP ${res.status}`);
    }
    return res.json();
  }

  /** GET /public/v1/integrations — connected channels. */
  async getIntegrations() {
    const res = await this.fetchImpl(`${this.base}/integrations`, { method: 'GET', headers: this.#authHeaders });
    if (!res.ok) {
      throw new Error(`Postiz getIntegrations failed: HTTP ${res.status}`);
    }
    return res.json();
  }
}
