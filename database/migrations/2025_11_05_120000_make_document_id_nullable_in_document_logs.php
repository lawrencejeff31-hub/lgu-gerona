<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_logs', function (Blueprint $table) {
            // Drop existing foreign key if present, then make column nullable and re-add FK with set null on delete
            try {
                $table->dropForeign(['document_id']);
            } catch (\Throwable $e) {
                // Foreign key may not exist in some environments; proceed to change column
            }

            // Make document_id nullable
            $table->unsignedBigInteger('document_id')->nullable()->change();

            // Re-add foreign key with set null on delete
            $table->foreign('document_id')
                ->references('id')
                ->on('documents')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('document_logs', function (Blueprint $table) {
            try {
                $table->dropForeign(['document_id']);
            } catch (\Throwable $e) {
                // Ignore if FK missing
            }

            // Revert to NOT NULL
            $table->unsignedBigInteger('document_id')->nullable(false)->change();

            // Restore original foreign key behavior (cascade delete)
            $table->foreign('document_id')
                ->references('id')
                ->on('documents')
                ->onDelete('cascade');
        });
    }
};