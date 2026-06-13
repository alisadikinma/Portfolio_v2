// Phase I poller tests — node --test, mocked fetch (no network).
// See docs/plans/2026-06-13-postiz-local-node-crosspost.md.

import { test } from 'node:test';
import assert from 'node:assert/strict';

import { VpsClient } from './vps-client.mjs';
import { PostizClient } from './postiz-client.mjs';
import {
  buildPostEnvelope,
  processJob,
  runClaimPoll,
  runConfirmPoll,
  runIntegrationSync,
} from './index.mjs';

const VPS = 'https://vps.test/api';
const POSTIZ = 'http://postiz.test';

function jsonResp(obj, status = 200) {
  return { ok: status < 400, status, json: async () => obj, text: async () => JSON.stringify(obj) };
}
function blobResp() {
  return { ok: true, status: 200, blob: async () => new Blob(['bytes']) };
}

/**
 * One mock fetch routing by URL substring. `state` lets a test control what
 * GET /posts + GET /integrations return.
 */
function makeMock(state = {}) {
  const calls = [];
  const impl = async (url, opts = {}) => {
    const method = opts.method ?? 'GET';
    const body = opts.body && typeof opts.body === 'string' ? JSON.parse(opts.body) : opts.body;
    calls.push({ url, method, body });

    if (url.includes('/automation/postiz/pending')) return jsonResp(state.pending ?? { jobs: [], upcoming: [] });
    if (url.includes('/automation/postiz/') && url.endsWith('/result')) return jsonResp({ ok: true });
    if (url.includes('/automation/postiz/channels/sync')) return jsonResp({ ok: true });

    if (url.includes('/public/v1/upload')) {
      state._uploadN = (state._uploadN ?? 0) + 1;
      return jsonResp({ id: `media-${state._uploadN}`, path: `/uploads/m${state._uploadN}.png` });
    }
    if (url.includes('/public/v1/posts') && method === 'POST') {
      if (state.postFails) return jsonResp({ message: 'boom' }, 500);
      return jsonResp({ id: state.createdPostId ?? 'pz-1' });
    }
    if (url.includes('/public/v1/posts') && method === 'GET') return jsonResp(state.posts ?? []);
    if (url.includes('/public/v1/integrations')) return jsonResp(state.integrations ?? []);

    // Otherwise: a media URL fetch → return blob bytes.
    return blobResp();
  };
  impl.calls = calls;
  return impl;
}

function clients(state) {
  const fetchImpl = makeMock(state);
  return {
    fetchImpl,
    vps: new VpsClient({ baseUrl: VPS, token: 't', fetchImpl }),
    postiz: new PostizClient({ baseUrl: POSTIZ, apiKey: 'k', fetchImpl, sleep: async () => {} }),
  };
}

test('buildPostEnvelope includes all required fields + post_type', () => {
  const env = buildPostEnvelope(
    { integrationId: 77, content: 'hi', mediaItems: [{ id: 'a', path: '/a' }], postType: 'post' },
    new Date('2026-06-13T00:00:00.000Z')
  );
  assert.equal(env.type, 'now');
  assert.equal(env.date, '2026-06-13T00:00:00.000Z');
  assert.equal(env.shortLink, false);
  assert.deepEqual(env.tags, []);
  assert.equal(env.posts[0].integration.id, '77');
  assert.equal(env.posts[0].settings.post_type, 'post');
  assert.deepEqual(env.posts[0].value[0].image, [{ id: 'a', path: '/a' }]);
});

test('buildPostEnvelope medium variant carries settings.canonical', () => {
  const env = buildPostEnvelope({ integrationId: 12, content: '<p>x</p>', canonical: 'https://alisadikinma.com/blog/x' });
  assert.equal(env.posts[0].settings.canonical, 'https://alisadikinma.com/blog/x');
});

test('processJob uploads each media then enqueues and returns post id', async () => {
  const { postiz, fetchImpl } = clients({ createdPostId: 'pz-9' });
  const job = {
    job_id: 1,
    platform: 'instagram',
    postiz_integration_id: '77',
    media_urls: ['https://alisadikinma.com/storage/s1.png', 'https://alisadikinma.com/storage/s2.png'],
    media_types: ['image', 'image'],
    caption: 'cap',
    post_type: 'post',
  };
  const postId = await processJob(job, { postiz });
  assert.equal(postId, 'pz-9');
  assert.equal(fetchImpl.calls.filter((c) => c.url.includes('/upload')).length, 2);
});

test('runClaimPoll claims, posts, callbacks accepted with postiz_post_id, dedupes', async () => {
  const state = {
    createdPostId: 'pz-7',
    pending: {
      jobs: [{ job_id: 5, platform: 'instagram', postiz_integration_id: '77', media_urls: ['https://alisadikinma.com/storage/a.png'], media_types: ['image'], caption: 'c', post_type: 'post' }],
      upcoming: [],
    },
  };
  const { vps, postiz, fetchImpl } = clients(state);
  const accepted = new Map();

  await runClaimPoll({ vps, postiz, worker: 'local-1', accepted });
  assert.equal(accepted.get(5), 'pz-7');
  const resultCalls = fetchImpl.calls.filter((c) => c.url.endsWith('/result'));
  assert.equal(resultCalls.length, 1);
  assert.equal(resultCalls[0].body.status, 'accepted');
  assert.equal(resultCalls[0].body.postiz_post_id, 'pz-7');

  // Second tick, same job still returned by pending → must be skipped (dedupe).
  await runClaimPoll({ vps, postiz, worker: 'local-1', accepted });
  assert.equal(fetchImpl.calls.filter((c) => c.url.endsWith('/result')).length, 1);
});

