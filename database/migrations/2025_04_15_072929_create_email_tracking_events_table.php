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
        Schema::create('email_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_batch_id')->constrained()->onDelete('cascade'); // Foreign key to email_batches [cite: 218]
            $table->string('recipient_identifier'); // Unique identifier for the recipient [cite: 218]
            $table->enum('type', ['open', 'click']); // Event type [cite: 219]
            $table->timestamp('tracked_at')->useCurrent(); // Time of event [cite: 219]
            $table->ipAddress('ip_address')->nullable(); // User IP address [cite: 220]
            $table->text('user_agent')->nullable(); // User agent string [cite: 220]
            $table->text('link_url')->nullable(); // Clicked URL (for clicks) [cite: 221]
            $table->timestamps(); // created_at, updated_at [cite: 222]

            //$table->index(['email_batch_id', 'recipient_identifier', 'type']); // Index for faster lookups
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_tracking_events');
    }
};
