<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_ideas', function (Blueprint $table) {
            $table->unsignedTinyInteger('virality_score')->nullable()->after('source_data');
            $table->json('virality_breakdown')->nullable()->after('virality_score');
        });
    }

    public function down(): void
    {
        Schema::table('content_ideas', function (Blueprint $table) {
            $table->dropColumn(['virality_score', 'virality_breakdown']);
        });
    }
};
