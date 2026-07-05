<?php

namespace Database\Seeders;

use App\Enums\EnumRole;
use App\Services\PermissionRegistrar as ResourcePermissionRegistrar;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Bersihkan cache permission Spatie sebelum seeding.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 1. Buat semua role dari enum (project yang menentukan daftarnya).
        foreach (EnumRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }

        // 2. Pindai semua Resource/Page/Widget + custom (termasuk bypass_data_scope)
        //    dan buat record permission yang belum ada.
        ResourcePermissionRegistrar::syncToDatabase();

        // 3. WAJIB: Super Admin mendapat SEMUA permission (termasuk bypass_data_scope)
        //    sehingga bisa mengakses seluruh fitur.
        Role::findByName(EnumRole::SuperAdmin->value, 'web')->syncPermissions(Permission::all());
    }
}
