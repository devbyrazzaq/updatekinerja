<?php

namespace Database\Seeders;

use App\Enums\EnumRole;
use App\Models\UnitKerja;
use App\Services\PermissionRegistrar as ResourcePermissionRegistrar;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Satu role pembatas data untuk tiap unit kerja aktif, mis. "Unit: Perpustakaan".
 *
 * Role ini sengaja tidak memuat hak akses menu apa pun — isinya hanya permission
 * scope `view_data_unit_{id}` — sehingga bisa ditumpuk bersama role fungsional
 * (mis. Pimpinan Unit) dan bisa diberikan lebih dari satu kepada satu pengguna.
 * Pengguna berunit ganda lalu berpindah unit lewat pengalih di topbar.
 */
class UnitKerjaScopeRoleSeeder extends Seeder
{
    public function run(): void
    {
        $prefix = ResourcePermissionRegistrar::dataScopePermissionPrefix('unit');

        foreach (UnitKerja::where('is_active', true)->orderBy('name')->get() as $unitKerja) {
            $permission = Permission::findOrCreate($prefix.$unitKerja->id, 'web');

            Role::findOrCreate(EnumRole::unitScopeName($unitKerja->name), 'web')
                ->syncPermissions([$permission]);
        }
    }
}
