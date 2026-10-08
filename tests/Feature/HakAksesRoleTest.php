<?php

namespace Tests\Feature;

use App\Enums\EnumRole;
use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Actions\AjukanPemasukanAction;
use App\Filament\Actions\AjukanRealisasiAction;
use App\Filament\Clusters\PengaturanSistem\Pages\IdentitasAplikasi;
use App\Filament\Pages\BukuAnggaran;
use App\Filament\Pages\BukuAnggaranKeseluruhan;
use App\Filament\Pages\MonitoringProgramKerja;
use App\Filament\Pages\MonitoringRealisasi;
use App\Filament\Pages\PerbandinganMonitoring;
use App\Filament\Resources\AcuanProgramKerjas\AcuanProgramKerjaResource;
use App\Filament\Resources\DaftarProgramKerjas\Pages\ListDaftarProgramKerjas;
use App\Filament\Resources\Dosens\DosenResource;
use App\Filament\Resources\JadwalPencairans\JadwalPencairanResource;
use App\Filament\Resources\PaguAnggarans\PaguAnggaranResource;
use App\Filament\Resources\Pemasukans\PemasukanResource;
use App\Filament\Resources\PengajuanProgramKerjas\Pages\ListPengajuanProgramKerjas;
use App\Filament\Resources\PengajuanProgramKerjas\PengajuanProgramKerjaResource;
use App\Filament\Resources\RealisasiProgramKerjas\RealisasiProgramKerjaResource;
use App\Filament\Resources\RekeningBanks\RekeningBankResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\UnitKerjas\UnitKerjaResource;
use App\Filament\Resources\VerifikasiBiroKeuangans\VerifikasiBiroKeuanganResource;
use App\Filament\Resources\VerifikasiKeuanganPemasukans\VerifikasiKeuanganPemasukanResource;
use App\Filament\Resources\VerifikasiLaporanLampaus\VerifikasiLaporanLampauResource;
use App\Filament\Resources\VerifikasiLaporans\VerifikasiLaporanResource;
use App\Filament\Resources\VerifikasiPengajuans\VerifikasiPengajuanResource;
use App\Filament\Resources\VerifikasiRektors\VerifikasiRektorResource;
use App\Filament\Resources\VerifikasiWakilPemasukans\VerifikasiWakilPemasukanResource;
use App\Filament\Resources\VerifikasiWakilRektors\VerifikasiWakilRektorResource;
use App\Filament\Widgets\AksiCepatWidget;
use App\Filament\Widgets\MenungguKeputusanWidget;
use App\Filament\Widgets\RingkasanProgramKerjaWidget;
use App\Filament\Widgets\StatusRealisasiWidget;
use App\Models\Bidang;
use App\Models\Kategori;
use App\Models\Pemasukan;
use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use App\Models\Periode;
use App\Models\Program;
use App\Models\RealisasiProgramKerja;
use App\Models\RekeningBank;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\UnitKerjaAktif;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Hak akses tiap role fungsional sebagaimana disusun RoleSeeder: Rektor, Wakil
 * Rektor I–III, Biro Keuangan, dan Pimpinan Unit.
 */
class HakAksesRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function akun(EnumRole $role, ?UnitKerja $unitKerja = null): User
    {
        $user = User::factory()->create(['unit_kerja_id' => $unitKerja?->id]);
        $user->assignRole($role->value);

        return $user;
    }

    public function test_rektor_mengelola_master_data_anggaran_dan_program_kerja(): void
    {
        $this->actingAs($this->akun(EnumRole::Rektor));

        $this->assertTrue(UnitKerjaResource::canCreate());
        $this->assertTrue(PaguAnggaranResource::canCreate());
        $this->assertTrue(BukuAnggaran::canAccess());
        $this->assertTrue(AcuanProgramKerjaResource::canCreate());
        $this->assertTrue(IdentitasAplikasi::canAccess());
    }

    public function test_rektor_hanya_membaca_pelaksanaan_pemasukan_dan_pengguna(): void
    {
        $this->actingAs($this->akun(EnumRole::Rektor));

        $this->assertTrue(RealisasiProgramKerjaResource::canViewAny());
        $this->assertFalse(RealisasiProgramKerjaResource::canCreate());
        $this->assertTrue(PemasukanResource::canViewAny());
        $this->assertFalse(PemasukanResource::canCreate());
        $this->assertTrue(DosenResource::canViewAny());
        $this->assertFalse(DosenResource::canCreate());
        $this->assertFalse(RoleResource::canViewAny());
    }

    public function test_rektor_memegang_verifikasi_pengajuan_dan_verifikasi_rektor(): void
    {
        $this->actingAs($this->akun(EnumRole::Rektor));

        $this->assertTrue(VerifikasiPengajuanResource::canViewAny());
        $this->assertTrue(VerifikasiRektorResource::currentUserCanVerify());
        $this->assertFalse(VerifikasiWakilRektorResource::canViewAny());
        $this->assertFalse(VerifikasiBiroKeuanganResource::canViewAny());
        $this->assertFalse(JadwalPencairanResource::canViewAny());
    }

    /**
     * Monitoring cukup dibuka; mencatat capaian tetap pekerjaan unit kerja.
     */
    public function test_rektor_membuka_monitoring_tanpa_mencatat_capaian(): void
    {
        $rektor = $this->akun(EnumRole::Rektor);
        $this->actingAs($rektor);

        $this->assertTrue(MonitoringProgramKerja::canAccess());
        $this->assertTrue(MonitoringRealisasi::canAccess());
        $this->assertTrue(PerbandinganMonitoring::canAccess());
        $this->assertTrue(BukuAnggaranKeseluruhan::canAccess());
        $this->assertFalse($rektor->can(MonitoringProgramKerja::PERMISSION_CATAT_CAPAIAN));
    }

    public function test_dashboard_pimpinan_universitas(): void
    {
        $this->actingAs($this->akun(EnumRole::Rektor));

        $this->assertTrue(StatusRealisasiWidget::canView());
        $this->assertTrue(MenungguKeputusanWidget::canView());
        $this->assertFalse(AksiCepatWidget::canView());
    }

    public function test_hanya_wakil_rektor_ii_yang_memegang_verifikasi_wakil_rektor(): void
    {
        $this->actingAs($this->akun(EnumRole::WakilRektorII));

        $this->assertTrue(VerifikasiWakilRektorResource::currentUserCanVerify());
        $this->assertTrue(VerifikasiWakilPemasukanResource::currentUserCanVerify());
        $this->assertFalse(VerifikasiRektorResource::canViewAny());
        $this->assertFalse(VerifikasiPengajuanResource::canViewAny());

        foreach ([EnumRole::WakilRektorI, EnumRole::WakilRektorIII] as $role) {
            $this->actingAs($this->akun($role));

            $this->assertFalse(VerifikasiWakilRektorResource::canViewAny(), $role->value);
            $this->assertFalse(VerifikasiWakilPemasukanResource::canViewAny(), $role->value);
        }
    }

    public function test_wakil_rektor_hanya_membaca_program_kerja(): void
    {
        foreach ([EnumRole::WakilRektorI, EnumRole::WakilRektorII, EnumRole::WakilRektorIII] as $role) {
            $this->actingAs($this->akun($role));

            $this->assertTrue(UnitKerjaResource::canCreate(), $role->value);
            $this->assertTrue(PaguAnggaranResource::canCreate(), $role->value);
            $this->assertTrue(AcuanProgramKerjaResource::canViewAny(), $role->value);
            $this->assertFalse(AcuanProgramKerjaResource::canCreate(), $role->value);
            $this->assertTrue(MonitoringRealisasi::canAccess(), $role->value);
            $this->assertTrue(IdentitasAplikasi::canAccess(), $role->value);
        }
    }

    public function test_biro_keuangan_memegang_anggaran_verifikasi_keuangan_dan_pencairan(): void
    {
        $this->actingAs($this->akun(EnumRole::BiroKeuangan));

        $this->assertTrue(PaguAnggaranResource::canCreate());
        $this->assertTrue(VerifikasiKeuanganPemasukanResource::currentUserCanVerify());
        $this->assertTrue(VerifikasiBiroKeuanganResource::currentUserCanVerify());
        $this->assertTrue(VerifikasiLaporanResource::canViewAny());
        $this->assertTrue(VerifikasiLaporanLampauResource::canViewAny());
        $this->assertTrue(JadwalPencairanResource::canCreate());
        $this->assertTrue(BukuAnggaranKeseluruhan::canAccess());
        $this->assertTrue(MenungguKeputusanWidget::canView());

        $this->assertFalse(UnitKerjaResource::canViewAny());
        $this->assertFalse(RealisasiProgramKerjaResource::canViewAny());
        $this->assertFalse(VerifikasiRektorResource::canViewAny());
        $this->assertFalse(MonitoringProgramKerja::canAccess());
        $this->assertFalse(RingkasanProgramKerjaWidget::canView());
    }

    /**
     * Pimpinan universitas melihat data seluruh unit kerja tanpa akses penuh, sehingga
     * pengalih unit tidak perlu muncul; Pimpinan Unit tetap hanya melihat unitnya.
     */
    public function test_pimpinan_universitas_melihat_data_seluruh_unit_kerja(): void
    {
        [$unitA, $unitB] = UnitKerja::factory()->count(2)->create();
        RekeningBank::factory()->create(['unit_kerja_id' => $unitA->id]);
        RekeningBank::factory()->create(['unit_kerja_id' => $unitB->id]);

        $rektor = $this->akun(EnumRole::Rektor);
        $this->actingAs($rektor);

        $this->assertFalse($rektor->isPrivileged());
        $this->assertTrue($rektor->canViewAllUnitData());
        $this->assertSame(2, RekeningBankResource::getEloquentQuery()->count());
        $this->assertFalse(UnitKerjaAktif::dapatBerganti($rektor));

        $pimpinan = $this->akun(EnumRole::PimpinanUnit, $unitA);
        $this->actingAs($pimpinan);

        $this->assertFalse($pimpinan->canViewAllUnitData());
        $this->assertSame(1, RekeningBankResource::getEloquentQuery()->count());
    }

    /**
     * Akses seluruh unit melekat pada role: pengguna yang merangkap Biro Keuangan dan
     * Pimpinan Unit melihat seluruh unit di menu keuangan, tetapi menu Pimpinan Unit
     * (Pelaksanaan, Pemasukan, Rekening Bank) tetap dibatasi unitnya sendiri.
     */
    public function test_akses_seluruh_unit_tidak_menular_ke_menu_role_berlingkup_unit(): void
    {
        [$unitA, $unitB] = UnitKerja::factory()->count(2)->create();
        RekeningBank::factory()->create(['unit_kerja_id' => $unitA->id]);
        RekeningBank::factory()->create(['unit_kerja_id' => $unitB->id]);

        $rangkap = $this->akun(EnumRole::PimpinanUnit, $unitA);
        $rangkap->assignRole(EnumRole::BiroKeuangan->value);
        $this->actingAs($rangkap);

        $this->assertFalse($rangkap->canViewAllUnitData(RealisasiProgramKerjaResource::getPermissionName('view_any')));
        $this->assertFalse($rangkap->canViewAllUnitData(PengajuanProgramKerjaResource::getPermissionName('view_any')));
        $this->assertFalse($rangkap->canViewAllUnitData(PemasukanResource::getPermissionName('view_any')));
        $this->assertSame(1, RekeningBankResource::getEloquentQuery()->count());
        $this->assertSame(
            [$unitA->id],
            UnitKerjaAktif::batasiKueri(UnitKerja::query(), PengajuanProgramKerjaResource::getPermissionName('view_any'))->pluck('id')->all(),
        );

        $this->assertTrue($rangkap->canViewAllUnitData(BukuAnggaranKeseluruhan::getPagePermission()));
        $this->assertTrue($rangkap->canViewAllUnitData(JadwalPencairanResource::getPermissionName('view_any')));

        $this->assertFalse($rangkap->canViewAllUnitData());
        $this->assertSame($unitA->name, UnitKerjaAktif::namaCakupan($rangkap));
    }

    public function test_biro_keuangan_tanpa_role_lain_tetap_melihat_seluruh_unit(): void
    {
        $keuangan = $this->akun(EnumRole::BiroKeuangan, UnitKerja::factory()->create());

        $this->assertTrue($keuangan->canViewAllUnitData());
        $this->assertTrue($keuangan->canViewAllUnitData(BukuAnggaranKeseluruhan::getPagePermission()));
        $this->assertFalse($keuangan->canViewAllUnitData(RealisasiProgramKerjaResource::getPermissionName('view_any')));
    }

    /**
     * Aksi alur unit kerja menumpang hak ubah menunya, sehingga pemegang hak baca saja
     * tidak bisa mengajukan atas nama unit.
     */
    public function test_aksi_ajukan_tersembunyi_bagi_pemegang_hak_baca(): void
    {
        $unitKerja = UnitKerja::factory()->create();
        $realisasi = RealisasiProgramKerja::factory()->create();
        $pemasukan = Pemasukan::factory()->create(['unit_kerja_id' => $unitKerja->id]);

        $this->actingAs($this->akun(EnumRole::Rektor));

        $this->assertFalse(AjukanRealisasiAction::make()->record($realisasi)->isVisible());
        $this->assertFalse(AjukanPemasukanAction::make()->record($pemasukan)->isVisible());

        $this->actingAs($this->akun(EnumRole::PimpinanUnit, $unitKerja));

        $this->assertTrue(AjukanRealisasiAction::make()->record($realisasi)->isVisible());
        $this->assertTrue(AjukanPemasukanAction::make()->record($pemasukan)->isVisible());
    }

    public function test_aksi_ajukan_program_kerja_tersembunyi_bagi_pemegang_hak_baca(): void
    {
        $penawaran = $this->penawaranTahunBerjalan();
        $pengajuan = PengajuanProgramKerja::factory()->create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $penawaran->unit_kerja_id,
            'status' => EnumStatusPengajuan::Draft,
        ]);

        $this->actingAs($this->akun(EnumRole::Rektor));

        Livewire::test(ListDaftarProgramKerjas::class)
            ->assertActionHidden(TestAction::make('ajukan')->table($penawaran));
        Livewire::test(ListPengajuanProgramKerjas::class)
            ->assertActionHidden(TestAction::make('ajukan')->table($pengajuan));

        $this->actingAs($this->akun(EnumRole::PimpinanUnit, $penawaran->unitKerja));

        Livewire::test(ListDaftarProgramKerjas::class)
            ->assertActionVisible(TestAction::make('ajukan')->table($penawaran));
        Livewire::test(ListPengajuanProgramKerjas::class)
            ->assertActionVisible(TestAction::make('ajukan')->table($pengajuan));
    }

    /**
     * Pemegang role "Wakil Rektor" lama berpindah ke "Wakil Rektor II" dan langsung
     * menerima hak aksesnya.
     */
    public function test_migrasi_mengganti_nama_role_wakil_rektor_lama(): void
    {
        Role::findByName(EnumRole::WakilRektorII->value, 'web')->delete();
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'Wakil Rektor', 'guard_name' => 'web']));

        $migration = require database_path('migrations/2026_10_08_024601_sesuaikan_hak_akses_role_pimpinan.php');
        $migration->up();

        $user = $user->fresh();

        $this->assertTrue($user->hasRole(EnumRole::WakilRektorII->value));
        $this->assertFalse(Role::where('name', 'Wakil Rektor')->exists());
        $this->assertTrue($user->can(VerifikasiWakilRektorResource::getPermissionName('verifikasi')));
    }

    private function penawaranTahunBerjalan(): PenawaranProgramKerja
    {
        $periode = Periode::create(['name' => 'P1', 'start_datetime' => now(), 'end_datetime' => now()->addYear()]);
        $tahunKerja = TahunKerja::create([
            'periode_id' => $periode->id,
            'name' => 'TA 2026',
            'start_datetime' => now(),
            'end_datetime' => now()->addYear(),
            'status' => EnumStatusTahunKerja::Berjalan,
        ]);

        return PenawaranProgramKerja::create([
            'tahun_kerja_id' => $tahunKerja->id,
            'unit_kerja_id' => UnitKerja::create(['name' => 'Fakultas Teknik'])->id,
            'bidang_id' => Bidang::create(['code' => 'B1', 'name' => 'Pendidikan'])->id,
            'kategori_id' => Kategori::create(['code' => 'K1', 'name' => 'Rutin'])->id,
            'program_id' => Program::create(['name' => 'Tridharma'])->id,
            'name' => 'Workshop Kurikulum',
            'target' => '1 kegiatan',
            'is_active' => true,
        ]);
    }
}
