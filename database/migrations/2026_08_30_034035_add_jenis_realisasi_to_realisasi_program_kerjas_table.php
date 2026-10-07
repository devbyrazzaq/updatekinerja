<?php

use App\Enums\EnumJenisRealisasi;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda jenis realisasi beserta pencatatnya. Capaian yang dicatat langsung dari
 * halaman Monitoring Program Kerja tidak melewati alur pengajuan-pencairan, sehingga
 * perlu dibedakan dari realisasi beranggaran agar tampilannya tidak menyesatkan.
 *
 * Data lama ditandai lewat riwayatnya: hanya realisasi yang lognya menyebut
 * pencatatan dari Monitoring yang berjenis tanpa anggaran, sekaligus menjadi sumber
 * siapa pencatatnya. Menebak dari `anggaran_digunakan` nol tidak dipakai karena
 * realisasi beranggaran pun bisa bernilai nol.
 */
return new class extends Migration
{
    private const PENANDA_LOG = 'dicatat langsung dari Monitoring Program Kerja';

    public function up(): void
    {
        Schema::table('realisasi_program_kerjas', function (Blueprint $table) {
            $table->string('jenis_realisasi')->default(EnumJenisRealisasi::Anggaran->value)->after('name');
            $table->foreignId('dicatat_oleh_id')->nullable()->after('verifikator_laporan_id')->constrained('users')->nullOnDelete();
        });

        $logs = DB::table('realisasi_program_kerja_logs')
            ->where('description', 'like', '%'.self::PENANDA_LOG.'%')
            ->orderBy('id')
            ->get(['realisasi_program_kerja_id', 'user_id']);

        foreach ($logs as $log) {
            DB::table('realisasi_program_kerjas')
                ->where('id', $log->realisasi_program_kerja_id)
                ->update([
                    'jenis_realisasi' => EnumJenisRealisasi::TanpaAnggaran->value,
                    'dicatat_oleh_id' => $log->user_id,
                    // Status anggaran "habis" sempat ditulis untuk capaian tanpa anggaran,
                    // padahal tidak ada anggaran yang diserap sama sekali.
                    'status_anggaran' => null,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('realisasi_program_kerjas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dicatat_oleh_id');
            $table->dropColumn('jenis_realisasi');
        });
    }
};
