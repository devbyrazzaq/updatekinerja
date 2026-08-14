<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tahun target yang diacu tahun kerja. Dipakai untuk memilih target Acuan Program
     * Kerja saat penawaran dibentuk, sehingga tidak lagi disimpulkan dari start_datetime.
     */
    public function up(): void
    {
        Schema::table('tahun_kerjas', function (Blueprint $table) {
            $table->unsignedSmallInteger('tahun')->nullable()->after('slug')->index();
        });

        DB::table('tahun_kerjas')
            ->whereNull('tahun')
            ->select('id', 'start_datetime')
            ->orderBy('id')
            ->each(function (object $tahunKerja): void {
                DB::table('tahun_kerjas')
                    ->where('id', $tahunKerja->id)
                    ->update(['tahun' => Carbon::parse($tahunKerja->start_datetime)->year]);
            });
    }

    public function down(): void
    {
        Schema::table('tahun_kerjas', function (Blueprint $table) {
            $table->dropColumn('tahun');
        });
    }
};
