<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a dedicated `linkedin_url` column for the testimonial author's
 * personal LinkedIn profile.
 *
 * Distinct from `source_url` (which records WHERE the testimonial was
 * published — typically the recommendations page on Ali's profile).
 * `linkedin_url` is used by the homepage carousel to make each card
 * link to the author's own profile.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            if (!Schema::hasColumn('testimonials', 'linkedin_url')) {
                $table->string('linkedin_url')->nullable()->after('source_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            if (Schema::hasColumn('testimonials', 'linkedin_url')) {
                $table->dropColumn('linkedin_url');
            }
        });
    }
};
