<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_ideas', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress_percentage')->default(0)->after('status');
            $table->string('current_step', 50)->nullable()->after('progress_percentage');
            $table->json('progress_log')->nullable()->after('current_step');
            $table->unsignedInteger('process_pid')->nullable()->after('progress_log');
        });
    }

    public function down(): void
    {
        Schema::table('content_ideas', function (Blueprint $table) {
            $table->dropColumn(['progress_percentage', 'current_step', 'progress_log', 'process_pid']);
        });
    }
};
