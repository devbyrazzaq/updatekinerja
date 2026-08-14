<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            // Unit kerja disemai lebih dulu karena RoleSeeder membangun satu role
            // pembatas data untuk tiap unit yang ada.
            UnitKerjaSeeder::class,
            RoleSeeder::class,
            MasterDataSeeder::class,
            UserSeeder::class,
            // Akun pimpinan tiap unit kerja; bisa dibatalkan lewat
            // `php artisan seed:hapus-pimpinan-unit`.
            PimpinanUnitSeeder::class,
            DosenSeeder::class,
            ProgramKerjaSeeder::class,
        ]);
    }
}
