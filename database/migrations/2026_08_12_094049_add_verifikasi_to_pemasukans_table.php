<?php

use App\Enums\EnumStatusPemasukan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pemasukans', function (Blueprint $table): void {
            $table->string('status')->default(EnumStatusPemasukan::Draft->value)->index()->after('keterangan');
            $table->foreignId('user_id')->nullable()->after('unit_kerja_id')->constrained('users')->nullOnDelete();
            $table->text('catatan_verifikasi')->nullable()->after('status');
            $table->dateTime('disetujui_rektor_at')->nullable()->after('catatan_verifikasi');
            $table->foreignId('rektor_id')->nullable()->after('disetujui_rektor_at')->constrained('users')->nullOnDelete();
            $table->dateTime('disetujui_wakil_at')->nullable()->after('rektor_id');
            $table->foreignId('wakil_id')->nullable()->after('disetujui_wakil_at')->constrained('users')->nullOnDelete();
            $table->dateTime('disetujui_keuangan_at')->nullable()->after('wakil_id');
            $table->foreignId('keuangan_id')->nullable()->after('disetujui_keuangan_at')->constrained('users')->nullOnDelete();
            $table->json('bukti_path')->nullable()->after('keuangan_id');
            $table->json('bukti_original_names')->nullable()->after('bukti_path');
            $table->dateTime('bukti_diserahkan_at')->nullable()->after('bukti_original_names');
            $table->dateTime('divalidasi_at')->nullable()->after('bukti_diserahkan_at');
        });

        // Pemasukan yang sudah tercatat sebelum alur verifikasi ada dianggap sah apa
        // adanya. Tanpa backfill ini semuanya berstatus draf dan hilang dari Buku
        // Anggaran begitu penyaringan status diaktifkan.
        DB::table('pemasukans')->update([
            'status' => EnumStatusPemasukan::Valid->value,
            'divalidasi_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('pemasukans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('rektor_id');
            $table->dropConstrainedForeignId('wakil_id');
            $table->dropConstrainedForeignId('keuangan_id');
            $table->dropColumn([
                'status',
                'catatan_verifikasi',
                'disetujui_rektor_at',
                'disetujui_wakil_at',
                'disetujui_keuangan_at',
                'bukti_path',
                'bukti_original_names',
                'bukti_diserahkan_at',
                'divalidasi_at',
            ]);
        });
    }
};
