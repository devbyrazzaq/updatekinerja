<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Widget "Perlu Tindakan Anda" dipecah menjadi dua kartu berdampingan — "Menunggu
 * Keputusan Anda" dan "Perlu Ditindaklanjuti" — dan dashboard mendapat kartu baru
 * "Tentang Sistem" di samping kartu akun.
 *
 * Permission-nya ikut berganti nama, jadi role yang tadinya boleh melihat antrean tugas
 * dipindahkan ke kedua permission penggantinya. Tanpa ini, widgetnya menghilang dari
 * dashboard sampai tiap role dibuka dan disimpan ulang. Kartu "Tentang Sistem" diberikan
 * ke role yang memang sudah melihat widget dashboard.
 */
return new class extends Migration
{
    private const PERMISSION_LAMA = 'view_widget_antrean_tugas';

    /**
     * @var array<int, string>
     */
    private const PERMISSION_BARU = [
        'view_widget_menunggu_keputusan',
        'view_widget_perlu_tindak_lanjut',
    ];

    private const PERMISSION_TENTANG_SISTEM = 'view_widget_tentang_sistem';

    public function up(): void
    {
        foreach (self::PERMISSION_BARU as $nama) {
            $this->wariskan(self::PERMISSION_LAMA, $nama);
        }

        $this->berikanKeRoleWidget(self::PERMISSION_TENTANG_SISTEM);

        $this->hapus(self::PERMISSION_LAMA);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (self::PERMISSION_BARU as $nama) {
            $this->wariskan($nama, self::PERMISSION_LAMA);
            $this->hapus($nama);
        }

        $this->hapus(self::PERMISSION_TENTANG_SISTEM);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Menyalin pemberian sebuah permission ke permission lain, baik yang ditautkan ke
     * role maupun langsung ke pengguna.
     */
    private function wariskan(string $dari, string $ke): void
    {
        $asal = DB::table('permissions')->where('name', $dari)->first();

        if ($asal === null) {
            return;
        }

        $tujuanId = $this->idPermission($ke, $asal->guard_name);

        $roleIds = DB::table('role_has_permissions')
            ->where('permission_id', $asal->id)
            ->pluck('role_id');

        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->updateOrInsert([
                'permission_id' => $tujuanId,
                'role_id' => $roleId,
            ]);
        }

        $pemilik = DB::table('model_has_permissions')
            ->where('permission_id', $asal->id)
            ->get();

        foreach ($pemilik as $baris) {
            DB::table('model_has_permissions')->updateOrInsert([
                'permission_id' => $tujuanId,
                'model_type' => $baris->model_type,
                'model_id' => $baris->model_id,
            ]);
        }
    }

    /**
     * Memberikan sebuah permission ke semua role yang sudah boleh melihat widget mana pun
     * pada dashboard.
     */
    private function berikanKeRoleWidget(string $nama): void
    {
        $permissionId = $this->idPermission($nama, 'web');

        $roleIds = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('permissions.name', 'like', 'view_widget_%')
            ->distinct()
            ->pluck('role_has_permissions.role_id');

        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->updateOrInsert([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }

    private function idPermission(string $nama, string $guard): int
    {
        $permission = DB::table('permissions')
            ->where('name', $nama)
            ->where('guard_name', $guard)
            ->first();

        if ($permission !== null) {
            return (int) $permission->id;
        }

        return (int) DB::table('permissions')->insertGetId([
            'name' => $nama,
            'guard_name' => $guard,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Membuang permission beserta penautannya ke role dan pengguna.
     */
    private function hapus(string $nama): void
    {
        $ids = DB::table('permissions')->where('name', $nama)->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
