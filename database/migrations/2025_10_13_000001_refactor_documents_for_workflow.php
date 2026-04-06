<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Add hold fields if they don't exist
            if (!Schema::hasColumn('documents', 'hold_reason')) {
                $table->text('hold_reason')->nullable()->after('rejection_reason');
            }
            if (!Schema::hasColumn('documents', 'hold_at')) {
                $table->timestamp('hold_at')->nullable()->after('hold_reason');
            }
        });
        
        Schema::table('documents', function (Blueprint $table) {
            if (!$this->indexExists('documents', 'documents_status_workflow_index')) {
                $table->index('status', 'documents_status_workflow_index');
            }
            if (!$this->indexExists('documents', 'documents_current_department_workflow_index')) {
                $table->index('current_department_id', 'documents_current_department_workflow_index');
            }
            if (!$this->indexExists('documents', 'documents_status_department_workflow_index')) {
                $table->index(['status', 'current_department_id'], 'documents_status_department_workflow_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if ($this->indexExists('documents', 'documents_status_workflow_index')) {
                $table->dropIndex('documents_status_workflow_index');
            }
            if ($this->indexExists('documents', 'documents_current_department_workflow_index')) {
                $table->dropIndex('documents_current_department_workflow_index');
            }
            if ($this->indexExists('documents', 'documents_status_department_workflow_index')) {
                $table->dropIndex('documents_status_department_workflow_index');
            }
        });
        
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['hold_reason', 'hold_at']);
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                $res = DB::select('SELECT COUNT(1) AS cnt FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?', [$table, $index]);
                return (int)($res[0]->cnt ?? 0) > 0;
            }
            if ($driver === 'pgsql') {
                $res = DB::select('SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? AND indexname = ?', [$table, $index]);
                return !empty($res);
            }
            if ($driver === 'sqlite') {
                $res = DB::select("PRAGMA index_list('$table')");
                foreach ($res as $row) {
                    $name = is_array($row) ? ($row['name'] ?? null) : ($row->name ?? null);
                    if ($name === $index) {
                        return true;
                    }
                }
                return false;
            }
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }
};