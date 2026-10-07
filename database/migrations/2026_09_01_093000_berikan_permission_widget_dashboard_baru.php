<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Dashboard bertambah empat kartu: periode berjalan, sisa waktu tahun kerja, ringkasan
 * program kerja, dan aksi cepat.
 *
 * Permission-nya diberikan ke role yang memang sudah melihat widget dashboard, supaya
 * kartunya langsung tampil tanpa perlu membuka dan menyimpan ulang tiap role. Aksi di
 * dalam kartu "Aksi Cepat" tetap tunduk pada hak akses masing-masing menu, jadi
 * permission ini hanya menentukan kartunya terlihat atau tidak.
 */
return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private const PERMISSION_BARU = [
        'view_widget_periode_berjalan',
        'view_widget_sisa_waktu_tahun_kerja',
        'view_widget_ringkasan_program_kerja',
        'view_widget_aksi_cepat',
    ];

    public function up(): void
    {
        $roleIds = $this->roleYangMelihatWidget();

        foreach (self::PERMISSION_BARU as $nama) {
            $permissionId = $this->idPermission($nama);

            foreach ($roleIds as $roleId) {
                DB::table('role_has_permissions')->updateOrInsert([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('name', self::PERMISSION_BARU)->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Role yang sudah boleh melihat widget dashboard mana pun.
     *
     * @return array<int, int>
     */
    private function roleYangMelihatWidget(): array
    {
        return DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('permissions.name', 'like', 'view_widget_%')
            ->distinct()
            ->pluck('role_has_permissions.role_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function idPermission(string $nama): int
    {
        $permission = DB::table('permissions')
            ->where('name', $nama)
            ->where('guard_name', 'web')
            ->first();

        if ($permission !== null) {
            return (int) $permission->id;
        }

        return (int) DB::table('permissions')->insertGetId([
            'name' => $nama,
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
