# GEO — Measuring AI Traffic (GA4 + crawler-hit logger)

Two distinct signals, two mechanisms:

1. **Human referrals from AI chats** (a person clicks alisadikinma.com out of a
   ChatGPT / Perplexity / Gemini / Claude answer) → these are real browser
   visits with a referrer, so **GA4** can see them. We isolate them into an
   "AI Traffic" channel group.
2. **AI bot crawls** (GPTBot, ClaudeBot, PerplexityBot, …) → these never run
   JavaScript and carry no referrer, so **GA4 can never see them**. They are
   logged server-side by the `LogAiCrawler` middleware and surfaced at
   `GET /api/admin/geo/crawler-hits`.

---

## 1. Enable GA4 (human-referral signal)

The gtag.js loader in `frontend/src/main.js` is gated by an env var. When the
id is empty, **no script loads and no network request fires** (dev-safe).

Set the measurement id in the frontend env (e.g. `frontend/.env.production`):

```env
VITE_GA4_MEASUREMENT_ID=G-XXXXXXXXXX
```

Rebuild the frontend (`npm run build`) for the change to take effect. Leave it
empty in dev/local to skip GA4 entirely.

---

## 2. Create the "AI Traffic" channel group in GA4 (manual, one-time)

GA4 has no built-in "AI Traffic" channel, so create a custom channel group:

1. GA4 → **Admin** → **Data display** → **Channel groups**
2. Click **Create channel group**
3. Name it **`AI Traffic`**
4. Add a new channel → condition **Source matches regex**
5. Paste the regex below (one line, verbatim):

```
chatgpt\.com|openai\.com|perplexity\.ai|gemini\.google\.com|copilot\.microsoft\.com|claude\.ai|anthropic\.com|deepseek\.com
```

6. **Drag the AI Traffic channel above the Referral channel** (order matters —
   without this, AI referrers fall through into generic Referral).
7. **Save.**

The channel group applies going forward (GA4 channel groups are not
retroactive to data before they were created).

---

## 3. AI bot crawls (server-side, GA4 cannot see these)

The `LogAiCrawler` web middleware (`backend/app/Http/Middleware/LogAiCrawler.php`)
matches the request User-Agent against a fixed AI-bot list — `GPTBot`,
`OAI-SearchBot`, `ChatGPT-User`, `ClaudeBot`, `Claude-Web`, `PerplexityBot`,
`Google-Extended`, `Applebot-Extended` — and increments a per-bot **daily**
counter in the `geo_crawler_hits` table. It is fail-open: a logging failure
never breaks the crawl response.

Read the last 30 days, grouped by bot (admin, `auth:sanctum`):

```
GET /api/admin/geo/crawler-hits
→ { "success": true, "data": [ { "bot": "GPTBot", "total": 142, "last_seen": "2026-06-25" }, … ] }
```

This is the only place AI-crawler activity is visible — GA4 cannot report it
because crawlers don't execute the gtag.js tag.
