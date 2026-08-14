<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Memisahkan nominal yang diajukan dari nominal yang disetujui pada realisasi:
 *
 * - `nominal_diajukan`  : nominal yang diajukan unit, dikunci saat laporan akhir masuk
 *   sehingga tidak ikut berubah ketika `anggaran_digunakan` diperbarui jadi realisasi akhir.
 * - `nominal_disetujui` : satu nominal hasil persetujuan verifikator, menggantikan dua
 *   kolom terpisah `nominal_disetujui_rektor` dan `nominal_disetujui_wakil`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('realisasi_program_kerjas', function (Blueprint $table): void {
            $table->decimal('nominal_diajukan', 18, 2)->nullable()->after('anggaran_digunakan');
            $table->decimal('nominal_disetujui', 18, 2)->nullable()->after('nominal_diajukan');
        });

        DB::table('realisasi_program_kerjas')->update([
            'nominal_diajukan' => DB::raw('anggaran_digunakan'),
            'nominal_disetujui' => DB::raw('COALESCE(nominal_disetujui_wakil, nominal_disetujui_rektor)'),
        ]);

        Schema::table('realisasi_program_kerjas', function (Blueprint $table): void {
            $table->dropColumn(['nominal_disetujui_rektor', 'nominal_disetujui_wakil']);
        });
    }

    public function down(): void
    {
        Schema::table('realisasi_program_kerjas', function (Blueprint $table): void {
            $table->decimal('nominal_disetujui_rektor', 18, 2)->nullable()->after('catatan_verifikasi');
            $table->decimal('nominal_disetujui_wakil', 18, 2)->nullable()->after('disetujui_rektor_at');
        });

        DB::table('realisasi_program_kerjas')->update([
            'nominal_disetujui_wakil' => DB::raw('nominal_disetujui'),
        ]);

        Schema::table('realisasi_program_kerjas', function (Blueprint $table): void {
            $table->dropColumn(['nominal_diajukan', 'nominal_disetujui']);
        });
    }
};
