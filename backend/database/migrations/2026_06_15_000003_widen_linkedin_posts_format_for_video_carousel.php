<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `linkedin_posts.format` was an ENUM('text','carousel'). The IG-video repurpose
 * calendar anchor needs a third value, `video_carousel`. Converting the column to a
 * plain string drops the DB-level allow-list (now enforced by
 * LinkedInPost::FORMAT_* constants + request validation) and is portable across
 * MySQL (was ENUM) and the sqlite test DB (was a varchar CHECK constraint) — both
 * would otherwise reject the new value. Future formats need no further migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('linkedin_posts', function (Blueprint $table) {
            $table->string('format')->change();
        });
    }

    public function down(): void
    {
        // Best-effort revert: any video_carousel rows are remapped to carousel so the
        // narrowed enum doesn't reject them. Irreversible loss of the distinction is
        // acceptable on a rollback (the feature is being removed).
        \Illuminate\Support\Facades\DB::table('linkedin_posts')
            ->where('format', 'video_carousel')
            ->update(['format' => 'carousel']);

        if (\Illuminate\Support\Facades\DB::getDriverName() === 'mysql') {
            \Illuminate\Support\Facades\DB::statement(
                "ALTER TABLE linkedin_posts MODIFY COLUMN format ENUM('text','carousel') NOT NULL"
            );
        } else {
            Schema::table('linkedin_posts', function (Blueprint $table) {
                $table->string('format')->change();
            });
        }
    }
};
