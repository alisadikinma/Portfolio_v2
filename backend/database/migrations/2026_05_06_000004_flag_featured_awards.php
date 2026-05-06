<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data-only migration: flag historically-significant awards as featured.
 *
 * Background: `awards.is_featured` was added 2026-05-04 but no rows were
 * back-flagged at the time. CV export ordering relies on this column
 * (`orderByDesc('is_featured')`) — without seeded values, ordering is
 * effectively undefined. Flagging the obvious flagship rows ("Demo Day
 * Champion", "1st Place Winner") unblocks consumer ranking in jobhunter.
 *
 * Idempotent: safe to re-run. Only flips `is_featured` to true on matched
 * rows; never touches non-matched rows or sets back to false.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::table('awards')
            ->where(function ($q) {
                $q->where('title', 'like', '%Demo Day%')
                  ->orWhere('title', 'like', '%1st Place%')
                  ->orWhere('title', 'like', '%Champion%')
                  ->orWhere('title', 'like', '%Winner%');
            })
            ->update(['is_featured' => true]);
    }

    public function down(): void
    {
        // Reverse pass exists for rollback symmetry, but operators rarely
        // want to un-feature these by accident. Mirror the up() filter.
        DB::table('awards')
            ->where(function ($q) {
                $q->where('title', 'like', '%Demo Day%')
                  ->orWhere('title', 'like', '%1st Place%')
                  ->orWhere('title', 'like', '%Champion%')
                  ->orWhere('title', 'like', '%Winner%');
            })
            ->update(['is_featured' => false]);
    }
};
