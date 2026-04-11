<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('platform');
            $table->string('platform_user_id');
            $table->string('username')->nullable();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamps();
            $table->unique(['platform', 'platform_user_id']);
        });

        Schema::create('carousel_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('source_url')->nullable();
            $table->string('shortcode')->nullable();
            $table->json('source_images')->nullable();
            $table->json('source_analysis')->nullable();
            $table->text('creative_direction')->nullable();
            $table->enum('status', [
                'scraping', 'analyzing', 'generating', 'draft',
                'approved', 'scheduled', 'posted', 'failed'
            ])->default('draft');
            $table->string('target_platform')->default('instagram');
            $table->string('hook_category')->nullable();
            $table->json('captions')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->json('post_result')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('carousel_slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carousel_draft_id')
                ->constrained('carousel_drafts')
                ->cascadeOnDelete();
            $table->integer('slide_index');
            $table->enum('slide_type', [
                'hook', 'foreshadow', 'body', 'peak', 'cta'
            ])->default('body');
            $table->text('source_image_url')->nullable();
            $table->text('prompt')->nullable();
            $table->text('generated_image_url')->nullable();
            $table->string('generation_uuid')->nullable();
            $table->enum('generation_status', [
                'pending', 'processing', 'completed', 'failed'
            ])->default('pending');
            $table->json('wow_score')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carousel_slides');
        Schema::dropIfExists('carousel_drafts');
        Schema::dropIfExists('social_accounts');
    }
};
