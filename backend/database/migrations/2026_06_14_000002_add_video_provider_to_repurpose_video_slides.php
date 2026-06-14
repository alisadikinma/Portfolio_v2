<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * video_rebrand bookend i2v: Veo-first → GROK-failover (2026-06-14).
 *
 * Veo 3.x is the DEFAULT generator (better quality) but hits two hard Google
 * walls on bookend clips: mandatory-audio → PUBLIC_ERROR_AUDIO_FILTERED
 * (nondeterministic) and a prominent-people block that refuses to animate a
 * recognizable celebrity in the source frame. GROK (xAI) clears both (audio is
 * stripped on download; xAI ≠ Google). This column records which provider a
 * bookend clip should use — flipped to 'grok' when a public figure is on the
 * keyframe (dispatch GROK directly) or when Veo fails audio/prominent-people
 * (failover). Default 'veo' keeps every existing + non-figure clip on Veo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repurpose_video_slides', function (Blueprint $table) {
            $table->string('video_provider', 16)->default('veo')->after('veo_status'); // veo | grok
        });
    }

    public function down(): void
    {
        Schema::table('repurpose_video_slides', function (Blueprint $table) {
            $table->dropColumn('video_provider');
        });
    }
};
