<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signatures', function (Blueprint $table) {
            if (!Schema::hasColumn('signatures', 'signature_file_path')) {
                $table->string('signature_file_path')->nullable()->after('signature_image_path');
            }
            if (!Schema::hasColumn('signatures', 'certificate_serial')) {
                $table->string('certificate_serial')->nullable()->after('signature_hash');
            }
            if (!Schema::hasColumn('signatures', 'algorithm')) {
                $table->string('algorithm')->default('SHA256withRSA')->after('certificate_serial');
            }
            if (!Schema::hasColumn('signatures', 'metadata')) {
                $table->json('metadata')->nullable()->after('algorithm');
            }
        });
    }

    public function down(): void
    {
        Schema::table('signatures', function (Blueprint $table) {
            $columns = ['signature_file_path', 'certificate_serial', 'algorithm', 'metadata'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('signatures', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
