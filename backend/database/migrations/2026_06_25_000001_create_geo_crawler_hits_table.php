<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GEO crawler-hit log (2026-06-25) — Pillar 5 measurement.
 *
 * AI bot crawls (GPTBot, ClaudeBot, PerplexityBot, etc.) never execute JS and
 * carry no referrer, so GA4 can never see them. The LogAiCrawler web middleware
 * records each match here as a per-bot daily counter. Daily granularity keeps
 * the table tiny (one row per bot per day); the unique(date,bot) index makes
 * the increment an atomic upsert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_crawler_hits', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('bot', 40);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamps();

            $table->unique(['date', 'bot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_crawler_hits');
    }
};
