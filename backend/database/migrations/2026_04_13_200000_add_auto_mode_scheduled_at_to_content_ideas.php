<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_ideas', function (Blueprint $table) {
            $table->boolean('auto_mode')->default(false)->after('priority');
            $table->timestamp('scheduled_at')->nullable()->after('auto_mode');
        });
    }

    public function down(): void
    {
        Schema::table('content_ideas', function (Blueprint $table) {
            $table->dropColumn(['auto_mode', 'scheduled_at']);
        });
    }
};
