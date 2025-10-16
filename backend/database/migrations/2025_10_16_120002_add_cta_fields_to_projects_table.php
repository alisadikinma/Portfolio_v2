<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('cta_title', 255)->nullable()->after('github_url');        // "Transform Your Business"
            $table->text('cta_description')->nullable()->after('cta_title');          // "See how this solution can..."
            $table->string('cta_button_text', 100)->nullable()->after('cta_description'); // "Get Started", "Contact Us"
            $table->string('cta_phone_number', 20)->nullable()->after('cta_button_text'); // "+62-812-xxxx" atau email
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['cta_title', 'cta_description', 'cta_button_text', 'cta_phone_number']);
        });
    }
};
