<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Pengaturan Sistem kini berupa cluster berisi beberapa halaman, masing-masing dengan
 * permission sendiri. Role yang sebelumnya boleh membuka halaman Pengaturan Sistem
 * tunggal mendapat seluruh permission halaman barunya, supaya aksesnya tidak hilang
 * tanpa perlu membuka dan menyimpan ulang tiap role. Permission lamanya lalu dibuang.
 */
return new class extends Migration
{
    private const PERMISSION_LAMA = 'view_page_pengaturan_sistem';

    /**
     * @var array<int, string>
     */
    private const PERMISSION_BARU = [
        'view_page_identitas_aplikasi',
        'view_page_halaman_masuk',
        'view_page_penanda_tangan_laporan',
        'view_page_periode_jabatan',
        'view_page_aturan_realisasi',
        'view_page_berkas_unggahan',
    ];

    public function up(): void
    {
        $lama = $this->permission(self::PERMISSION_LAMA);

        if ($lama === null) {
            return;
        }

        $roleIds = DB::table('role_has_permissions')->where('permission_id', $lama)->pluck('role_id');
        $modelPemilik = DB::table('model_has_permissions')->where('permission_id', $lama)->get(['model_type', 'model_id']);

        foreach (self::PERMISSION_BARU as $nama) {
            $permissionId = $this->permission($nama) ?? $this->buatPermission($nama);

            foreach ($roleIds as $roleId) {
                DB::table('role_has_permissions')->updateOrInsert([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }

            foreach ($modelPemilik as $pemilik) {
                DB::table('model_has_permissions')->updateOrInsert([
                    'permission_id' => $permissionId,
                    'model_type' => $pemilik->model_type,
                    'model_id' => $pemilik->model_id,
                ]);
            }
        }

        $this->hapusPermission([$lama]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $baru = DB::table('permissions')->whereIn('name', self::PERMISSION_BARU)->pluck('id');

        if ($baru->isEmpty()) {
            return;
        }

        $lama = $this->permission(self::PERMISSION_LAMA) ?? $this->buatPermission(self::PERMISSION_LAMA);

        DB::table('role_has_permissions')
            ->whereIn('permission_id', $baru)
            ->distinct()
            ->pluck('role_id')
            ->each(fn (mixed $roleId) => DB::table('role_has_permissions')->updateOrInsert([
                'permission_id' => $lama,
                'role_id' => $roleId,
            ]));

        $this->hapusPermission($baru->all());

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

    /**
     * @param  array<int, int>  $ids
     */
    private function hapusPermission(array $ids): void
    {
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
