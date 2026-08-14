<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membalik arah relasi jadwal pencairan: semula satu baris jadwal milik satu
 * realisasi, kini jadwal menjadi record mandiri (nama + tanggal) yang menampung
 * banyak realisasi. Nominal jadwal tidak lagi disimpan karena dihitung dari
 * total realisasi yang dijadwalkan padanya.
 *
 * Realisasi juga mulai menyimpan cara anggaran diserahkan (transfer/tunai)
 * beserta tujuan rekeningnya bila ditransfer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_pencairans', function (Blueprint $table) {
            $table->foreignId('tahun_kerja_id')->nullable()->after('id')->constrained('tahun_kerjas')->cascadeOnDelete();
            $table->string('name')->nullable()->after('tahun_kerja_id');
        });

        Schema::table('realisasi_program_kerjas', function (Blueprint $table) {
            $table->foreignId('jadwal_pencairan_id')->nullable()->after('status_pencairan')->constrained('jadwal_pencairans')->nullOnDelete();
            $table->string('metode_pembayaran')->nullable()->after('dicairkan_at');
            $table->string('nama_bank')->nullable()->after('metode_pembayaran');
            $table->string('nomor_rekening')->nullable()->after('nama_bank');
            $table->string('nama_pemilik_rekening')->nullable()->after('nomor_rekening');
        });

        $this->pindahkanJadwalLama();

        // SQLite membedakan perintah alter dari perintah yang membangun ulang tabel,
        // jadi pembuangan foreign key, kolom, dan perubahan kolom dipisah per blueprint.
        Schema::table('jadwal_pencairans', function (Blueprint $table) {
            $table->dropForeign(['realisasi_program_kerja_id']);
        });

        Schema::table('jadwal_pencairans', function (Blueprint $table) {
            $table->dropColumn(['realisasi_program_kerja_id', 'nominal']);
        });

        Schema::table('jadwal_pencairans', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
        });

        Schema::table('realisasi_program_kerjas', function (Blueprint $table) {
            $table->dropColumn('jadwal_pencairan');
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_pencairans', function (Blueprint $table) {
            $table->foreignId('realisasi_program_kerja_id')->nullable()->after('id')->constrained('realisasi_program_kerjas')->cascadeOnDelete();
            $table->decimal('nominal', 18, 2)->nullable()->after('tanggal_pencairan');
        });

        Schema::table('realisasi_program_kerjas', function (Blueprint $table) {
            $table->date('jadwal_pencairan')->nullable()->after('status_pencairan');
        });

        DB::table('realisasi_program_kerjas')
            ->whereNotNull('jadwal_pencairan_id')
            ->orderBy('id')
            ->each(function (object $realisasi): void {
                $jadwal = DB::table('jadwal_pencairans')->where('id', $realisasi->jadwal_pencairan_id)->first();

                if ($jadwal === null) {
                    return;
                }

                DB::table('realisasi_program_kerjas')
                    ->where('id', $realisasi->id)
                    ->update(['jadwal_pencairan' => $jadwal->tanggal_pencairan]);

                DB::table('jadwal_pencairans')
                    ->where('id', $jadwal->id)
                    ->update([
                        'realisasi_program_kerja_id' => $realisasi->id,
                        'nominal' => $realisasi->nominal_disetujui ?? $realisasi->anggaran_digunakan,
                    ]);
            });

        Schema::table('realisasi_program_kerjas', function (Blueprint $table) {
            $table->dropForeign(['jadwal_pencairan_id']);
        });

        Schema::table('realisasi_program_kerjas', function (Blueprint $table) {
            $table->dropColumn(['jadwal_pencairan_id', 'metode_pembayaran', 'nama_bank', 'nomor_rekening', 'nama_pemilik_rekening']);
        });

        Schema::table('jadwal_pencairans', function (Blueprint $table) {
            $table->dropForeign(['tahun_kerja_id']);
        });

        Schema::table('jadwal_pencairans', function (Blueprint $table) {
            $table->dropColumn(['tahun_kerja_id', 'name']);
        });
    }

    /**
     * Data lama: tiap baris jadwal mewakili satu realisasi. Tautannya dipindahkan
     * ke kolom baru di sisi realisasi, lalu jadwal diberi nama turunan tanggalnya
     * dan tahun kerja yang diwarisi dari penawaran induk realisasi.
     */
    private function pindahkanJadwalLama(): void
    {
        DB::table('jadwal_pencairans')->orderBy('id')->each(function (object $jadwal): void {
            $tanggal = Carbon::parse($jadwal->tanggal_pencairan)->locale('id');

            DB::table('jadwal_pencairans')
                ->where('id', $jadwal->id)
                ->update([
                    'name' => 'Pencairan '.$tanggal->translatedFormat('d F Y'),
                    'tahun_kerja_id' => $this->tahunKerjaRealisasi($jadwal->realisasi_program_kerja_id),
                ]);

            DB::table('realisasi_program_kerjas')
                ->where('id', $jadwal->realisasi_program_kerja_id)
                ->update(['jadwal_pencairan_id' => $jadwal->id]);
        });
    }

    private function tahunKerjaRealisasi(?int $realisasiId): ?int
    {
        if ($realisasiId === null) {
            return null;
        }

        return DB::table('realisasi_program_kerjas as realisasi')
            ->join('pengajuan_program_kerjas as pengajuan', 'pengajuan.id', '=', 'realisasi.pengajuan_program_kerja_id')
            ->join('penawaran_program_kerjas as penawaran', 'penawaran.id', '=', 'pengajuan.penawaran_program_kerja_id')
            ->where('realisasi.id', $realisasiId)
            ->value('penawaran.tahun_kerja_id');
    }
};