test('runClaimPoll callbacks failed when enqueue errors', async () => {
  const state = {
    postFails: true,
    pending: { jobs: [{ job_id: 6, platform: 'instagram', postiz_integration_id: '77', media_urls: [], media_types: [], caption: 'c', post_type: 'post' }] },
  };
  const { vps, postiz, fetchImpl } = clients(state);
  const accepted = new Map();
  await runClaimPoll({ vps, postiz, worker: 'local-1', accepted });

  const resultCalls = fetchImpl.calls.filter((c) => c.url.endsWith('/result'));
  assert.equal(resultCalls[0].body.status, 'failed');
  assert.equal(accepted.has(6), false);
});

test('runConfirmPoll reports published on PUBLISHED, failed on ERROR', async () => {
  // PUBLISHED
  const pubState = { posts: [{ id: 'pz-1', state: 'PUBLISHED', releaseURL: 'https://instagram.com/p/x' }] };
  let { vps, postiz, fetchImpl } = clients(pubState);
  let accepted = new Map([[10, 'pz-1']]);
  await runConfirmPoll({ vps, postiz, accepted });
  let r = fetchImpl.calls.find((c) => c.url.endsWith('/result'));
  assert.equal(r.body.status, 'published');
  assert.equal(r.body.permalink, 'https://instagram.com/p/x');
  assert.equal(accepted.has(10), false);

  // ERROR
  const errState = { posts: [{ id: 'pz-2', state: 'ERROR' }] };
  ({ vps, postiz, fetchImpl } = clients(errState));
  accepted = new Map([[11, 'pz-2']]);
  await runConfirmPoll({ vps, postiz, accepted });
  r = fetchImpl.calls.find((c) => c.url.endsWith('/result'));
  assert.equal(r.body.status, 'failed');
  assert.equal(accepted.has(11), false);
});

test('runConfirmPoll leaves QUEUE jobs pending', async () => {
  const { vps, postiz } = clients({ posts: [{ id: 'pz-3', state: 'QUEUE' }] });
  const accepted = new Map([[12, 'pz-3']]);
  await runConfirmPoll({ vps, postiz, accepted });
  assert.equal(accepted.has(12), true);
});

test('runConfirmPoll gives up and reports failed after maxConfirmAttempts', async () => {
  const { vps, postiz, fetchImpl } = clients({ posts: [] }); // post never appears
  const accepted = new Map([[20, 'pz-missing']]);
  const attempts = new Map();
  // 2 ticks below cap → still pending; 3rd hits cap → failed.
  await runConfirmPoll({ vps, postiz, accepted, attempts, maxConfirmAttempts: 3 });
  await runConfirmPoll({ vps, postiz, accepted, attempts, maxConfirmAttempts: 3 });
  assert.equal(accepted.has(20), true);
  await runConfirmPoll({ vps, postiz, accepted, attempts, maxConfirmAttempts: 3 });
  assert.equal(accepted.has(20), false);
  const r = fetchImpl.calls.find((c) => c.url.endsWith('/result'));
  assert.equal(r.body.status, 'failed');
});

test('runIntegrationSync maps and posts the channel list', async () => {
  const state = {
    integrations: [
      { id: 1, identifier: 'instagram', profile: 'alisadikinma', disabled: false },
      { id: 2, identifier: 'medium', profile: 'ali', disabled: true },
    ],
  };
  const { vps, postiz, fetchImpl } = clients(state);
  const channels = await runIntegrationSync({ vps, postiz });

  assert.deepEqual(channels, [
    { platform: 'instagram', handle: 'alisadikinma', integration_id: '1', enabled: true },
    { platform: 'medium', handle: 'ali', integration_id: '2', enabled: false },
  ]);
  const sync = fetchImpl.calls.find((c) => c.url.includes('/channels/sync'));
  assert.equal(sync.body.channels.length, 2);
});

test('PostizClient retries once on HTTP 429', async () => {
  let n = 0;
  const fetchImpl = async (url, opts = {}) => {
    if (url.includes('/public/v1/posts') && (opts.method ?? 'GET') === 'POST') {
      n++;
      return n === 1 ? jsonResp({ message: 'rate' }, 429) : jsonResp({ id: 'pz-ok' });
    }
    return jsonResp({});
  };
  const postiz = new PostizClient({ baseUrl: POSTIZ, apiKey: 'k', fetchImpl, sleep: async () => {} });
  const id = await postiz.createPost(buildPostEnvelope({ integrationId: '1', content: 'x' }));
  assert.equal(id, 'pz-ok');
  assert.equal(n, 2);
});
