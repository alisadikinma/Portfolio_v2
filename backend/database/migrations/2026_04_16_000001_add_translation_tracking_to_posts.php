<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->unsignedBigInteger('source_idea_id')->nullable()->after('id');
            $table->index('source_idea_id');
            $table->boolean('translation_pending')->default(false)->after('published_at');
            $table->unsignedTinyInteger('translation_attempts')->default(0)->after('translation_pending');
            $table->timestamp('last_translation_attempt')->nullable()->after('translation_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['source_idea_id']);
            $table->dropColumn([
                'source_idea_id',
                'translation_pending',
                'translation_attempts',
                'last_translation_attempt',
            ]);
        });
    }
};
