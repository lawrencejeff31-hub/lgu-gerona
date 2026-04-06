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
        Schema::table('document_logs', function (Blueprint $table) {
            // Drop existing foreign key if present, then make column nullable and re-add FK with set null on delete
            try {
                $table->dropForeign(['user_id']);
            } catch (\Throwable $e) {
                // Foreign key may not exist in some environments; proceed to change column
            }

            // Make user_id nullable
            $table->unsignedBigInteger('user_id')->nullable()->change();

            // Re-add foreign key with set null on delete
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_logs', function (Blueprint $table) {
            try {
                $table->dropForeign(['user_id']);
            } catch (\Throwable $e) {
                // Ignore if FK missing
            }

            // Revert to NOT NULL
            $table->unsignedBigInteger('user_id')->nullable(false)->change();

            // Restore original foreign key behavior (cascade delete)
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }
};
