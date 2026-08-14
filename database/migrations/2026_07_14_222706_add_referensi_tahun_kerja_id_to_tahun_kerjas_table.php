<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tahun kerja lain yang dijadikan referensi anggaran. Bila diisi, halaman Pagu
     * Anggaran menampilkan kolom perbandingan anggaran unit kerja dari tahun tersebut.
     */
    public function up(): void
    {
        Schema::table('tahun_kerjas', function (Blueprint $table) {
            $table->foreignId('referensi_tahun_kerja_id')
                ->nullable()
                ->after('batas_anggaran')
                ->constrained('tahun_kerjas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tahun_kerjas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referensi_tahun_kerja_id');
        });
    }
};
