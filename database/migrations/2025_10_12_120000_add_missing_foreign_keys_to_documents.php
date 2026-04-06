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
            // Add foreign key constraints for department relationships
            if (Schema::hasColumn('documents', 'department_id') && !$this->foreignKeyExists('documents', 'documents_department_id_foreign')) {
                $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            }
            
            if (Schema::hasColumn('documents', 'current_department_id') && !$this->foreignKeyExists('documents', 'documents_current_department_id_foreign')) {
                $table->foreign('current_department_id')->references('id')->on('departments')->onDelete('set null');
            }
            
            // Add foreign key constraint for document type
            if (Schema::hasColumn('documents', 'document_type_id') && !$this->foreignKeyExists('documents', 'documents_document_type_id_foreign')) {
                $table->foreign('document_type_id')->references('id')->on('document_types')->onDelete('set null');
            }
            
            // Add indexes for better query performance
            if (!$this->indexExists('documents', 'documents_status_index')) {
                $table->index('status');
            }
            
            if (!$this->indexExists('documents', 'documents_priority_index')) {
                $table->index('priority');
            }
            
            if (!$this->indexExists('documents', 'documents_security_level_index')) {
                $table->index('security_level');
            }
            
            if (!$this->indexExists('documents', 'documents_type_index')) {
                $table->index('type');
            }
            
            if (!$this->indexExists('documents', 'documents_created_at_index')) {
                $table->index('created_at');
            }
            
            if (!$this->indexExists('documents', 'documents_deadline_index')) {
                $table->index('deadline');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Drop foreign keys
            if ($this->foreignKeyExists('documents', 'documents_department_id_foreign')) {
                $table->dropForeign(['department_id']);
            }
            
            if ($this->foreignKeyExists('documents', 'documents_current_department_id_foreign')) {
                $table->dropForeign(['current_department_id']);
            }
            
            if ($this->foreignKeyExists('documents', 'documents_document_type_id_foreign')) {
                $table->dropForeign(['document_type_id']);
            }
            
            // Drop indexes
            if ($this->indexExists('documents', 'documents_status_index')) {
                $table->dropIndex(['status']);
            }
            
            if ($this->indexExists('documents', 'documents_priority_index')) {
                $table->dropIndex(['priority']);
            }
            
            if ($this->indexExists('documents', 'documents_security_level_index')) {
                $table->dropIndex(['security_level']);
            }
            
            if ($this->indexExists('documents', 'documents_type_index')) {
                $table->dropIndex(['type']);
            }
            
            if ($this->indexExists('documents', 'documents_created_at_index')) {
                $table->dropIndex(['created_at']);
            }
            
            if ($this->indexExists('documents', 'documents_deadline_index')) {
                $table->dropIndex(['deadline']);
            }
        });
    }
    
    /**
     * Check if a foreign key exists
     */
    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        try {
            $connection = Schema::getConnection();
            $schemaBuilder = $connection->getSchemaBuilder();
            
            // Get all foreign keys for the table
            $foreignKeys = $schemaBuilder->getForeignKeys($table);
            
            foreach ($foreignKeys as $key) {
                if ($key['name'] === $foreignKey) {
                    return true;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            // If we can't check, assume it doesn't exist
            return false;
        }
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