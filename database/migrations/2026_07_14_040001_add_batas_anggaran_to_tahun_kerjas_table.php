<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Batas nominal anggaran yang diberikan untuk satu tahun kerja. Diisi di halaman
     * Pengaturan Program Kerja dan menjadi pembanding pengajuan serta penggunaan
     * anggaran pada ringkasan Pagu Anggaran.
     */
    public function up(): void
    {
        Schema::table('tahun_kerjas', function (Blueprint $table) {
            $table->decimal('batas_anggaran', 18, 2)->nullable()->after('tahun');
        });
    }

    public function down(): void
    {
        Schema::table('tahun_kerjas', function (Blueprint $table) {
            $table->dropColumn('batas_anggaran');
        });
    }
};
