<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_references', function (Blueprint $table) {
            $table->id();

            // Wikidata Q-ID. Null for user-uploaded entities (qid resolution failed
            // or entity is private — still cache by name so subsequent articles
            // about the same subject reuse the upload).
            $table->string('qid', 20)->nullable()->unique();

            $table->string('name');

            $table->enum('entity_type', ['person', 'landmark', 'logo', 'product']);

            // Relative path under storage disk (e.g. "entity-refs/person/Q..._slug.jpg")
            $table->string('local_path', 500);

            // Public URL served by Laravel (via url('/storage/...')). Stored as full
            // URL to match the project's convention — see CLAUDE.md "Image URLs".
            $table->string('local_url', 500);

            $table->text('wikimedia_source_url')->nullable();

            // Commons LicenseShortName value: CC0, PD, PD-USGov, CC-BY-4.0, or
            // USER-UPLOADED for admin-supplied files.
            $table->string('license', 50);

            $table->text('attribution')->nullable();

            $table->enum('source', ['wikimedia', 'user_upload'])->default('wikimedia');

            $table->timestamp('fetched_at');
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedInteger('use_count')->default(1);

            // Admin-controlled re-fetch trigger. Null = never expire.
            $table->timestamp('refresh_after')->nullable();

            $table->timestamps();

            $table->index('name');
            $table->index(['entity_type', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_references');
    }
};
