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
        // This migration is redundant - the qr_codes table is already created
        // by the 2025_09_29_040206_create_qr_codes_table.php migration
        // Skip creating the table if it already exists
        if (!Schema::hasTable('q_r_codes')) {
            Schema::create('q_r_codes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_id')->constrained('documents');
                $table->string('token')->unique();
                $table->string('qr_image_path')->nullable();
                $table->integer('scan_count')->default(0);
                $table->timestamp('last_scanned_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('q_r_codes');
    }
};
