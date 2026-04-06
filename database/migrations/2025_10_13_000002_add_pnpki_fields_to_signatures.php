<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signatures', function (Blueprint $table) {
            if (!Schema::hasColumn('signatures', 'pnpki_certificate')) {
                $table->json('pnpki_certificate')->nullable()->after('certificate_serial');
            }
            if (!Schema::hasColumn('signatures', 'verification_status')) {
                $table->enum('verification_status', ['pending', 'verified', 'failed'])->default('pending')->after('pnpki_certificate');
            }
            if (!Schema::hasColumn('signatures', 'signature_type')) {
                $table->enum('signature_type', ['manual', 'digital', 'pnpki'])->default('pnpki')->after('verification_status');
            }
            
            $table->index(['document_id', 'signature_type']);
            $table->index('verification_status');
        });
    }

    public function down(): void
    {
        Schema::table('signatures', function (Blueprint $table) {
            $table->dropIndex(['document_id', 'signature_type']);
            $table->dropIndex(['verification_status']);
            $table->dropColumn(['pnpki_certificate', 'verification_status', 'signature_type']);
        });
    }
};