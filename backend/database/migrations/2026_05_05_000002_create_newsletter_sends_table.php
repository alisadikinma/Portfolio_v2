<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('newsletter_sends', function (Blueprint $table) {
            $table->id();
            $table->timestamp('sent_at')->nullable()->index('idx_newsletter_sends_sent_at');
            $table->unsignedInteger('subscriber_count')->default(0);
            $table->unsignedInteger('posts_count')->default(0);
            $table->json('post_ids')->nullable();
            $table->enum('status', ['sent', 'failed', 'skipped', 'partial'])->default('sent');
            $table->text('error_message')->nullable();
            $table->enum('triggered_by', ['cron', 'manual', 'test'])->default('cron');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('test_recipient', 255)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_sends');
    }
};
