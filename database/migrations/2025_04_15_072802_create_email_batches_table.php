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
        Schema::create('email_batches', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable()->maxLength(255);
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('smtp_configuration_id')->constrained()->onDelete('cascade');
            $table->string('csv_file_path');
            $table->string('email_to');
            $table->string('email_cc')->nullable();
            $table->string('email_bcc')->nullable();
            $table->text('email_subject');
            $table->longText('email_body');
            $table->json('attachment_paths')->nullable();
            $table->boolean('has_personalized_attachments')->default(false);
            $table->json('data_headers')->nullable();
            $table->json('data_rows')->nullable();
            $table->enum('status', ['draft', 'generating', 'generated', 'sending', 'sent', 'failed'])->default('pending');
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('generated_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->boolean('tracking_enabled')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_batches');
    }
};
