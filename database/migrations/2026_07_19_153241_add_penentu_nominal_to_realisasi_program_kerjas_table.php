<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mencatat verifikator yang menetapkan `nominal_disetujui`. Nominal dapat ditetapkan
 * pada tahap Rektor (final) atau Wakil Rektor, sehingga penentunya perlu disimpan
 * eksplisit agar tampilan Persetujuan Anggaran dan log dapat menyebut siapa yang
 * menentukan besaran persetujuan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('realisasi_program_kerjas', function (Blueprint $table): void {
            $table->foreignId('penentu_nominal_id')->nullable()->after('nominal_disetujui')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('realisasi_program_kerjas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('penentu_nominal_id');
        });
    }
};
