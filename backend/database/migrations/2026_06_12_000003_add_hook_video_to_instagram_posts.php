<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GROK hook video (June 12, 2026) — Instagram mixed carousel.
 *
 * Each IG cross-post draft gets a GROK image-to-video animation of its hook
 * slide (slide 1): the creator moves subtly + the floating topic icons drift.
 * Instagram (unlike LinkedIn) can mix video+image in ONE carousel, so the hook
 * ships as video item 1 + the remaining slides as images.
 *
 * Completion is POLL-driven (GeminiGen never fires webhooks — see the
 * blog:process-images precedent) — hook_video_job_uuid is the GROK job uuid the
 * poller hits at /uapi/v1/history/{uuid}.
 *   - hook_video_url      final 4:5 MP4 (audio stripped); null until done
 *   - hook_video_status   pending | generating | done | failed (null = not requested)
 *   - hook_video_job_uuid GROK poll key (indexed)
 *   - hook_video_error    failure reason surfaced to operator / regenerate
 *
 * All nullable — IG siblings created before this feature stay valid (all-image).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('instagram_posts')) {
            return;
        }

        Schema::table('instagram_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('instagram_posts', 'hook_video_url')) {
                $table->string('hook_video_url', 500)->nullable()->after('external_url')
                    ->comment('Final 4:5 GROK hook MP4 (audio stripped); null until done');
            }
            if (! Schema::hasColumn('instagram_posts', 'hook_video_status')) {
                $table->string('hook_video_status', 16)->nullable()->after('hook_video_url')
                    ->comment('pending|generating|done|failed; null = no hook video requested');
            }
            if (! Schema::hasColumn('instagram_posts', 'hook_video_job_uuid')) {
                $table->string('hook_video_job_uuid', 64)->nullable()->after('hook_video_status')
                    ->comment('GROK job uuid — poll key at /uapi/v1/history/{uuid}');
                $table->index('hook_video_job_uuid', 'idx_instagram_hook_video_uuid');
            }
            if (! Schema::hasColumn('instagram_posts', 'hook_video_error')) {
                $table->text('hook_video_error')->nullable()->after('hook_video_job_uuid');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('instagram_posts')) {
            return;
        }

        Schema::table('instagram_posts', function (Blueprint $table) {
            // Single-column index drops with its column on MySQL; sqlite rebuilds
            // the table on dropColumn. Guard each so down() is idempotent.
            $cols = array_values(array_filter(
                ['hook_video_url', 'hook_video_status', 'hook_video_job_uuid', 'hook_video_error'],
                fn ($c) => Schema::hasColumn('instagram_posts', $c)
            ));
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
