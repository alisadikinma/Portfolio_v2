<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_sections', function (Blueprint $table) {
            $table->string('title')->nullable()->after('section_type');
            $table->text('description')->nullable()->after('title');
            $table->string('video_url')->nullable()->after('description');
            $table->json('content')->nullable()->after('video_url');
        });

        // Seed 9 homepage sections
        $sections = [
            ['page_type' => 'homepage', 'section_type' => 'hero', 'title' => 'Hero', 'is_active' => true, 'sequence' => 1],
            ['page_type' => 'homepage', 'section_type' => 'skills-reel', 'title' => 'Skills Reel', 'is_active' => true, 'sequence' => 2],
            ['page_type' => 'homepage', 'section_type' => 'skill-vibe-coding', 'title' => 'Vibe Coding', 'description' => 'Build production apps with AI-powered development workflows using Claude Code, Cursor, and custom plugins.', 'video_url' => '/videos/vibe-coding.mp4', 'is_active' => true, 'sequence' => 3],
            ['page_type' => 'homepage', 'section_type' => 'skill-ai-automation', 'title' => 'AI Automation', 'description' => 'Design and deploy intelligent automation pipelines using n8n, Zapier, and custom API orchestration.', 'video_url' => '/videos/ai-automation.mp4', 'is_active' => true, 'sequence' => 4],
            ['page_type' => 'homepage', 'section_type' => 'skill-ai-agents', 'title' => 'AI Agents', 'description' => 'Build autonomous AI agents with OpenClaw, Claude API, and multi-agent architectures for complex task execution.', 'video_url' => '/videos/ai-agents.mp4', 'is_active' => true, 'sequence' => 5],
            ['page_type' => 'homepage', 'section_type' => 'skill-ai-video', 'title' => 'AI Video Generation', 'description' => 'Produce cinematic video content using VEO 3.1, Kling AI, and custom prompt engineering pipelines.', 'video_url' => '/videos/ai-video.mp4', 'is_active' => true, 'sequence' => 6],
            ['page_type' => 'homepage', 'section_type' => 'featured-projects', 'title' => 'Featured Projects', 'is_active' => true, 'sequence' => 7],
            ['page_type' => 'homepage', 'section_type' => 'latest-blog', 'title' => 'Latest Blog', 'is_active' => true, 'sequence' => 8],
            ['page_type' => 'homepage', 'section_type' => 'stats-cta', 'title' => 'Stats & CTA', 'is_active' => true, 'sequence' => 9],
        ];

        foreach ($sections as $section) {
            DB::table('page_sections')->updateOrInsert(
                ['page_type' => $section['page_type'], 'section_type' => $section['section_type']],
                array_merge($section, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    public function down(): void
    {
        // Remove seeded sections
        DB::table('page_sections')->where('page_type', 'homepage')->whereIn('section_type', [
            'hero', 'skills-reel', 'skill-vibe-coding', 'skill-ai-automation',
            'skill-ai-agents', 'skill-ai-video', 'featured-projects', 'latest-blog', 'stats-cta'
        ])->delete();

        Schema::table('page_sections', function (Blueprint $table) {
            $table->dropColumn(['title', 'description', 'video_url', 'content']);
        });
    }
};
