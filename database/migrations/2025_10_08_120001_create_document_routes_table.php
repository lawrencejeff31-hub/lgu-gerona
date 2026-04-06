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
        Schema::create('document_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->onDelete('cascade');
            $table->foreignId('from_office_id')->nullable()->constrained('departments')->onDelete('set null');
            $table->foreignId('to_office_id')->constrained('departments')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // User who performed the action
            $table->enum('status', ['sent', 'received', 'rejected', 'approved'])->default('sent');
            $table->text('remarks')->nullable();
            $table->timestamps();
            
            $table->index(['document_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_routes');
    }
};