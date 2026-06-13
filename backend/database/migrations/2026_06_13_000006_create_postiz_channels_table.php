<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Postiz channel mapping — platform → Postiz integration_id, auto-synced by the
 * local poller (GET /public/v1/integrations → POST /automation/postiz/channels/sync).
 * See docs/plans/2026-06-13-postiz-local-node-crosspost.md (Phase A / E).
 *
 * At publish time the VPS resolves platform→integration_id from this table and
 * sends it in the `pending` payload; the local poller stays dumb (forwards the ID).
 * Channels that disappear from Postiz are flipped enabled=false (audit), not deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postiz_channels', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 32);                 // identifier from Postiz (instagram, linkedin, ...)
            $table->string('handle');                       // profile/handle from Postiz
            $table->string('postiz_integration_id');        // Postiz integration id used in POST /posts
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'handle'], 'uniq_postiz_channel_platform_handle');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postiz_channels');
    }
};
