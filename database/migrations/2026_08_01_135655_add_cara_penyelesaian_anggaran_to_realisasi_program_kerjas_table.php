<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cara selisih anggaran dituntaskan menurut verifikator laporan: kekurangan
 * ditalangi/dicairkan/dipotong, atau sisa dikembalikan/ditahan/dialihkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('realisasi_program_kerjas', function (Blueprint $table) {
            $table->string('cara_penyelesaian_anggaran')->nullable()->after('status_penyelesaian_anggaran');
        });
    }

    public function down(): void
    {
        Schema::table('realisasi_program_kerjas', function (Blueprint $table) {
            $table->dropColumn('cara_penyelesaian_anggaran');
        });
    }
};
