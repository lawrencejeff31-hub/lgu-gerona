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
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->enum('type', ['bug', 'feature_request', 'improvement', 'question', 'other'])->default('bug');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open');
            $table->string('reporter_name');
            $table->string('reporter_email');
            $table->string('reporter_phone')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // If reported by authenticated user
            $table->json('attachments')->nullable(); // Store file paths as JSON array
            $table->text('admin_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['status', 'priority']);
            $table->index(['type', 'created_at']);
            $table->index('reporter_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
