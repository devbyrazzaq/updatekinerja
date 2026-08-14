<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dokumen proposal wajib menyertai pengajuan realisasi program kerja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('realisasi_program_kerjas', function (Blueprint $table) {
            $table->string('proposal_path')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('realisasi_program_kerjas', function (Blueprint $table) {
            $table->dropColumn('proposal_path');
        });
    }
};
