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
        Schema::table('documents', function (Blueprint $table) {
            // Add new fields for DTS requirements
            $table->string('file_name')->nullable()->after('document_number');
            $table->unsignedBigInteger('sender_id')->nullable()->after('created_by');
            $table->string('security_level')->default('internal')->after('priority');
            $table->string('qr_code_path')->nullable()->after('security_level');
            $table->string('file_path')->nullable()->after('qr_code_path');
            
            // Add foreign key for sender
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('set null');
            
            // Update existing enum values to include new statuses
            $table->dropColumn('type');
        });
        
        // Add type column with new enum values
        Schema::table('documents', function (Blueprint $table) {
            $table->enum('type', ['PR', 'PO', 'DV', 'bid', 'award', 'contract', 'other'])->default('other')->after('description');
        });
        
        // Update status enum to include new values
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('status');
        });
        
        Schema::table('documents', function (Blueprint $table) {
            $table->enum('status', [
                'draft', 'submitted', 'received', 'under_review', 
                'for_approval', 'approved', 'rejected', 'awaiting_payment', 
                'paid', 'completed', 'archived'
            ])->default('draft')->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['sender_id']);
            $table->dropColumn([
                'file_name',
                'sender_id', 
                'security_level',
                'qr_code_path',
                'file_path'
            ]);
            
            // Restore original type and status enums
            $table->dropColumn(['type', 'status']);
        });
        
        Schema::table('documents', function (Blueprint $table) {
            $table->enum('type', ['bid', 'award', 'contract', 'other'])->after('description');
            $table->enum('status', ['draft', 'submitted', 'under_review', 'approved', 'rejected', 'completed'])->after('type');
        });
    }
};