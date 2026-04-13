# Content Engine Enhancements — Pagination + Auto Mode + Scheduling

**Date:** April 13, 2026
**Status:** Design Approved

---

## 1. Pagination

**Problem:** Ideas table shows all rows, requires excessive scrolling.

**Solution:** Backend paginated API (15 per page) + frontend pagination controls.

**Backend:**
- `ContentIdeaController::index()` — change `->get()` to `->paginate(15)`, return `meta` + `links`

**Frontend:**
- Add page state, prev/next buttons below table
- Show "Page X of Y" with page numbers

---

## 2. Auto Mode (Toggle per Idea)

**Problem:** Every idea requires manual step-by-step: article preview → approve → image gen → approve → finalize → publish. For bulk content, this is tedious.

**Solution:** A toggle per idea — "Auto" mode — that runs the full pipeline without manual approvals.

**Flow:**
```
Manual mode (current): draft → researching → article_ready [WAIT] → approve → generating_images [WAIT] → images_ready [WAIT] → publish
Auto mode:            draft → researching → article_ready → auto-approve → generating_images → images_ready → auto-publish
```

**Implementation:**
- Add `auto_mode` boolean column on `content_ideas` table (default false)
- Toggle checkbox in the ideas table row
- When article generation completes (status → `article_ready`) AND `auto_mode` is true:
  - Auto-approve article
  - Auto-start image generation
- When image generation completes (status → `images_ready`) AND `auto_mode` is true:
  - Auto-publish to blog
- Frontend: checkbox column in table + batch toggle

---

## 3. Scheduled Generation

**Problem:** User wants to schedule when content gets generated, not just trigger manually.

**Solution:** Schedule field per idea — date/time when generation should start.

**Implementation:**
- Add `scheduled_at` datetime column on `content_ideas` table (nullable)
- Date/time picker in the ideas table or inline form
- Laravel scheduler command: `artisan content:process-scheduled`
  - Runs every minute via cron
  - Finds ideas where `status = 'draft'` AND `scheduled_at <= now()` AND `scheduled_at IS NOT NULL`
  - Auto-starts research for each
  - Combined with auto_mode, this creates full automation
- Frontend: date picker in the Add Idea form + inline edit

---

## Combined Flow

```
User adds idea with:
  - auto_mode: true
  - scheduled_at: 2026-04-14 09:00

At 09:00, cron runs:
  1. Picks up scheduled idea → starts research (SSH → Claude CLI)
  2. Article generated → auto-approve (skip preview)
  3. Image generation starts → auto-generate all segments
  4. Images ready → auto-publish to blog
  5. Status → completed
```
