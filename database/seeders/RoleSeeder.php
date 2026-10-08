<?php

namespace Database\Seeders;

use App\Enums\EnumPermission;
use App\Enums\EnumRole;
use App\Filament\Pages\BukuAnggaran;
use App\Filament\Pages\BukuAnggaranKeseluruhan;
use App\Filament\Pages\MonitoringProgramKerja;
use App\Filament\Pages\MonitoringRealisasi;
use App\Filament\Pages\PerbandinganMonitoring;
use App\Filament\Resources\JadwalPencairans\JadwalPencairanResource;
use App\Filament\Resources\RekeningBanks\RekeningBankResource;
use App\Filament\Resources\VerifikasiBiroKeuangans\VerifikasiBiroKeuanganResource;
use App\Filament\Resources\VerifikasiKeuanganPemasukans\VerifikasiKeuanganPemasukanResource;
use App\Filament\Resources\VerifikasiLaporanLampaus\VerifikasiLaporanLampauResource;
use App\Filament\Resources\VerifikasiLaporans\VerifikasiLaporanResource;
use App\Filament\Resources\VerifikasiRektors\VerifikasiRektorResource;
use App\Filament\Resources\VerifikasiWakilPemasukans\VerifikasiWakilPemasukanResource;
use App\Filament\Resources\VerifikasiWakilRektors\VerifikasiWakilRektorResource;
use App\Filament\Widgets\AksiCepatWidget;
use App\Filament\Widgets\MenungguKeputusanWidget;
use App\Filament\Widgets\PenyerapanBulananWidget;
use App\Filament\Widgets\PeriodeBerjalanWidget;
use App\Filament\Widgets\PerluTindakLanjutWidget;
use App\Filament\Widgets\PintasanMenuWidget;
use App\Filament\Widgets\RingkasanAnggaranWidget;
use App\Filament\Widgets\RingkasanProgramKerjaWidget;
use App\Filament\Widgets\SisaWaktuTahunKerjaWidget;
use App\Filament\Widgets\StatusRealisasiWidget;
use App\Filament\Widgets\TentangSistemWidget;
use App\Services\PermissionRegistrar as ResourcePermissionRegistrar;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role beserta hak aksesnya. Hak akses dirakit dari grup menu sidebar dan kelas menu,
 * sehingga menu baru yang ditambahkan ke sebuah grup ikut terbawa tanpa perlu
 * mendaftar nama permission satu per satu.
 *
 * Istilah yang dipakai:
 * - "penuh": seluruh permission menu (lihat, tambah, ubah, hapus, impor, ekspor, aksi
 *   khusus seperti verifikasi).
 * - "baca": hanya permission berawalan {@see self::AWALAN_HAK_BACA} — melihat daftar &
 *   detail, mengekspor, dan mengunduh laporan, tanpa mengubah data.
 */
class RoleSeeder extends Seeder
{
    /**
     * Awalan permission yang tidak mengubah data, untuk menu berhak akses "baca".
     * `view_` sekaligus mencakup `view_any_` dan `view_page_`.
     *
     * @var array<int, string>
     */
    protected const AWALAN_HAK_BACA = ['view_', 'export_', 'report_'];

    /**
     * Widget dashboard pimpinan universitas (Rektor dan Wakil Rektor). Isi kartu
     * "Menunggu Keputusan" dan "Perlu Tindak Lanjut" tetap mengikuti menu verifikasi
     * yang boleh dibuka, sehingga tiap role hanya melihat antreannya sendiri.
     *
     * @var array<int, class-string>
     */
    protected const WIDGET_PIMPINAN_UNIVERSITAS = [
        TentangSistemWidget::class,
        PeriodeBerjalanWidget::class,
        SisaWaktuTahunKerjaWidget::class,
        RingkasanAnggaranWidget::class,
        RingkasanProgramKerjaWidget::class,
        MenungguKeputusanWidget::class,
        PerluTindakLanjutWidget::class,
        PintasanMenuWidget::class,
        PenyerapanBulananWidget::class,
        StatusRealisasiWidget::class,
    ];

    /**
     * @var array<int, class-string>
     */
    protected const WIDGET_BIRO_KEUANGAN = [
        TentangSistemWidget::class,
        PeriodeBerjalanWidget::class,
        SisaWaktuTahunKerjaWidget::class,
        RingkasanAnggaranWidget::class,
        MenungguKeputusanWidget::class,
        PintasanMenuWidget::class,
        PenyerapanBulananWidget::class,
    ];

    /**
     * Angka widget Pimpinan Unit dihitung dari unit kerjanya sendiri.
     *
     * @var array<int, class-string>
     */
    protected const WIDGET_PIMPINAN_UNIT = [
        TentangSistemWidget::class,
        PeriodeBerjalanWidget::class,
        SisaWaktuTahunKerjaWidget::class,
        RingkasanAnggaranWidget::class,
        RingkasanProgramKerjaWidget::class,
        AksiCepatWidget::class,
        PintasanMenuWidget::class,
        PenyerapanBulananWidget::class,
    ];

