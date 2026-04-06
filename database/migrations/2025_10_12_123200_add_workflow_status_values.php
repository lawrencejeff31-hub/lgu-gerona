<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add missing workflow statuses: for_review, signed, forwarded, on_hold
        // Note: Laravel doesn't support modifying enum easily across all databases
        // So we'll use a string column to allow all workflow statuses
        Schema::table('documents', function (Blueprint $table) {
            // Drop any existing indexes on status column first
            try {
                $table->dropIndex(['status']);
            } catch (\Exception $e) {
                // Index might not exist, continue
            }
            // Change status from enum to string to support all workflow statuses
            $table->dropColumn('status');
        });
        
        Schema::table('documents', function (Blueprint $table) {
            $table->string('status')->default('draft')->after('type');
            $table->index('status'); // Add index for performance
        });
        
        // Update any existing records to use proper workflow statuses
        DB::statement("UPDATE documents SET status = 'draft' WHERE status IS NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
        
        // Restore original enum
        Schema::table('documents', function (Blueprint $table) {
            $table->enum('status', [
                'draft', 'submitted', 'received', 'under_review', 
                'for_approval', 'approved', 'rejected', 'awaiting_payment', 
                'paid', 'completed', 'archived'
            ])->default('draft')->after('type');
        });
    }
};