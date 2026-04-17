<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_ideas', function (Blueprint $table) {
            $table->unsignedTinyInteger('pipeline_attempts')->default(0)->after('auto_mode');
            $table->timestamp('pipeline_last_attempt_at')->nullable()->after('pipeline_attempts');
            $table->timestamp('pipeline_next_retry_at')->nullable()->after('pipeline_last_attempt_at');
            $table->string('pipeline_failed_stage', 32)->nullable()->after('pipeline_next_retry_at');

            $table->index(
                ['auto_mode', 'status', 'pipeline_next_retry_at'],
                'idx_auto_pipeline_scan'
            );
        });
    }

    public function down(): void
    {
        Schema::table('content_ideas', function (Blueprint $table) {
            $table->dropIndex('idx_auto_pipeline_scan');
            $table->dropColumn([
                'pipeline_attempts',
                'pipeline_last_attempt_at',
                'pipeline_next_retry_at',
                'pipeline_failed_stage',
            ]);
        });
    }
};
