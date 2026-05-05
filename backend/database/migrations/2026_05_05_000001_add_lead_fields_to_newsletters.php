<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('newsletters', function (Blueprint $table) {
            $table->string('name', 120)->nullable()->after('email');
            $table->string('whatsapp_number', 20)->nullable()->after('name');
            $table->char('unsubscribe_token', 32)->nullable()->after('whatsapp_number');
            $table->timestamp('consent_given_at')->nullable()->after('unsubscribe_token');
            $table->string('source', 40)->nullable()->after('consent_given_at');

            $table->unique('unsubscribe_token', 'idx_newsletters_unsubscribe_token');
            $table->unique('whatsapp_number', 'idx_newsletters_whatsapp_number');
        });
    }

    public function down(): void
    {
        Schema::table('newsletters', function (Blueprint $table) {
            $table->dropUnique('idx_newsletters_unsubscribe_token');
            $table->dropUnique('idx_newsletters_whatsapp_number');
            $table->dropColumn([
                'name',
                'whatsapp_number',
                'unsubscribe_token',
                'consent_given_at',
                'source',
            ]);
        });
    }
};
