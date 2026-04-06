<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'position')) {
                $table->string('position', 100)->nullable();
            }

            if (!Schema::hasColumn('users', 'pnpki_certificate_serial')) {
                $table->string('pnpki_certificate_serial', 128)->nullable()->index();
            }

            if (!Schema::hasColumn('users', 'can_sign_digitally')) {
                $table->boolean('can_sign_digitally')->default(false)->index();
            }

            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'position')) {
                $table->dropColumn('position');
            }
            if (Schema::hasColumn('users', 'pnpki_certificate_serial')) {
                $table->dropColumn('pnpki_certificate_serial');
            }
            if (Schema::hasColumn('users', 'can_sign_digitally')) {
                $table->dropColumn('can_sign_digitally');
            }
            if (Schema::hasColumn('users', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};