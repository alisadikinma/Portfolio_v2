# Newsletter System — Deploy Runbook

**Shipped:** 2026-05-05
**Plan:** [docs/plans/2026-05-05-newsletter-system-plan.md](../plans/2026-05-05-newsletter-system-plan.md)
**Design:** [docs/plans/2026-05-05-newsletter-system.md](../plans/2026-05-05-newsletter-system.md)

## What ships

- **3 new tables/columns**: `newsletters` gains 5 cols (`name`, `whatsapp_number`, `unsubscribe_token`, `consent_given_at`, `source`); new `newsletter_sends` audit table.
- **8 new API endpoints**: `POST /api/newsletter/unsubscribe-by-token` (public) + 8 routes under `/api/admin/newsletter/*` (auth:sanctum) + 3 routes under `/api/admin/settings/mail*` (auth:sanctum).
- **2 artisan commands**: `newsletter:send-weekly` (Friday 09:00 WIB cron) + `newsletter:backfill-tokens` (one-shot).
- **1 Mailable + Blade templates**: `App\Mail\WeeklyDigest` + `emails.weekly-digest{,-text}.blade.php` (Dark Cinema HTML, 600px table-based, inline CSS, UTM-tracked).
- **1 service provider**: `App\Providers\MailConfigOverrideProvider` reads `settings` table (group `mail`) at boot and overrides Laravel `mail.*` config. Operator manages SMTP via `/admin/about` UI — no `.env` edits needed.
- **1 settings seeder**: `MailSettingsSeeder` inserts 8 mail keys with Hostinger defaults (password null until operator sets via UI).
- **7 frontend changes**: `useNewsletter()` widened, 4 touchpoint forms now collect 3 fields (name/email/WhatsApp), new `NewsletterModal.vue`, new `NewsletterUnsubscribe.vue` page, new admin view `NewsletterSubscribers.vue` + sidebar entry, new "Email — SMTP Settings" card on `AboutSettings.vue`.
- **1 cleanup**: dead `handleSubscribe()` placeholder removed from `CTASection.vue`.

## Production deploy checklist

### 1. Configure Hostinger SMTP via admin panel (NOT .env)

SMTP config now lives in the `settings` table (group: `mail`) and is managed via the admin UI. The `MailConfigOverrideProvider` reads these settings at boot and overrides Laravel's `mail.*` config — so `.env` stays untouched and operator can change SMTP creds without redeploying.

Mailbox provider: **Hostinger** — `aiagent@alisadikinma.com`. Connection settings per Hostinger panel:

| Protocol | Host | Port | Encryption |
|---|---|---|---|
| Outgoing (SMTP) | `smtp.hostinger.com` | 465 | SSL |
| Incoming (IMAP) | `imap.hostinger.com` | 993 | SSL (read-only, for replies — not used by Laravel) |

**Steps:**

1. Make sure the seeder ran (auto-runs on deploy via deploy.sh, or manually):
   ```bash
   cd /var/www/Portfolio_v2/backend
   php artisan db:seed --class=MailSettingsSeeder --force
   ```
   This inserts 8 default rows (host, port, encryption, etc. pre-filled for Hostinger). Idempotent — safe to re-run.

2. Log in to admin panel → `/admin/about` → scroll to **"Email — SMTP Settings"** card (between Telegram and LinkedIn cards).

3. Verify the pre-filled defaults look right:
   - SMTP Host: `smtp.hostinger.com`
   - Port: `465`
   - Username: `aiagent@alisadikinma.com`
   - Encryption: `SSL (port 465)`
   - From Address: `aiagent@alisadikinma.com`
   - From Name: `Ali Sadikin`

4. **Paste the mailbox password** in the Password field. Get it from:
   - Hostinger panel → Email → `aiagent@alisadikinma.com` → "Manage" → either show existing password or reset to a new one
   - **Note**: password is encrypted via Laravel `Crypt::encryptString` before DB write — never stored plaintext, never returned by GET. UI shows `✓ Configured` badge once set.

5. Click **"Save SMTP Settings"** → toast confirms save.

6. Click **"📤 Send test email to me"** → defaults to your admin user's email. Wait ~3-10s. Inbox + spam folder should show "SMTP Test — Portfolio Admin" within 30s.

7. **Restart queue worker** so the persistent worker picks up new SMTP creds (the test send uses fresh config in-request, but queued newsletter jobs run in a separate process):
   ```bash
   sudo systemctl restart portfolio-queue.service
   ```

