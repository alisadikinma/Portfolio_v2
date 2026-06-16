import { readFile } from 'node:fs/promises';
import { basename } from 'node:path';
import { config } from '../config.js';

/** Thin HTTP client for the VPS bridge API (Phase E). Bearer = video-full:work token. */
export class Bridge {
  constructor({ baseUrl = config.bridge.baseUrl, token = config.bridge.token } = {}) {
    this.baseUrl = baseUrl.replace(/\/$/, '');
    this.token = token;
  }

  headers(extra = {}) {
    if (!this.token) throw new Error('bridge: VIDEO_FULL_WORKER_TOKEN not set');
    return { Authorization: `Bearer ${this.token}`, Accept: 'application/json', ...extra };
  }

  async #json(method, path, body) {
    const res = await fetch(`${this.baseUrl}${path}`, {
      method,
      headers: this.headers(body ? { 'Content-Type': 'application/json' } : {}),
      body: body ? JSON.stringify(body) : undefined,
    });
    if (!res.ok) throw new Error(`bridge ${method} ${path} ${res.status}`);
    return res.json();
  }

  /** Claim the next queued job. Returns the job spec or null. */
  async claim() {
    const { job } = await this.#json('GET', '/worker/video-full/claim');
    return job;
  }

  progress(id, progress, step) {
    return this.#json('PUT', `/worker/video-full/${id}/progress`, { progress, step });
  }

  putSegments(id, segments) {
    return this.#json('POST', `/worker/video-full/${id}/segments`, { segments });
  }

  /** Upload the final MP4 (multipart). */
  async uploadFinal(id, mp4Path) {
    const form = new FormData();
    form.append('final_video', new Blob([await readFile(mp4Path)], { type: 'video/mp4' }), basename(mp4Path));
    const res = await fetch(`${this.baseUrl}/worker/video-full/${id}/assets`, {
      method: 'POST', headers: this.headers(), body: form,
    });
    if (!res.ok) throw new Error(`bridge upload final ${res.status}`);
    return res.json();
  }

  /** Upload a per-segment preview + status. */
  async uploadSegmentPreview(id, segmentIndex, previewPath, status = 'done') {
    const form = new FormData();
    form.append('segment_index', String(segmentIndex));
    form.append('segment_status', status);
    if (previewPath) {
      form.append('preview', new Blob([await readFile(previewPath)], { type: 'video/mp4' }), basename(previewPath));
    }
    const res = await fetch(`${this.baseUrl}/worker/video-full/${id}/assets`, {
      method: 'POST', headers: this.headers(), body: form,
    });
    if (!res.ok) throw new Error(`bridge upload segment ${res.status}`);
    return res.json();
  }
}