    /**
     * Menu satuan Pimpinan Unit di luar grup Pelaksanaan, Pemasukan, dan Perencanaan,
     * beserta ability yang diberikan (`null` = seluruh permission menu tersebut).
     *
     * Datanya tetap dibatasi unit kerja: Rekening Bank hanya menampilkan rekening
     * unitnya (plus rekening umum) dan hanya rekening unitnya yang bisa ditambah, diubah,
     * atau dihapus, sedangkan menu lainnya hanya menghitung unit yang boleh diakses.
     *
     * @var array<class-string, array<int, string>|null>
     */
    protected const MENU_PIMPINAN_UNIT = [
        // Tanpa hapus massal: aksi massal tidak memeriksa kepemilikan tiap rekening,
        // sehingga rekening umum bisa ikut terhapus.
        RekeningBankResource::class => ['view_any', 'view', 'create', 'update', 'delete'],
        BukuAnggaran::class => null,
        MonitoringProgramKerja::class => null,
        MonitoringRealisasi::class => null,
        PerbandinganMonitoring::class => null,
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

        // 4. Role fungsional. Tidak ada yang mendapat `bypass_data_scope`, sehingga
        //    menunya tetap dibatasi permission masing-masing.
        foreach ($this->hakAksesRole() as $role => $permissions) {
            Role::findByName($role, 'web')->syncPermissions(
                ResourcePermissionRegistrar::ensurePermissions($permissions),
            );
        }

        // 5. Role pembatas data per unit kerja, agar satu pengguna bisa diberi akses
        //    ke lebih dari satu unit lalu berpindah lewat pengalih unit di topbar.
        $this->call(UnitKerjaScopeRoleSeeder::class);
    }

    /**
     * Permission tiap role fungsional. Role yang tidak terdaftar di sini (mis. Admin,
     * Unit Kerja) tidak disentuh, sehingga hak akses yang diatur lewat menu Role & Akses
     * tetap utuh.
     *
     * @return array<string, array<int, string>>
     */
    protected function hakAksesRole(): array
    {
        // Pimpinan universitas & Biro Keuangan memantau seluruh unit kerja.
        $seluruhUnit = EnumPermission::ViewAllUnitData->value;

        $wakilRektor = [
            ...$this->grupPenuh(['Master Data', 'Anggaran', 'Pengaturan Sistem']),
            ...$this->grupBaca(['Program Kerja', 'Pelaksanaan', 'Perencanaan', 'Pemasukan', 'Monitoring', 'Pengguna']),
            ...$this->menuPenuh(static::WIDGET_PIMPINAN_UNIVERSITAS),
            $seluruhUnit,
        ];

        return [
            EnumRole::Rektor->value => [
                ...$this->grupPenuh([
                    'Master Data',
                    'Anggaran',
                    'Program Kerja',
                    'Verifikasi Pengajuan',
                    'Verifikasi Pengajuan Perencanaan',
                    'Pengaturan Sistem',
                ]),
                ...$this->grupBaca(['Pelaksanaan', 'Perencanaan', 'Pemasukan', 'Monitoring', 'Pengguna']),
                ...$this->menuPenuh([VerifikasiRektorResource::class]),
                ...$this->menuPenuh(static::WIDGET_PIMPINAN_UNIVERSITAS),
                $seluruhUnit,
            ],
            EnumRole::WakilRektorI->value => $wakilRektor,
            EnumRole::WakilRektorII->value => [
                ...$wakilRektor,
                ...$this->menuPenuh([
                    VerifikasiWakilRektorResource::class,
                    VerifikasiWakilPemasukanResource::class,
                ]),
            ],
            EnumRole::WakilRektorIII->value => $wakilRektor,
            EnumRole::BiroKeuangan->value => [
                ...$this->grupPenuh(['Anggaran']),
                ...$this->menuPenuh([
                    VerifikasiKeuanganPemasukanResource::class,
                    VerifikasiBiroKeuanganResource::class,
                    VerifikasiLaporanResource::class,
                    VerifikasiLaporanLampauResource::class,
                    JadwalPencairanResource::class,
                    BukuAnggaranKeseluruhan::class,
                ]),
                ...$this->menuPenuh(static::WIDGET_BIRO_KEUANGAN),
                $seluruhUnit,
            ],
            // Tanpa permission seluruh unit: datanya dibatasi unit kerjanya sendiri.
            EnumRole::PimpinanUnit->value => [
                ...$this->grupPenuh(['Pelaksanaan', 'Pemasukan', 'Perencanaan']),
                ...ResourcePermissionRegistrar::permissionNamesForMenus(static::MENU_PIMPINAN_UNIT),
                ...$this->menuPenuh(static::WIDGET_PIMPINAN_UNIT),
            ],
        ];
    }

    /**
     * @param  array<int, string>  $navGroups
     * @return array<int, string>
     */
    protected function grupPenuh(array $navGroups): array
    {
        return ResourcePermissionRegistrar::permissionNamesForNavGroups($navGroups);
    }

    /**
     * @param  array<int, string>  $navGroups
     * @return array<int, string>
     */
    protected function grupBaca(array $navGroups): array
    {
        return array_values(array_filter(
            ResourcePermissionRegistrar::permissionNamesForNavGroups($navGroups),
            fn (string $permission): bool => Str::startsWith($permission, static::AWALAN_HAK_BACA),
        ));
    }

    /**
     * @param  array<int, class-string>  $menus
     * @return array<int, string>
     */
    protected function menuPenuh(array $menus): array
    {
        return ResourcePermissionRegistrar::permissionNamesForMenus(array_fill_keys($menus, null));
    }
}