If the test fails, the toast shows the SMTP error verbatim (e.g. `Authentication failed`, `Connection timed out`, `Could not authenticate`). Common causes: wrong password, blocked port (some VPS providers block 25 by default), Hostinger throttle.

#### Why DB instead of .env?

- Operator changes credentials without SSH access or redeployment
- Password encrypted at rest (Laravel Crypt) — `.env` was plaintext
- Test-send button verifies config before saving subscribers' fate to broken SMTP
- Multi-environment safety: same codebase, different settings rows per env

### 2. SPF / DKIM (Hostinger handles by default)

Hostinger automatically configures SPF + DKIM for the mailbox domain when the email account is created. To verify:

1. Hostinger panel → Email → Domain → "DNS records"
2. Confirm SPF (`v=spf1 include:_spf.mail.hostinger.com`) and DKIM CNAMEs exist
3. Test deliverability after first send via https://www.mail-tester.com — paste an `aiagent@alisadikinma.com` test send into the test address, target score 8+/10

### 3. Hostinger sending limits — IMPORTANT

Hostinger shared hosting plans cap email sends:
- **Standard plan**: ~100 emails/hour, 500-1000/day
- **Premium plan**: ~300 emails/hour, 3000/day
- **Business plan**: higher

The newsletter cron sends one email per subscriber sequentially via the queue worker (`Mail::queue` chunks subscribers in batches of 100). At <100 subscribers this is fine. Above that, monitor for `RecipientsLimitExceeded` errors and add a `->throttle(50, 60)` to the queue or migrate to a transactional provider (Resend, Postmark, SES).

If a Friday batch hits the limit, the unsent portion goes to `failed_jobs` table — re-dispatch via `php artisan queue:retry all` after the limit window resets.

### 3. Run migrations + backfill (idempotent)

```bash
# Auto-run via deploy.sh on next push, but if running manually:
cd /var/www/Portfolio_v2/backend
php artisan migrate --force
php artisan newsletter:backfill-tokens   # one-shot, idempotent
```

The two new migrations (`2026_05_05_000001_add_lead_fields_to_newsletters` + `2026_05_05_000002_create_newsletter_sends_table`) ride along with `php artisan migrate --force` in `scripts/deploy.sh` step 3.

### 4. Verify systemd queue worker + cron scheduler are running

These are pre-existing per CLAUDE.md "VPS Background Process Setup" section. Newsletter system reuses them — no new infra. Verify:

```bash
sudo systemctl is-active portfolio-queue.service     # → active
crontab -u claudesn -l | grep schedule:run           # → returns the line
php artisan schedule:list | grep newsletter          # → shows Friday 09:00 entry
```

If any are missing, follow `scripts/systemd/README.md` install steps.

### 5. Smoke test in production after deploy

```bash
# 5a. Verify new public route accepts 3-field payload
curl -X POST https://alisadikinma.com/api/newsletter/subscribe \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"name":"Smoke Test","email":"smoke@yourdomain.com","whatsapp_number":"+628999999999","source":"blog_inline"}'
# Expect: {"success":true,"message":"Successfully subscribed..."}

# 5b. Verify token unsubscribe works
TOKEN=$(mysql -u ali -p portfolio_v2 -se "SELECT unsubscribe_token FROM newsletters WHERE email='smoke@yourdomain.com'")
curl -X POST https://alisadikinma.com/api/newsletter/unsubscribe-by-token \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d "{\"token\":\"$TOKEN\"}"
# Expect: {"success":true,"message":"Successfully unsubscribed."}
```

### 6. Operator-side admin smoke test

1. Visit https://alisadikinma.com/login → log in
2. Sidebar → click "Newsletter" → confirm `/admin/newsletter` loads, subscribers list visible
3. Click "📧 Preview next Friday's digest" → modal opens, HTML email renders inside iframe
4. Click "📤 Send test" (default: your own email) → wait ~2-5s → toast "Test digest sent" → check inbox
5. (Optional) Visit "Send History" tab → confirm new row with `triggered_by=test`

### 7. First Friday cron observation

On the first Friday after deploy at 09:00 WIB:
```bash
ssh claudesn@alisadikinma.com
tail -f /var/www/Portfolio_v2/backend/storage/logs/laravel.log
# Look for: "Queued N subscribers · M posts" log line
mysql -u ali -p portfolio_v2 -e "SELECT * FROM newsletter_sends ORDER BY id DESC LIMIT 1;"
# Should show status='sent' or 'skipped' (no posts that week)
```

