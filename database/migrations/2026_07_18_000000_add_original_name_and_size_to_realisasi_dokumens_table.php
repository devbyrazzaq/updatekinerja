<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Menyimpan nama berkas asli (sesuai unggahan pengguna) dan ukuran berkas pada
 * tiap dokumen realisasi. Nama penyimpanan tetap acak demi keamanan; nama asli
 * dan ukuran hanya untuk ditampilkan. Peta nama asli disimpan di induk agar dapat
 * disalin ke dokumen saat berkas diunggah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('realisasi_program_kerjas', function (Blueprint $table): void {
            $table->json('proposal_original_names')->nullable()->after('proposal_path');
            $table->json('laporan_original_names')->nullable()->after('laporan_path');
        });

        Schema::table('realisasi_dokumens', function (Blueprint $table): void {
            $table->string('original_name')->nullable()->after('name');
            $table->unsignedBigInteger('size')->nullable()->after('original_name');
        });

        $disk = Storage::disk(config('filament.default_filesystem_disk'));

        DB::table('realisasi_dokumens')
            ->select('id', 'path')
            ->orderBy('id')
            ->each(function (object $dokumen) use ($disk): void {
                if (blank($dokumen->path) || ! $disk->exists($dokumen->path)) {
                    return;
                }

                DB::table('realisasi_dokumens')
                    ->where('id', $dokumen->id)
                    ->update(['size' => $disk->size($dokumen->path)]);
            });
    }

    public function down(): void
    {
        Schema::table('realisasi_dokumens', function (Blueprint $table): void {
            $table->dropColumn(['original_name', 'size']);
        });

        Schema::table('realisasi_program_kerjas', function (Blueprint $table): void {
            $table->dropColumn(['proposal_original_names', 'laporan_original_names']);
        });
    }
};
