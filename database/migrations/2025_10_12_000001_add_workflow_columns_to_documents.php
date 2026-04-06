<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add workflow-related columns to documents table
        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'qr_code_path')) {
                $table->string('qr_code_path')->nullable()->after('metadata');
            }
            if (!Schema::hasColumn('documents', 'file_path')) {
                $table->string('file_path')->nullable()->after('qr_code_path');
            }
            if (!Schema::hasColumn('documents', 'file_name')) {
                $table->string('file_name')->nullable()->after('file_path');
            }
            if (!Schema::hasColumn('documents', 'security_level')) {
                $table->enum('security_level', ['public', 'internal', 'confidential', 'secret'])
                    ->default('internal')->after('priority');
            }
            if (!Schema::hasColumn('documents', 'sender_id')) {
                $table->foreignId('sender_id')->nullable()->after('created_by')
                    ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('documents', 'received_by')) {
                $table->foreignId('received_by')->nullable()->after('assigned_to')
                    ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('documents', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('received_by')
                    ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('documents', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('approved_by')
                    ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('documents', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('deadline');
            }
            if (!Schema::hasColumn('documents', 'received_at')) {
                $table->timestamp('received_at')->nullable()->after('submitted_at');
            }
            if (!Schema::hasColumn('documents', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('received_at');
            }
            if (!Schema::hasColumn('documents', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('documents', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('rejected_at');
            }
            if (!Schema::hasColumn('documents', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('completed_at');
            }
            if (!Schema::hasColumn('documents', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('archived_at');
            }
            if (!Schema::hasColumn('documents', 'hold_reason')) {
                $table->text('hold_reason')->nullable()->after('rejection_reason');
            }
            if (!Schema::hasColumn('documents', 'hold_at')) {
                $table->timestamp('hold_at')->nullable()->after('hold_reason');
            }
        });

        // Add office tracking columns to document_logs table
        Schema::table('document_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('document_logs', 'from_office_id')) {
                $table->foreignId('from_office_id')->nullable()->after('document_id')
                    ->constrained('departments')->nullOnDelete();
            }
            if (!Schema::hasColumn('document_logs', 'to_office_id')) {
                $table->foreignId('to_office_id')->nullable()->after('from_office_id')
                    ->constrained('departments')->nullOnDelete();
            }
            if (!Schema::hasColumn('document_logs', 'remarks')) {
                $table->text('remarks')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $columns = [
                'qr_code_path', 'file_path', 'file_name', 'security_level',
                'sender_id', 'received_by', 'approved_by', 'rejected_by',
                'submitted_at', 'received_at', 'approved_at', 'rejected_at',
                'completed_at', 'archived_at', 'rejection_reason', 'hold_reason', 'hold_at'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('documents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('document_logs', function (Blueprint $table) {
            if (Schema::hasColumn('document_logs', 'from_office_id')) {
                $table->dropConstrainedForeignId('from_office_id');
            }
            if (Schema::hasColumn('document_logs', 'to_office_id')) {
                $table->dropConstrainedForeignId('to_office_id');
            }
            if (Schema::hasColumn('document_logs', 'remarks')) {
                $table->dropColumn('remarks');
            }
        });
    }
};
