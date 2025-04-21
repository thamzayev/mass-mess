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
        Schema::create('personalized_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_batch_id')->constrained()->onDelete('cascade'); // Foreign key to email_batches [cite: 206]
            $table->string('recipient_identifier'); // Unique identifier for the recipient [cite: 207]
            $table->string('file_path'); // Path to generated PDF [cite: 208]
            $table->string('original_name')->nullable(); // Original attachment name [cite: 209]
            $table->timestamps(); // created_at, updated_at [cite: 210]

            //$table->index(['email_batch_id', 'recipient_identifier']); // Index for faster lookups
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personalized_attachments');
    }
};
