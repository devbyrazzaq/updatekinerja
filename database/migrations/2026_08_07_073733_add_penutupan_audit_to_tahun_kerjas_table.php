<?php

use App\Enums\EnumStatusTahunKerja;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jejak penutupan dan penguncian tahun kerja. Keduanya adalah keputusan yang
     * menghentikan pekerjaan seluruh unit kerja, jadi waktu dan pelakunya dicatat
     * agar bisa ditelusuri kembali.
     */
    public function up(): void
    {
        Schema::table('tahun_kerjas', function (Blueprint $table) {
            $table->dateTime('ditutup_pada')->nullable()->after('status');

            $table->foreignId('ditutup_oleh_id')
                ->nullable()
                ->after('ditutup_pada')
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('dikunci_pada')->nullable()->after('ditutup_oleh_id');

            $table->foreignId('dikunci_oleh_id')
                ->nullable()
                ->after('dikunci_pada')
                ->constrained('users')
                ->nullOnDelete();
        });

        // Tahun yang sudah berstatus Penutupan diberi perkiraan waktu tutup dari
        // perubahan terakhirnya. Status Selesai sengaja dilewati: status itu juga
        // menampung tahun kerja yang belum pernah dijalankan, sehingga tahun yang
        // benar-benar dikunci tidak bisa dibedakan dari yang belum pernah dipakai.
        DB::table('tahun_kerjas')
            ->where('status', EnumStatusTahunKerja::Penutupan->value)
            ->update(['ditutup_pada' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('tahun_kerjas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ditutup_oleh_id');
            $table->dropConstrainedForeignId('dikunci_oleh_id');
            $table->dropColumn(['ditutup_pada', 'dikunci_pada']);
        });
    }
};
