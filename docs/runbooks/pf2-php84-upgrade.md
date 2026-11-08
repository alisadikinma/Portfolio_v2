# PF-2 release runbook — PHP 8.4 + data repair

Phase 0C fills the PHP 8.4 part later. This file currently holds the data repair for Phase 0D.

## Repair WIB-stored schedule times

Why: before the fix, schedule timestamps written from an `Asia/Jakarta` Carbon were stored as the WIB
wall-clock in a UTC app, so those posts published 7 hours late. The code fix
(`StoresDatesInAppTimezone`) stops new bad rows. This command repairs rows that are still pending.

Run AFTER the web release is deployed (the fix must be live first, or new schedules keep landing wrong).

1. Dry-run. It writes nothing:
   `php artisan linkedin:repair-wib-schedules --dry-run`
   Output: `id | raw | intended WIB | action`. Actions: `would fix`, `ok`, `skip: no logged publish_at`,
   `skip: differs by Ns`.
2. Review the list with Ali. Only `would fix` rows change (raw `scheduled_at` is exactly 7 h after the
   logged `publish_at`). `skip` rows are never touched; Ali decides each by hand.
3. Real run, with Ali watching:
   `php artisan linkedin:repair-wib-schedules`
   It fixes `scheduled_at` + `cancel_window_ends_at` on the draft and `scheduled_at` on unpublished
   siblings (Facebook, Instagram, TikTok, Threads, Reddit).
4. Verify: rerun with `--dry-run`. Fixed rows now show `ok`.

Notes:
- Published rows are ignored. The command is manual only; it is not scheduled.
- Rollback: none needed for skipped rows. For a wrong fix, set the raw value back with SQL from the dry-run output.
- Caveat: the truth source is `publish_at` inside `pipeline_state_log`. Check the dry-run first: rows
  without it are only listed.
