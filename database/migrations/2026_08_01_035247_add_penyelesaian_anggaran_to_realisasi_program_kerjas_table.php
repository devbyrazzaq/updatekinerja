<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Selisih anggaran laporan realisasi (sisa atau kekurangan) beserta tindak
 * lanjutnya: sudah dikembalikan/dilunasi, atau masih menunggu Biro Keuangan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('realisasi_program_kerjas', function (Blueprint $table) {
            $table->decimal('nominal_selisih_anggaran', 18, 2)->nullable()->after('status_anggaran');
            $table->string('status_penyelesaian_anggaran')->nullable()->after('nominal_selisih_anggaran');
            $table->dateTime('penyelesaian_anggaran_at')->nullable()->after('status_penyelesaian_anggaran');
        });
    }

    public function down(): void
    {
        Schema::table('realisasi_program_kerjas', function (Blueprint $table) {
            $table->dropColumn(['nominal_selisih_anggaran', 'status_penyelesaian_anggaran', 'penyelesaian_anggaran_at']);
        });
    }
};
