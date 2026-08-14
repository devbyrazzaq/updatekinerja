<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laporan realisasi: evaluasi pengerjaan, status penyerapan anggaran, dan
 * persentase ketercapaian target, melengkapi dokumen laporan yang diunggah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('realisasi_program_kerjas', function (Blueprint $table) {
            $table->text('evaluasi_pengerjaan')->nullable()->after('laporan_path');
            $table->string('status_anggaran')->nullable()->after('evaluasi_pengerjaan');
            $table->unsignedTinyInteger('persentase_ketercapaian')->nullable()->after('status_anggaran');
        });
    }

    public function down(): void
    {
        Schema::table('realisasi_program_kerjas', function (Blueprint $table) {
            $table->dropColumn(['evaluasi_pengerjaan', 'status_anggaran', 'persentase_ketercapaian']);
        });
    }
};
