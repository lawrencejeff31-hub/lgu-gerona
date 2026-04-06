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
        // Drop the incorrect q_r_codes table if it exists
        if (Schema::hasTable('q_r_codes') && !Schema::hasColumn('q_r_codes', 'document_id')) {
            Schema::dropIfExists('q_r_codes');
        }

        // Ensure the correct qr_codes table exists with proper structure
        if (!Schema::hasTable('qr_codes')) {
            Schema::create('qr_codes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_id')->constrained('documents')->onDelete('cascade');
                $table->string('token')->unique();
                $table->string('qr_image_path')->nullable();
                $table->integer('scan_count')->default(0);
                $table->timestamp('last_scanned_at')->nullable();
                $table->timestamps();
            });
        } else {
            // If table exists, ensure it has all required columns
            Schema::table('qr_codes', function (Blueprint $table) {
                if (!Schema::hasColumn('qr_codes', 'document_id')) {
                    $table->foreignId('document_id')->constrained('documents')->onDelete('cascade');
                }
                if (!Schema::hasColumn('qr_codes', 'token')) {
                    $table->string('token')->unique();
                }
                if (!Schema::hasColumn('qr_codes', 'qr_image_path')) {
                    $table->string('qr_image_path')->nullable();
                }
                if (!Schema::hasColumn('qr_codes', 'scan_count')) {
                    $table->integer('scan_count')->default(0);
                }
                if (!Schema::hasColumn('qr_codes', 'last_scanned_at')) {
                    $table->timestamp('last_scanned_at')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't drop the table in down method as it might contain data
        // Schema::dropIfExists('qr_codes');
    }
};