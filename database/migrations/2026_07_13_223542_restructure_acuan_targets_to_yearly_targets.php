<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Target acuan kini melekat pada tahun kalender (mengikuti rentang tahun kelompok
 * acuan), bukan pada record Tahun Kerja, dan menyimpan nilai beserta satuannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acuan_targets', function (Blueprint $table) {
            $table->unsignedSmallInteger('tahun')->nullable()->after('acuan_program_kerja_id');
            $table->string('satuan')->nullable()->after('target');
        });

        Schema::table('acuan_targets', function (Blueprint $table) {
            $table->renameColumn('target', 'nilai');
        });

        $tahunPerTahunKerja = DB::table('tahun_kerjas')
            ->pluck('start_datetime', 'id')
            ->map(fn (string $startDatetime): int => Carbon::parse($startDatetime)->year);

        foreach ($tahunPerTahunKerja as $tahunKerjaId => $tahun) {
            DB::table('acuan_targets')
                ->where('tahun_kerja_id', $tahunKerjaId)
                ->update(['tahun' => $tahun]);
        }

        DB::table('acuan_targets')->whereNull('tahun')->delete();

        // Unique baru dibuat lebih dulu: unique lama masih dipakai foreign key
        // acuan_program_kerja_id sebagai indeks pendukung, jadi belum bisa dibuang.
        Schema::table('acuan_targets', function (Blueprint $table) {
            $table->unsignedSmallInteger('tahun')->nullable(false)->change();
            $table->unique(['acuan_program_kerja_id', 'tahun']);
        });

        Schema::table('acuan_targets', function (Blueprint $table) {
            $table->dropForeign(['tahun_kerja_id']);
        });

        Schema::table('acuan_targets', function (Blueprint $table) {
            $table->dropUnique('acuan_targets_acuan_program_kerja_id_tahun_kerja_id_unique');
        });

        Schema::table('acuan_targets', function (Blueprint $table) {
            $table->dropColumn('tahun_kerja_id');
        });
    }

    public function down(): void
    {
        Schema::table('acuan_targets', function (Blueprint $table) {
            $table->foreignId('tahun_kerja_id')->nullable()->after('acuan_program_kerja_id')->constrained('tahun_kerjas')->cascadeOnDelete();
        });

        Schema::table('acuan_targets', function (Blueprint $table) {
            $table->dropUnique('acuan_targets_acuan_program_kerja_id_tahun_unique');
            $table->dropColumn(['tahun', 'satuan']);
        });

        Schema::table('acuan_targets', function (Blueprint $table) {
            $table->renameColumn('nilai', 'target');
        });
    }
};
