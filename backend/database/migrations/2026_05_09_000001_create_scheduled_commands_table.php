<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase A — admin scheduler tab foundation.
 *
 * Authoritative DB-backed mirror of routes/console.php schedule entries.
 * Each row represents ONE artisan command (or placeholder slot for a future
 * command). The Vue admin tab reads/writes this table; a kernel hook reads
 * `enabled=true AND is_placeholder=false` rows to materialize the schedule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_commands', function (Blueprint $table) {
            $table->id();

            // Identity
            $table->string('signature')->unique();           // e.g. "linkedin:auto-schedule"
            $table->string('display_name');                  // e.g. "LinkedIn — Auto-schedule manual review"
            $table->text('description')->nullable();

            // Grouping
            $table->enum('category', [
                'content_engine',
                'linkedin',
                'instagram',
                'facebook',
                'tiktok',
                'newsletter',
                'system',
            ])->index();

            // Cron config
            $table->string('cron_expression', 64);            // e.g. "0 3 * * *"
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->boolean('enabled')->default(true)->index();
            $table->boolean('is_placeholder')->default(false);

            // Execution flags
            $table->unsignedSmallInteger('without_overlapping_minutes')->nullable();
            $table->boolean('run_in_background')->default(false);
            $table->json('arguments')->nullable();           // CLI flags as JSON array

            // Last-run telemetry
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('last_finished_at')->nullable();
            $table->enum('last_status', ['success', 'failed', 'running', 'never'])
                  ->default('never');
            $table->unsignedInteger('last_duration_ms')->nullable();
            $table->text('last_error')->nullable();

            // Display ordering
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            // Composite index for category-grouped, sort-ordered listing
            $table->index(['category', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_commands');
    }
};
