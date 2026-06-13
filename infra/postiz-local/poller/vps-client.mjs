// VPS coordination client — talks to the Laravel automation endpoints.
// See docs/plans/2026-06-13-postiz-local-node-crosspost.md (Phase I).
//
// All calls carry the VPS automation bearer token. The local node is dumb:
// it claims jobs, reports results, and syncs the Postiz integration list.

export class VpsClient {
  /**
   * @param {{baseUrl:string, token:string, fetchImpl?:typeof fetch}} opts
   *   baseUrl e.g. https://alisadikinma.com/api
   */
  constructor({ baseUrl, token, fetchImpl = fetch }) {
    this.baseUrl = baseUrl.replace(/\/$/, '');
    this.token = token;
    this.fetchImpl = fetchImpl;
  }

  get #headers() {
    return {
      Authorization: `Bearer ${this.token}`,
      Accept: 'application/json',
    };
  }

  /** GET /automation/postiz/pending — returns { jobs:[], upcoming:[] }. */
  async fetchPending(worker, limit = 10) {
    const url = `${this.baseUrl}/automation/postiz/pending?worker=${encodeURIComponent(worker)}&limit=${limit}`;
    const res = await this.fetchImpl(url, { method: 'GET', headers: this.#headers });
    if (!res.ok) {
      throw new Error(`VPS pending failed: HTTP ${res.status}`);
    }
    return res.json();
  }

  /** POST /automation/postiz/{job}/result — body: {status, postiz_post_id?, permalink?, error?}. */
  async reportResult(jobId, body) {
    const url = `${this.baseUrl}/automation/postiz/${jobId}/result`;
    const res = await this.fetchImpl(url, {
      method: 'POST',
      headers: { ...this.#headers, 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    if (!res.ok) {
      throw new Error(`VPS result failed: HTTP ${res.status}`);
    }
    return res.json();
  }

  /** POST /automation/postiz/channels/sync — body: {channels:[...]}. */
  async syncChannels(channels) {
    const url = `${this.baseUrl}/automation/postiz/channels/sync`;
    const res = await this.fetchImpl(url, {
      method: 'POST',
      headers: { ...this.#headers, 'Content-Type': 'application/json' },
      body: JSON.stringify({ channels }),
    });
    if (!res.ok) {
      throw new Error(`VPS channels/sync failed: HTTP ${res.status}`);
    }
    return res.json();
  }
}
