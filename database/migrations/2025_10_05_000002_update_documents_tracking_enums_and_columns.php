<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Documents: add fields and widen enums
        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'barcode')) {
                $table->string('barcode')->unique()->nullable()->after('document_number');
            }
            if (!Schema::hasColumn('documents', 'document_type_id')) {
                $table->foreignId('document_type_id')->nullable()->after('description')->constrained('document_types')->nullOnDelete();
            }
            if (!Schema::hasColumn('documents', 'department_id')) {
                $table->foreignId('department_id')->nullable()->after('document_type_id')->constrained('departments')->nullOnDelete();
            }
            if (!Schema::hasColumn('documents', 'current_department_id')) {
                $table->foreignId('current_department_id')->nullable()->after('department_id')->constrained('departments')->nullOnDelete();
            }
            if (!Schema::hasColumn('documents', 'security')) {
                $table->enum('security', ['public','internal','confidential','secret'])->default('internal')->after('priority');
            }
            if (!Schema::hasColumn('documents', 'tags')) {
                $table->json('tags')->nullable()->after('metadata');
            }
        });

        // Alter enum columns if needed - can't alter enum easily across DBs, keep as is if exists
        // Tracking table: widen action enum if not present
        Schema::table('document_tracking', function (Blueprint $table) {
            // no-op for enum portability; new actions will be validated at app level
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'barcode')) $table->dropColumn('barcode');
            if (Schema::hasColumn('documents', 'document_type_id')) $table->dropConstrainedForeignId('document_type_id');
            if (Schema::hasColumn('documents', 'department_id')) $table->dropConstrainedForeignId('department_id');
            if (Schema::hasColumn('documents', 'current_department_id')) $table->dropConstrainedForeignId('current_department_id');
            if (Schema::hasColumn('documents', 'security')) $table->dropColumn('security');
            if (Schema::hasColumn('documents', 'tags')) $table->dropColumn('tags');
        });
    }
};
