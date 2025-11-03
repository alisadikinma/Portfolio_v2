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
            // Domain/category for filtering (e.g., "AI Automation", "Web Development", "Mobile Apps", "IoT Solutions")
            $table->string('domain', 100)->nullable()->after('category')->index();

            // Business impact one-liner (e.g., "Saved $318K annually through intelligent automation")
            $table->string('impact_statement', 255)->nullable()->after('description');

            // Project template fields for professional portfolio showcase
            // Context: Project background/why it was needed
            $table->text('context')->nullable()->after('content');

            // Role: Your specific role in the project (e.g., "Lead Full-Stack Developer", "Technical Architect")
            $table->string('role', 100)->nullable()->after('context');

            // Problem: Business problem that needed to be solved
            $table->text('problem')->nullable()->after('role');

            // Solution: Technical solution implemented
            $table->text('solution')->nullable()->after('problem');

            // Integration: Systems/technologies integrated
            $table->text('integration')->nullable()->after('solution');

            // Result: Measurable outcomes and business impact
            $table->text('result')->nullable()->after('integration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'domain',
                'impact_statement',
                'context',
                'role',
                'problem',
                'solution',
                'integration',
                'result'
            ]);
        });
    }
};
