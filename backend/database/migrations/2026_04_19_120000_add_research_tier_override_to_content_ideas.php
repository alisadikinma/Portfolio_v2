<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_ideas', function (Blueprint $table) {
            $table->enum('research_tier_override', ['auto', 'quick', 'deep'])
                ->default('auto')
                ->after('mechanical_scores_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('content_ideas', function (Blueprint $table) {
            $table->dropColumn('research_tier_override');
        });
    }
};
