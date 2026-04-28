# Phase A7 — VPS Deploy Guide (Operator Manual Step)

**Date:** 2026-04-28
**Phase:** A7 (final Phase A step, before Phase B cutover)
**Operator:** Ali Sadikin (you)
**Estimated time:** 4-6 minutes
**Risk:** LOW (feature flag stays OFF — no production impact until Phase B)

---

## Pre-flight Checks (local, before SSH)

```bash
# 1. Ensure local repo is at HEAD with all Phase A commits
cd D:\Projects\Portfolio_v2
git log --oneline -8
# Expected: 8b33ca39 (A6 router), 64013f94 (A4 fix), b493f66f (A4),
#           6430e1cc (A5 fix), 491a1a01 (A5), and ai-image-carousel-prompt-gen
#           plugin commits visible in that repo

cd D:\Projects\claude-plugin\ai-image-carousel-prompt-gen
git log --oneline -6
# Expected: a799686 (A3), eda38fe (A2 fix), 4d9a7af (A2), abf42d8 (A1 fix),
#           81c175f (A1), 6a53ce6 (pre-A1 baseline)

# 2. Build compiled refs locally — produces ~169KB bundle
npm run compile-refs
# Expected output: refs-carousel-gen-pipeline.md: 169,xxx bytes

ls -lh references/compiled/refs-carousel-gen-pipeline.md
# Verify ~169KB exists. This file is gitignored (build artifact).
```

If any of the above fails, STOP and reconcile before SSH. The compiled refs file is the load-bearing deliverable for A7.

---

## VPS Deploy Steps

> Adjust hostnames + paths to match your VPS. The defaults below assume
> the same VPS where `linkedin-post-writer` plugin already runs as the
> `claudesn` user.

### Step 1 — Copy compiled refs to VPS

From your Windows terminal (PowerShell or Git Bash):

```bash
# Replace VPS_HOST with your actual VPS hostname or IP
scp D:/Projects/claude-plugin/ai-image-carousel-prompt-gen/references/compiled/refs-carousel-gen-pipeline.md \
    claudesn@VPS_HOST:/home/claudesn/refs-carousel-gen-pipeline.md
```

Expected: 1 file transferred, ~169KB.

### Step 2 — Verify file landed correctly

```bash
ssh claudesn@VPS_HOST "ls -lh ~/refs-carousel-gen-pipeline.md && head -5 ~/refs-carousel-gen-pipeline.md"
```

Expected output:
- Size: ~169K, owned by claudesn
- First 5 lines start with `# Carousel-Gen Reference — Pipeline-mode bundle ...`

### Step 3 — Sync the plugin's pipeline-mode skill to VPS

The `/carousel-gen` skill is in the plugin marketplace cache on VPS.
You need the plugin at v3.0.0+ for pipeline-mode flags to be recognized
by `claude -p`. Two paths:

**Path A — Plugin marketplace pull (recommended, future):**

When `ai-image-carousel-prompt-gen` is published as v3.0.0 to the
plugin marketplace, run on VPS:

```bash
ssh claudesn@VPS_HOST
# As claudesn:
claude --upgrade-plugins  # or whatever the marketplace pull command is
exit
```

**Path B — Local file sync (this session, since plugin not yet published):**

```bash
# From local machine — sync entire plugin directory to VPS
rsync -avz --delete \
  --exclude=node_modules \
  --exclude=.git \
  D:/Projects/claude-plugin/ai-image-carousel-prompt-gen/ \
  claudesn@VPS_HOST:/home/claudesn/.claude/plugins/ai-image-carousel-prompt-gen/
```

Skip if you handle plugin distribution differently — adjust path to
match your VPS plugin cache location.

### Step 4 — Test SSH invocation as claudesn

```bash
ssh claudesn@VPS_HOST
# Now on VPS as claudesn user:

# Verify claude CLI sees the skill
claude -p "/carousel-gen --help" 2>&1 | head -20
```

Expected: skill help text mentions pipeline-mode flags
(`--pipeline`, `--blog-source`, `--bilingual`, `--narrative`,
`--target-slides`). If you see "skill not found" or no flags
documented, Step 3 didn't sync correctly — revisit Path A or B.

```bash
# Optional: smoke-test the bundle injection (do NOT run with real blog)
claude -p "/carousel-gen --pipeline --blog-source=https://alisadikinma.com/blog/test-only --bilingual=id,en --narrative=5act --target-slides=5" \
  --append-system-prompt-file ~/refs-carousel-gen-pipeline.md \
  --model sonnet \
  --dangerously-skip-permissions 2>&1 | head -30
```

This will likely fail because `https://alisadikinma.com/blog/test-only` doesn't exist as a real post — that's fine. What we're checking:
- Claude doesn't ask for `ref/` folder (Creator Identity gate suppression works)
- Claude attempts to run pipeline mode (no question prompts)
- Output format is JSON-shaped (even if status=failed, envelope structure correct)

```bash
exit  # leave VPS
```

### Step 5 — Set Laravel env vars on VPS

