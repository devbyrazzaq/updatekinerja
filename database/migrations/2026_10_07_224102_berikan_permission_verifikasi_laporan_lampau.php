<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Laporan realisasi tahun Penutupan kini diverifikasi lewat menu baru Verifikasi
 * Laporan Lampau. Role yang sudah memegang permission Verifikasi Laporan mendapat
 * padanannya di menu baru, supaya verifikator yang sama langsung bisa menuntaskan
 * laporan lampau tanpa perlu membuka dan menyimpan ulang tiap role.
 */
return new class extends Migration
{
    /**
     * Permission Verifikasi Laporan => padanannya di Verifikasi Laporan Lampau.
     *
     * @var array<string, string>
     */
    private const PADANAN = [
        'view_any_verifikasi_laporan' => 'view_any_verifikasi_laporan_lampau',
        'view_verifikasi_laporan' => 'view_verifikasi_laporan_lampau',
        'verifikasi_verifikasi_laporan' => 'verifikasi_verifikasi_laporan_lampau',
    ];

    public function up(): void
    {
        foreach (self::PADANAN as $asal => $tujuan) {
            $asalId = $this->permission($asal);

            if ($asalId === null) {
                continue;
            }

            $tujuanId = $this->permission($tujuan) ?? $this->buatPermission($tujuan);

            DB::table('role_has_permissions')
                ->where('permission_id', $asalId)
                ->pluck('role_id')
                ->each(fn (mixed $roleId) => DB::table('role_has_permissions')->updateOrInsert([
                    'permission_id' => $tujuanId,
                    'role_id' => $roleId,
                ]));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('name', array_values(self::PADANAN))->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function permission(string $nama): ?int
    {
        $id = DB::table('permissions')->where('name', $nama)->where('guard_name', 'web')->value('id');

        return $id === null ? null : (int) $id;
    }

    private function buatPermission(string $nama): int
    {
        return (int) DB::table('permissions')->insertGetId([
            'name' => $nama,
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
