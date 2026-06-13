<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * video_rebrand quality pass (#4) — correct the stored creator_brand_slug when it
 * is the literal placeholder. The render path is already hardened (CreatorHandle
 * treats placeholder slugs as unset), but fixing the data too keeps the admin UI
 * + any other creator_brand_slug consumer (e.g. filename prefixes) correct.
 *
 * Idempotent + safe: ONLY rewrites a known placeholder value, never a legitimate
 * custom slug. Re-running finds no placeholder → no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->where('group', 'creator_brand')
            ->where('key', 'creator_brand_slug')
            ->whereIn('value', ['creator-brand', 'creator_brand', ''])
            ->update(['value' => 'alisadikinma']);
    }

    public function down(): void
    {
        // No-op — the prior placeholder value is not worth restoring.
    }
};
