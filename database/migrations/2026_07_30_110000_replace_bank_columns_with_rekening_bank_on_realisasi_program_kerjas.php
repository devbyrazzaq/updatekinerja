<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Tujuan transfer pencairan tidak lagi ditulis lepas pada realisasi, melainkan
 * merujuk master Rekening Bank (bank + nomor rekening + atas nama) sehingga data
 * rekening unit kerja dikelola satu tempat dan dapat terpilih otomatis saat
 * penjadwalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('realisasi_program_kerjas', function (Blueprint $table) {
            $table->foreignId('rekening_bank_id')->nullable()->after('metode_pembayaran')->constrained('rekening_banks')->nullOnDelete();
        });

        $this->pindahkanRekeningLepas();

        Schema::table('realisasi_program_kerjas', function (Blueprint $table) {
            $table->dropColumn(['nama_bank', 'nomor_rekening', 'nama_pemilik_rekening']);
        });
    }

    public function down(): void
    {
        Schema::table('realisasi_program_kerjas', function (Blueprint $table) {
            $table->string('nama_bank')->nullable()->after('metode_pembayaran');
            $table->string('nomor_rekening')->nullable()->after('nama_bank');
            $table->string('nama_pemilik_rekening')->nullable()->after('nomor_rekening');
        });

        DB::table('realisasi_program_kerjas as realisasi')
            ->join('rekening_banks as rekening', 'rekening.id', '=', 'realisasi.rekening_bank_id')
            ->join('banks as bank', 'bank.id', '=', 'rekening.bank_id')
            ->update([
                'realisasi.nama_bank' => DB::raw('bank.name'),
                'realisasi.nomor_rekening' => DB::raw('rekening.nomor_rekening'),
                'realisasi.nama_pemilik_rekening' => DB::raw('rekening.atas_nama'),
            ]);

        Schema::table('realisasi_program_kerjas', function (Blueprint $table) {
            $table->dropForeign(['rekening_bank_id']);
        });

        Schema::table('realisasi_program_kerjas', function (Blueprint $table) {
            $table->dropColumn('rekening_bank_id');
        });
    }

    /**
     * Rekening yang sebelumnya ditulis lepas pada realisasi dipindahkan menjadi
     * record master Rekening Bank milik unit kerja pengaju, lalu ditautkan kembali.
     */
    private function pindahkanRekeningLepas(): void
    {
        DB::table('realisasi_program_kerjas')
            ->whereNotNull('nomor_rekening')
            ->orderBy('id')
            ->each(function (object $realisasi): void {
                $namaBank = $realisasi->nama_bank ?: 'Bank Lainnya';

                $bankId = DB::table('banks')->where('name', $namaBank)->value('id')
                    ?? DB::table('banks')->insertGetId([
                        'name' => $namaBank,
                        'slug' => Str::slug($namaBank),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                $unitKerjaId = DB::table('pengajuan_program_kerjas')
                    ->where('id', $realisasi->pengajuan_program_kerja_id)
                    ->value('unit_kerja_id');

                $rekeningId = DB::table('rekening_banks')
                    ->where('bank_id', $bankId)
                    ->where('nomor_rekening', $realisasi->nomor_rekening)
                    ->value('id')
                    ?? DB::table('rekening_banks')->insertGetId([
                        'bank_id' => $bankId,
                        'unit_kerja_id' => $unitKerjaId,
                        'nomor_rekening' => $realisasi->nomor_rekening,
                        'atas_nama' => $realisasi->nama_pemilik_rekening ?: 'Tidak diketahui',
                        'is_utama' => false,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                DB::table('realisasi_program_kerjas')
                    ->where('id', $realisasi->id)
                    ->update(['rekening_bank_id' => $rekeningId]);
            });
    }
};
