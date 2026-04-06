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
            // Add indexes for frequently queried columns (check if they don't exist first)
            if (!$this->indexExists('document_logs', 'document_logs_document_id_created_at_index')) {
                $table->index(['document_id', 'created_at']);
            }
            if (!$this->indexExists('document_logs', 'document_logs_action_index')) {
                $table->index('action');
            }
            if (!$this->indexExists('document_logs', 'document_logs_user_id_index')) {
                $table->index('user_id');
            }
        });

        Schema::table('attachments', function (Blueprint $table) {
            // Add indexes for attachment queries
            if (!$this->indexExists('attachments', 'attachments_document_id_index')) {
                $table->index('document_id');
            }
            if (!$this->indexExists('attachments', 'attachments_uploaded_by_index')) {
                $table->index('uploaded_by');
            }
            if (!$this->indexExists('attachments', 'attachments_created_at_index')) {
                $table->index('created_at');
            }
        });

        Schema::table('qr_codes', function (Blueprint $table) {
            // Add indexes for QR code lookups
            if (!$this->indexExists('qr_codes', 'qr_codes_document_id_index')) {
                $table->index('document_id');
            }
            if (!$this->indexExists('qr_codes', 'qr_codes_token_index')) {
                $table->index('token');
            }
            if (!$this->indexExists('qr_codes', 'qr_codes_created_at_index')) {
                $table->index('created_at');
            }
        });

        Schema::table('document_routes', function (Blueprint $table) {
            // Add additional indexes for routing queries
            if (!$this->indexExists('document_routes', 'document_routes_from_office_id_index')) {
                $table->index('from_office_id');
            }
            if (!$this->indexExists('document_routes', 'document_routes_to_office_id_index')) {
                $table->index('to_office_id');
            }
            if (!$this->indexExists('document_routes', 'document_routes_status_index')) {
                $table->index('status');
            }
        });

        Schema::table('departments', function (Blueprint $table) {
            // Add indexes for department queries
            if (Schema::hasColumn('departments', 'is_active') && !$this->indexExists('departments', 'departments_is_active_index')) {
                $table->index('is_active');
            }
            if (!$this->indexExists('departments', 'departments_name_index')) {
                $table->index('name');
            }
        });

        Schema::table('document_types', function (Blueprint $table) {
            // Add indexes for document type queries
            if (Schema::hasColumn('document_types', 'is_active') && !$this->indexExists('document_types', 'document_types_is_active_index')) {
                $table->index('is_active');
            }
            if (Schema::hasColumn('document_types', 'code') && !$this->indexExists('document_types', 'document_types_code_index')) {
                $table->index('code');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            // Add indexes for user queries
            if (Schema::hasColumn('users', 'department_id') && !$this->indexExists('users', 'users_department_id_index')) {
                $table->index('department_id');
            }
            // Note: is_active column doesn't exist in users table, so we skip this index
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_logs', function (Blueprint $table) {
            if ($this->indexExists('document_logs', 'document_logs_document_id_created_at_index')) {
                $table->dropIndex(['document_id', 'created_at']);
            }
            if ($this->indexExists('document_logs', 'document_logs_action_index')) {
                $table->dropIndex(['action']);
            }
            if ($this->indexExists('document_logs', 'document_logs_user_id_index')) {
                $table->dropIndex(['user_id']);
            }
        });

        Schema::table('attachments', function (Blueprint $table) {
            if ($this->indexExists('attachments', 'attachments_document_id_index')) {
                $table->dropIndex(['document_id']);
            }
            if ($this->indexExists('attachments', 'attachments_uploaded_by_index')) {
                $table->dropIndex(['uploaded_by']);
            }
            if ($this->indexExists('attachments', 'attachments_created_at_index')) {
                $table->dropIndex(['created_at']);
            }
        });

        Schema::table('qr_codes', function (Blueprint $table) {
            if ($this->indexExists('qr_codes', 'qr_codes_document_id_index')) {
                $table->dropIndex(['document_id']);
            }
            if ($this->indexExists('qr_codes', 'qr_codes_token_index')) {
                $table->dropIndex(['token']);
            }
            if ($this->indexExists('qr_codes', 'qr_codes_created_at_index')) {
                $table->dropIndex(['created_at']);
            }
        });

        Schema::table('document_routes', function (Blueprint $table) {
            if ($this->indexExists('document_routes', 'document_routes_from_office_id_index')) {
                $table->dropIndex(['from_office_id']);
            }
            if ($this->indexExists('document_routes', 'document_routes_to_office_id_index')) {
                $table->dropIndex(['to_office_id']);
            }
            if ($this->indexExists('document_routes', 'document_routes_status_index')) {
                $table->dropIndex(['status']);
            }
        });

        Schema::table('departments', function (Blueprint $table) {
            if ($this->indexExists('departments', 'departments_is_active_index')) {
                $table->dropIndex(['is_active']);
            }
            if ($this->indexExists('departments', 'departments_name_index')) {
                $table->dropIndex(['name']);
            }
        });

        Schema::table('document_types', function (Blueprint $table) {
            if ($this->indexExists('document_types', 'document_types_is_active_index')) {
                $table->dropIndex(['is_active']);
            }
            if ($this->indexExists('document_types', 'document_types_code_index')) {
                $table->dropIndex(['code']);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if ($this->indexExists('users', 'users_department_id_index')) {
                $table->dropIndex(['department_id']);
            }
            // Note: is_active column doesn't exist in users table, so we skip this index
        });
    }

    /**
     * Check if an index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        try {
            $connection = Schema::getConnection();
            $schemaBuilder = $connection->getSchemaBuilder();
            
            // Get all indexes for the table
            $indexes = $schemaBuilder->getIndexes($table);
            
            foreach ($indexes as $idx) {
                if ($idx['name'] === $index) {
                    return true;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            // If we can't check, assume it doesn't exist
            return false;
        }
    }
};