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
            $table->foreignId('email_batch_id')->constrained()->onDelete('cascade');
            $table->text('header')->nullable();
            $table->longText('template');
            $table->text('footer')->nullable();
            $table->string('filename');
            $table->timestamps();

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