The backend needs the new `CAROUSEL_GEN_*` env vars active. Edit
production `.env` on VPS (do NOT commit `.env` — it's in `.gitignore`).

```bash
ssh claudesn@VPS_HOST
# As claudesn (or as the deploy user that owns Laravel):
cd /var/www/Portfolio_v2/backend  # adjust path if different
nano .env
```

Add or update these lines (defaults match `config/carousel-gen.php`):

```env
# Carousel Gen Plugin Bridge (Phase A — feature-flagged)
CAROUSEL_GEN_DRIVER=ssh
CAROUSEL_GEN_SSH_HOST=localhost
CAROUSEL_GEN_SSH_USER=claudesn
CAROUSEL_GEN_SSH_KEY=/var/www/.ssh/id_ed25519
CAROUSEL_GEN_CLAUDE_PATH=claude
CAROUSEL_GEN_MODEL=sonnet
CAROUSEL_GEN_REFS_PIPELINE=/home/claudesn/refs-carousel-gen-pipeline.md
CAROUSEL_GEN_TIMEOUT_SECONDS=600

# Phase A — feature flag KEEPS OFF for now. Phase B flips after smoke test.
LINKEDIN_USE_CAROUSEL_GEN_ENGINE=false
```

Save and exit nano (Ctrl+O, Enter, Ctrl+X).

### Step 6 — Reload Laravel config + restart queue worker

```bash
# Still on VPS, in /var/www/Portfolio_v2/backend:
php artisan config:clear
php artisan config:cache
php artisan queue:restart
```

The queue:restart causes any running queue workers to gracefully exit
after their current job and respawn with the new env. Crucial for
GenerateLinkedInPost workers to pick up the new env.

### Step 7 — Verify config integration

```bash
# Still on VPS, in /var/www/Portfolio_v2/backend:
php artisan tinker --execute="echo 'driver=' . config('carousel-gen.driver') . PHP_EOL;"
php artisan tinker --execute="echo 'timeout=' . config('carousel-gen.timeout_seconds') . PHP_EOL;"
php artisan tinker --execute="echo 'refs=' . config('carousel-gen.refs_pipeline') . PHP_EOL;"
php artisan tinker --execute="var_dump(config('linkedin.use_carousel_gen_engine'));"
```

Expected:
- `driver=ssh`
- `timeout=600`
- `refs=/home/claudesn/refs-carousel-gen-pipeline.md`
- `bool(false)` (flag is OFF — Phase A complete, Phase B not yet started)

```bash
exit  # leave VPS
```

---

## Acceptance Criteria

A7 is complete when ALL of these are true on production VPS:

- [ ] `~/refs-carousel-gen-pipeline.md` exists at ~169KB, owned by claudesn
- [ ] `claude -p "/carousel-gen --help"` shows pipeline-mode flags
- [ ] Laravel `.env` has all 9 new env vars (`CAROUSEL_GEN_*` + `LINKEDIN_USE_CAROUSEL_GEN_ENGINE`)
- [ ] Queue worker restarted with new env
- [ ] `config('carousel-gen.refs_pipeline')` resolves to the deployed file path
- [ ] `config('linkedin.use_carousel_gen_engine')` returns `false` (NOT yet flipped)
- [ ] No errors in `storage/logs/laravel.log` for the past hour

---

## What NOT to do in A7

- **Do NOT flip `LINKEDIN_USE_CAROUSEL_GEN_ENGINE=true`** yet. That is Phase B Step 1 (smoke test with one manual draft) followed by Phase B Step 2 (production cutover). A7 is plumbing only.
- **Do NOT run `php artisan migrate`** — A7 has no DB migrations.
- **Do NOT delete the old `LINKEDIN_GEN_REFS_CAROUSEL` env** — that lives in `linkedin.generation.refs_carousel` config and is consumed by the legacy `/linkedin-carousel` path which is still the production code path. Phase C cleanup will remove it.
- **Do NOT push code** — backend code already deployed via the GitHub Actions auto-deploy on `git push origin main` (which already happened for A4-A6 commits if you've pushed them, or will happen on next push).

---

## Rollback (if Step 4 SSH test fails)

If `claude -p "/carousel-gen --help"` fails on VPS:

1. Likely cause: plugin not yet sync'd to VPS plugin cache (Step 3 incomplete)
2. Diagnose: `ssh claudesn@VPS_HOST "ls -la ~/.claude/plugins/"` — confirm `ai-image-carousel-prompt-gen/` directory exists
3. Re-run rsync from Step 3 Path B
4. Retest Step 4

If Laravel `.env` change breaks something (it shouldn't, since flag stays OFF):
1. Restore previous `.env` from `.env.backup` (always backup before edit)
2. `php artisan config:clear && php artisan config:cache && php artisan queue:restart`

---

## What's Next (Phase B — Cutover, separate task)

After A7 acceptance criteria met, Phase B begins. Phase B is its own
multi-step procedure with 7-day monitoring. See main plan:
[docs/plans/2026-04-28-linkedin-carousel-engine-decoupling.md](2026-04-28-linkedin-carousel-engine-decoupling.md)
under `## Implementation Plan → Phase B — Cutover`.

Quick reference for Phase B:
- B1: Smoke test with one manual draft (env flag set per-worker only)
- B2: Flip `LINKEDIN_USE_CAROUSEL_GEN_ENGINE=true` globally on VPS
- B3: 7-day monitoring window (Telegram alerts, FSM state log audit)
- B4: Update CLAUDE.md + PROJECT_STATUS.md
- B5: Rollback procedure (~5 min flag-flip back to false)

---

## Operator Notes Field

Use this section when running A7 to capture timestamps + observations:

```
[YYYY-MM-DD HH:MM] Pre-flight: compiled refs built locally, X bytes
[YYYY-MM-DD HH:MM] Step 1: scp completed, file size on VPS confirmed
[YYYY-MM-DD HH:MM] Step 4: claude -p smoke test result: PASS / FAIL ___
[YYYY-MM-DD HH:MM] Step 5: .env updated, backup at .env.backup-YYYYMMDD
[YYYY-MM-DD HH:MM] Step 7: all 4 config checks pass
[YYYY-MM-DD HH:MM] A7 COMPLETE — Phase A FULLY SHIPPED
```
