<?php

use App\Enums\EnumStatusPemasukan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap Verifikasi Rektor dihapus dari alur pemasukan: pemasukan yang diajukan unit
 * kerja langsung masuk antrean Wakil Rektor.
 */
return new class extends Migration
{
    /**
     * Permission milik Resource verifikasi Rektor pemasukan yang ikut dihapus.
     *
     * @var array<int, string>
     */
    private const PERMISSION_LAMA = [
        'view_any_verifikasi_rektor_pemasukan',
        'view_verifikasi_rektor_pemasukan',
        'verifikasi_verifikasi_rektor_pemasukan',
    ];

    public function up(): void
    {
        // Pemasukan yang sedang menunggu Rektor dikembalikan ke status "diajukan" —
        // status itu kini berarti menunggu Wakil Rektor.
        DB::table('pemasukans')
            ->where('status', 'verifikasi_rektor')
            ->update(['status' => EnumStatusPemasukan::Diajukan->value]);

        DB::table('pemasukan_logs')
            ->where('status', 'verifikasi_rektor')
            ->update(['status' => EnumStatusPemasukan::Diajukan->value]);

        Schema::table('pemasukans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rektor_id');
            $table->dropColumn('disetujui_rektor_at');
        });

        $this->hapusPermission();
    }

    public function down(): void
    {
        Schema::table('pemasukans', function (Blueprint $table): void {
            $table->dateTime('disetujui_rektor_at')->nullable()->after('catatan_verifikasi');
            $table->foreignId('rektor_id')->nullable()->after('disetujui_rektor_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Membuang permission Resource yang sudah tidak ada beserta penautannya ke role.
     */
    private function hapusPermission(): void
    {
        $ids = DB::table('permissions')->whereIn('name', self::PERMISSION_LAMA)->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
