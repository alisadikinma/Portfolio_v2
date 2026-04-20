<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_ideas', function (Blueprint $table) {
            $table->json('pipeline_state_log')->nullable()->after('pipeline_failed_stage');
            $table->unsignedTinyInteger('translation_attempts_auto')->default(0)->after('pipeline_state_log');
            $table->timestamp('translation_ready_at')->nullable()->after('translation_attempts_auto');
        });
    }

    public function down(): void
    {
        Schema::table('content_ideas', function (Blueprint $table) {
            $table->dropColumn([
                'pipeline_state_log',
                'translation_attempts_auto',
                'translation_ready_at',
            ]);
        });
    }
};