## Rollback

If newsletter system breaks production:

```bash
# Disable cron without rollback (lowest blast radius)
ssh claudesn@alisadikinma.com
crontab -u claudesn -l > /tmp/cron.bak
crontab -u claudesn -l | grep -v "schedule:run" | crontab -u claudesn -
# Newsletter cron stops firing; everything else still works.
```

For full rollback:
1. Revert the merge commit on main (PR or `git revert <SHA>`)
2. Push → CI/CD redeploys previous version
3. Tables `newsletter_sends` + new columns on `newsletters` stay (no destructive rollback) — they're additive

## Known issues / open work

- **Open/click tracking**: NOT shipped (out of scope per design). Add via Resend webhook if needed once subscribers > 500.
- **Welcome email on subscribe**: NOT shipped. Only weekly digest in v1.
- **Multi-language email**: HTML template hardcoded English only. Add per-locale variants if subscriber base spans languages.
- **Filament Newsletter resource**: pre-existing `App\Filament\Admin\Resources\Newsletters\NewslettersResource` exists but uses old single-email schema. New 3-field admin lives at `/admin/newsletter` (Vue) — operator should use that, not Filament. Filament resource can be deleted in a follow-up.

## Files shipped

**Backend (16 files)**:
- `database/migrations/2026_05_05_000001_add_lead_fields_to_newsletters.php` (NEW)
- `database/migrations/2026_05_05_000002_create_newsletter_sends_table.php` (NEW)
- `database/seeders/MailSettingsSeeder.php` (NEW — 8 mail.* keys)
- `app/Models/Newsletter.php` (MODIFIED — fillables + token auto-gen)
- `app/Models/NewsletterSend.php` (NEW)
- `database/factories/NewsletterFactory.php` (REPLACED — was empty stub)
- `app/Http/Controllers/Api/NewsletterController.php` (MODIFIED — 3-field validation + token unsubscribe)
- `app/Http/Controllers/Api/Admin/NewsletterAdminController.php` (NEW)
- `app/Http/Controllers/Api/SettingsController.php` (MODIFIED — getMailSettings + updateMailSettings + testMailConnection)
- `app/Mail/WeeklyDigest.php` (NEW)
- `app/Providers/MailConfigOverrideProvider.php` (NEW — DB→config bridge at boot)
- `bootstrap/providers.php` (MODIFIED — register MailConfigOverrideProvider)
- `resources/views/emails/weekly-digest.blade.php` (NEW)
- `resources/views/emails/weekly-digest-text.blade.php` (NEW)
- `app/Console/Commands/SendWeeklyNewsletter.php` (NEW)
- `app/Console/Commands/BackfillNewsletterTokens.php` (NEW)
- `routes/api.php` (MODIFIED — new public route + admin newsletter group + admin settings/mail routes)
- `routes/console.php` (MODIFIED — Friday 09:00 WIB schedule entry)

**Frontend (12 files)**:
- `src/composables/useNewsletter.js` (REPLACED — widened signature)
- `src/composables/useNewsletterAdmin.js` (NEW — TanStack Query)
- `src/stores/settings.js` (MODIFIED — mailSettings state + 3 actions)
- `src/components/blog/NewsletterInlineCard.vue` (REPLACED — 3-field form)
- `src/components/blog/NewsletterFloatingBanner.vue` (REPLACED — 3-field form)
- `src/components/blog/NewsletterFooterBar.vue` (REPLACED — modal-on-click pattern)
- `src/components/blog/NewsletterModal.vue` (NEW)
- `src/components/home/CTASection.vue` (MODIFIED — placeholder cleanup)
- `src/views/Blog.vue` (MODIFIED — inline section to 3 fields)
- `src/views/NewsletterUnsubscribe.vue` (NEW — public unsubscribe page)
- `src/views/admin/NewsletterSubscribers.vue` (NEW — admin view)
- `src/views/admin/AboutSettings.vue` (MODIFIED — Email/SMTP card + handlers)
- `src/router/index.js` (MODIFIED — 2 new routes)
- `src/layouts/AdminLayout.vue` (MODIFIED — sidebar Newsletter entry)

**Docs**:
- `docs/plans/2026-05-05-newsletter-system.md` (design)
- `docs/plans/2026-05-05-newsletter-system-plan.md` (implementation plan)
- `docs/runbooks/newsletter-deploy.md` (this file)
