<?php

use App\Enums\EnumJenisWaktuPemasukan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pemasukans', function (Blueprint $table): void {
            $table->string('jenis_waktu')
                ->default(EnumJenisWaktuPemasukan::SatuHari->value)
                ->after('rincian_kegiatan');
            $table->date('tanggal_selesai')->nullable()->after('tanggal_pelaksanaan');
        });
    }

    public function down(): void
    {
        Schema::table('pemasukans', function (Blueprint $table): void {
            $table->dropColumn(['jenis_waktu', 'tanggal_selesai']);
        });
    }
};
