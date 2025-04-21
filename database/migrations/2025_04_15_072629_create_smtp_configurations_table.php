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
        Schema::create('smtp_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Foreign key to users [cite: 120]
            $table->string('name'); // Descriptive name [cite: 121]
            $table->string('host'); // SMTP host [cite: 122]
            $table->integer('port'); // SMTP port [cite: 122]
            $table->string('username')->nullable(); // SMTP username [cite: 123]
            $table->string('password')->nullable(); // SMTP password (consider encryption) [cite: 123, 124]
            $table->enum('encryption', ['tls', 'ssl'])->nullable(); // Encryption type [cite: 125]
            $table->string('from_address'); // Default From address [cite: 126]
            $table->string('from_name')->nullable(); // Default From name [cite: 127]
            $table->timestamps(); // created_at, updated_at [cite: 128]
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('smtp_configurations');
    }
};
