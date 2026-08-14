<?php

use App\Enums\EnumStatusTahunKerja;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Siklus hidup tahun kerja menggantikan penanda aktif tunggal, sehingga satu
     * tahun Berjalan dan satu tahun Perencanaan bisa hidup berdampingan. Kelompok
     * acuan ikut pindah ke baris tahun kerja agar tiap slot konteks berdiri sendiri
     * saat keduanya berada di RENSTRA yang berbeda.
     */
    public function up(): void
    {
        Schema::table('tahun_kerjas', function (Blueprint $table) {
            $table->string('status')
                ->default(EnumStatusTahunKerja::Selesai->value)
                ->after('slug')
                ->index();

            $table->foreignId('kelompok_acuan_id')
                ->nullable()
                ->after('periode_id')
                ->constrained('kelompok_acuans')
                ->nullOnDelete();
        });

        DB::table('tahun_kerjas')
            ->where('is_active', true)
            ->update(['status' => EnumStatusTahunKerja::Berjalan->value]);

        $kelompokAcuanAktif = DB::table('kelompok_acuans')->where('is_active', true)->value('id');

        if ($kelompokAcuanAktif !== null) {
            DB::table('tahun_kerjas')
                ->where('status', EnumStatusTahunKerja::Berjalan->value)
                ->update(['kelompok_acuan_id' => $kelompokAcuanAktif]);
        }

        Schema::table('tahun_kerjas', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('tahun_kerjas', function (Blueprint $table) {
            $table->boolean('is_active')->default(false)->after('slug');
        });

        DB::table('tahun_kerjas')
            ->where('status', EnumStatusTahunKerja::Berjalan->value)
            ->update(['is_active' => true]);

        Schema::table('tahun_kerjas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kelompok_acuan_id');
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
