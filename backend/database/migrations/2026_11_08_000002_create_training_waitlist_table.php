<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('training_waitlist', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('company', 160)->nullable();
            $table->string('email', 190)->index();
            $table->string('whatsapp_number', 20)->nullable();
            $table->enum('interest', ['inhouse', 'workshop', 'public_class']);
            $table->text('note')->nullable();
            $table->string('source_page', 120);
            $table->enum('locale', ['id', 'en']);
            $table->boolean('newsletter_opt_in')->default(false);
            $table->enum('status', ['new', 'contacted', 'cancelled'])->default('new')->index();
            $table->text('admin_note')->nullable();
            $table->uuid('client_key')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_waitlist');
    }
};
