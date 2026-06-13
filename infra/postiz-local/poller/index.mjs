// Postiz local-node poller — claim, upload, enqueue, confirm, callback.
// See docs/plans/2026-06-13-postiz-local-node-crosspost.md (Phase I).
//
// The VPS owns ALL scheduling + anti-double-publish authority. This node only:
//   claim-poll      (~2m): claim ready jobs → upload media → POST /posts →
//                          callback {accepted, postiz_post_id} (hands off watchdog)
//   confirm-poll    (~1m): GET /posts, match postiz_post_id → {published|failed}
//   integration-sync(hourly): push the Postiz integration list to the VPS
//
// Orchestration functions are pure (clients injected) so they unit-test with a
// mocked fetch. The cron wiring (start) runs only when invoked as the entrypoint.

import { pathToFileURL } from 'node:url';
import { VpsClient } from './vps-client.mjs';
import { PostizClient } from './postiz-client.mjs';

/**
 * Build the source-verified Postiz POST envelope. ALL fields required by
 * create.post.dto.ts: type, date, shortLink, tags, posts[]. settings.post_type
 * is the provider setting (IG 'post'); Medium adds settings.canonical.
 */
export function buildPostEnvelope({ integrationId, content, mediaItems = [], postType, canonical }, now = new Date()) {
  const settings = {};
  if (postType) settings.post_type = postType;
  if (canonical) settings.canonical = canonical;

  const value = [{ content: content ?? '', image: mediaItems.map((m) => ({ id: m.id, path: m.path })) }];

  return {
    type: 'now',
    date: now.toISOString(),
    shortLink: false,
    tags: [],
    posts: [{ integration: { id: String(integrationId) }, value, settings }],
  };
}

/** Resolve the post body for a job: Medium carries pre-built HTML, others the caption. */
function resolveContent(job) {
  return job.platform === 'medium' ? (job.content ?? '') : (job.caption ?? '');
}

function mediaName(job, url, i) {
  const ext = (job.media_types?.[i] ?? 'image') === 'video' ? 'mp4' : 'png';
  return `${job.platform}-job${job.job_id}-${String(i + 1).padStart(2, '0')}.${ext}`;
}

/** Upload all media for a job, then enqueue it on Postiz. Returns the Postiz post id. */
export async function processJob(job, { postiz }) {
  const mediaItems = [];
  const urls = job.media_urls ?? [];
  for (let i = 0; i < urls.length; i++) {
    mediaItems.push(await postiz.uploadFromUrl(urls[i], mediaName(job, urls[i], i)));
  }

  const envelope = buildPostEnvelope({
    integrationId: job.postiz_integration_id,
    content: resolveContent(job),
    mediaItems,
    postType: job.post_type,
    canonical: job.canonical,
  });

  return postiz.createPost(envelope);
}

/**
 * Claim ready jobs and enqueue each on Postiz. `accepted` is a Map keyed by
 * job_id (→ postiz_post_id) shared with confirm-poll; jobs already accepted are
 * skipped (dedupe within and across ticks).
 */
export async function runClaimPoll({ vps, postiz, worker, accepted }) {
  const { jobs = [] } = await vps.fetchPending(worker);
  for (const job of jobs) {
    if (accepted.has(job.job_id)) continue;
    try {
      const postId = await processJob(job, { postiz });
      await vps.reportResult(job.job_id, { status: 'accepted', postiz_post_id: String(postId) });
      accepted.set(job.job_id, String(postId));
    } catch (err) {
      await vps.reportResult(job.job_id, { status: 'failed', error: String(err?.message ?? err) });
    }
  }
}

/**
 * For each accepted job, match its postiz_post_id in the Postiz GET /posts
 * window and report the terminal state. GET-poll covers BOTH success AND error
 * (the success-only webhook can't).
 */
export async function runConfirmPoll({ vps, postiz, accepted, now = new Date() }) {
  if (accepted.size === 0) return;

  const start = new Date(now.getTime() - 6 * 60 * 60 * 1000).toISOString(); // 6h back
  const end = new Date(now.getTime() + 60 * 60 * 1000).toISOString(); // 1h forward
  const posts = await postiz.getPosts(start, end);
  const byId = new Map((posts ?? []).map((p) => [String(p.id), p]));

  for (const [jobId, postId] of [...accepted]) {
    const p = byId.get(String(postId));
    if (!p) continue;
    if (p.state === 'PUBLISHED') {
      await vps.reportResult(jobId, { status: 'published', permalink: p.releaseURL ?? null });
      accepted.delete(jobId);
    } else if (p.state === 'ERROR') {
      await vps.reportResult(jobId, { status: 'failed', error: 'Postiz state=ERROR' });
      accepted.delete(jobId);
    }
    // QUEUE/DRAFT → still in flight; check again next tick.
  }
}

/** Push the live Postiz integration list to the VPS (auto channel mapping). */
export async function runIntegrationSync({ vps, postiz }) {
  const integrations = await postiz.getIntegrations();
  const channels = (integrations ?? []).map((i) => ({
    platform: i.identifier,
    handle: i.profile,
    integration_id: String(i.id),
    enabled: !i.disabled,
  }));
  await vps.syncChannels(channels);
  return channels;
}

// ─── Entrypoint (cron wiring) — only when run directly ──────────────────────

async function start() {
  const cron = (await import('node-cron')).default;

  const vps = new VpsClient({ baseUrl: requireEnv('VPS_API_BASE_URL'), token: requireEnv('VPS_AUTOMATION_TOKEN') });
  const postiz = new PostizClient({ baseUrl: requireEnv('POSTIZ_API_BASE_URL'), apiKey: requireEnv('POSTIZ_API_KEY') });
  const worker = process.env.POLLER_WORKER_ID ?? 'local-1';
  const accepted = new Map();

  // integration-sync at startup, then hourly.
  await runIntegrationSync({ vps, postiz }).catch((e) => console.error('[integration-sync]', e.message));

  cron.schedule('*/2 * * * *', () => runClaimPoll({ vps, postiz, worker, accepted }).catch((e) => console.error('[claim-poll]', e.message)));
  cron.schedule('* * * * *', () => runConfirmPoll({ vps, postiz, accepted }).catch((e) => console.error('[confirm-poll]', e.message)));
  cron.schedule('0 * * * *', () => runIntegrationSync({ vps, postiz }).catch((e) => console.error('[integration-sync]', e.message)));

  console.log(`[poller] started — worker=${worker}, claim every 2m, confirm every 1m, sync hourly`);
}

function requireEnv(key) {
  const v = process.env[key];
  if (!v) throw new Error(`Missing required env: ${key}`);
  return v;
}

// Run the cron loop only when executed directly (not when imported by tests).
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  start().catch((e) => {
    console.error('[poller] fatal:', e);
    process.exit(1);
  });
}
