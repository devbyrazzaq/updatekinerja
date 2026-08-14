<?php

namespace Database\Seeders;

use App\Enums\EnumRole;
use App\Filament\Pages\BukuAnggaran;
use App\Filament\Pages\MonitoringProgramKerja;
use App\Filament\Pages\MonitoringRealisasi;
use App\Filament\Resources\RekeningBanks\RekeningBankResource;
use App\Services\PermissionRegistrar as ResourcePermissionRegistrar;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Grup menu yang boleh diakses Pimpinan Unit. Hak aksesnya dirakit dari grup ini
     * sehingga menu baru yang ditambahkan ke salah satu grup ikut terbawa tanpa perlu
     * mendaftar nama permission satu per satu di sini.
     *
     * @var array<int, string>
     */
    protected const GRUP_MENU_PIMPINAN_UNIT = [
        'Pelaksanaan',
        'Pemasukan',
        'Perencanaan',
    ];

    /**
     * Menu satuan di luar grup di atas yang tetap boleh diakses Pimpinan Unit, beserta
     * ability yang diberikan (`null` = seluruh permission menu tersebut). Dipakai untuk
     * menu pada grup yang tidak boleh dibuka seutuhnya — grup "Master Data" dan
     * "Anggaran" masih memuat menu lain yang tetap menjadi wewenang admin.
     *
     * Datanya tetap dibatasi unit kerja: Rekening Bank hanya menampilkan rekening
     * unitnya (plus rekening umum) dan hanya bisa ditambah untuk unit tersebut,
     * sedangkan Buku Anggaran Unit, Monitoring Program Kerja, dan Monitoring Realisasi
     * hanya menghitung unit yang boleh diakses.
     *
     * @var array<class-string, array<int, string>|null>
     */
    protected const MENU_PIMPINAN_UNIT = [
        // Tanpa hapus: rekening bank sudah menjadi rujukan penjadwalan pencairan,
        // sehingga penghapusannya tetap menjadi wewenang admin.
        RekeningBankResource::class => ['view_any', 'view', 'create', 'update'],
        BukuAnggaran::class => null,
        MonitoringProgramKerja::class => null,
        MonitoringRealisasi::class => null,
    ];

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

        // 4. Pimpinan Unit: menu pada grup Pelaksanaan, Pemasukan, dan Perencanaan,
        //    ditambah menu satuan Rekening Bank dan Buku Anggaran Unit. Tanpa
        //    `bypass_data_scope`, sehingga datanya tetap dibatasi unit kerjanya.
        Role::findByName(EnumRole::PimpinanUnit->value, 'web')->syncPermissions(
            ResourcePermissionRegistrar::ensurePermissions([
                ...ResourcePermissionRegistrar::permissionNamesForNavGroups(static::GRUP_MENU_PIMPINAN_UNIT),
                ...ResourcePermissionRegistrar::permissionNamesForMenus(static::MENU_PIMPINAN_UNIT),
            ]),
        );

        // 5. Role pembatas data per unit kerja, agar satu pengguna bisa diberi akses
        //    ke lebih dari satu unit lalu berpindah lewat pengalih unit di topbar.
        $this->call(UnitKerjaScopeRoleSeeder::class);
    }
}
