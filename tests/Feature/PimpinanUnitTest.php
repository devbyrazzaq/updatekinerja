<?php

namespace Tests\Feature;

use App\Enums\EnumRole;
use App\Filament\Pages\BukuAnggaran as BukuAnggaranPage;
use App\Filament\Pages\DetailMonitoringRealisasi;
use App\Filament\Pages\MonitoringProgramKerja as MonitoringProgramKerjaPage;
use App\Filament\Pages\MonitoringRealisasi as MonitoringRealisasiPage;
use App\Filament\Resources\DaftarProgramKerjas\DaftarProgramKerjaResource;
use App\Filament\Resources\PaguAnggarans\PaguAnggaranResource;
use App\Filament\Resources\Pemasukans\PemasukanResource;
use App\Filament\Resources\PengajuanProgramKerjas\PengajuanProgramKerjaResource;
use App\Filament\Resources\PerencanaanPengajuanProgramKerjas\PerencanaanPengajuanProgramKerjaResource;
use App\Filament\Resources\RealisasiProgramKerjas\RealisasiProgramKerjaResource;
use App\Filament\Resources\RekeningBanks\Pages\CreateRekeningBank;
use App\Filament\Resources\RekeningBanks\Pages\ListRekeningBanks;
use App\Filament\Resources\RekeningBanks\RekeningBankResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\UnitKerjas\UnitKerjaResource;
use App\Filament\Resources\VerifikasiRektors\VerifikasiRektorResource;
use App\Models\Bank;
use App\Models\RekeningBank;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\UnitKerjaAktif;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pimpinan Unit hanya membawahi menu Pelaksanaan, Pemasukan, dan Perencanaan, dan
 * datanya dibatasi unit kerja yang sedang aktif.
 */
class PimpinanUnitTest extends TestCase
{
    use RefreshDatabase;

    private UnitKerja $unitUtama;

    private UnitKerja $unitKedua;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UnitKerjaSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->unitUtama = UnitKerja::where('slug', 'perpustakaan')->firstOrFail();
        $this->unitKedua = UnitKerja::where('slug', 'pusat-bahasa')->firstOrFail();
    }

    private function pimpinan(bool $duaUnit = true): User
    {
        $user = User::factory()->create(['unit_kerja_id' => $this->unitUtama->id]);

        $roles = [EnumRole::PimpinanUnit->value];

        if ($duaUnit) {
            $roles[] = EnumRole::unitScopeName($this->unitKedua->name);
        }

        $user->syncRoles($roles);

        return $user;
    }

    public function test_menu_pelaksanaan_pemasukan_dan_perencanaan_terbuka(): void
    {
        $this->actingAs($this->pimpinan());

        $this->assertTrue(DaftarProgramKerjaResource::canViewAny());
        $this->assertTrue(PengajuanProgramKerjaResource::canViewAny());
        $this->assertTrue(RealisasiProgramKerjaResource::canViewAny());
        $this->assertTrue(PemasukanResource::canViewAny());
        $this->assertTrue(PerencanaanPengajuanProgramKerjaResource::canViewAny());
    }

    public function test_menu_rekening_bank_dan_buku_anggaran_terbuka(): void
    {
        $this->actingAs($this->pimpinan());

        $this->assertTrue(RekeningBankResource::canViewAny());
        $this->assertTrue(RekeningBankResource::canCreate());
        $this->assertTrue(BukuAnggaranPage::canAccess());
        $this->assertTrue(MonitoringProgramKerjaPage::canAccess());
        $this->assertTrue(MonitoringRealisasiPage::canAccess());
    }

    /**
     * Monitoring Realisasi hanya menawarkan unit kerja yang boleh diakses, dan
     * rinciannya ikut terbuka lewat halaman detail yang menumpang hak akses yang sama.
     */
    public function test_monitoring_realisasi_terbatas_pada_unit_yang_boleh_diakses(): void
    {
        $unitLain = UnitKerja::create(['name' => 'Unit Lain']);

        $this->actingAs($this->pimpinan());
        $this->startSession();

        Livewire::test(MonitoringRealisasiPage::class)
            ->assertOk()
            ->assertFormSet(['unitKerjaId' => null])
            ->assertSee($this->unitUtama->name)
            ->assertDontSee($unitLain->name);

        $this->assertTrue(DetailMonitoringRealisasi::canAccess());
    }

    /**
     * Monitoring hanya menawarkan unit kerja yang boleh diakses; tanpa unit terpilih
     * pun angkanya dirangkum dari unit tersebut saja, bukan seluruh unit kerja.
     */
    public function test_monitoring_program_kerja_terbatas_pada_unit_yang_boleh_diakses(): void
    {
        $unitLain = UnitKerja::create(['name' => 'Unit Lain']);

        $this->actingAs($this->pimpinan());
        $this->startSession();

        Livewire::test(MonitoringProgramKerjaPage::class)
            ->assertOk()
            ->assertFormSet(['unitKerjaId' => null])
            ->assertSee($this->unitUtama->name)
            ->assertDontSee($unitLain->name);
    }

    public function test_menu_di_luar_ketiga_grup_tertutup(): void
    {
        $this->actingAs($this->pimpinan());

        $this->assertFalse(UnitKerjaResource::canViewAny());
        $this->assertFalse(PaguAnggaranResource::canViewAny());
        $this->assertFalse(RoleResource::canViewAny());
        $this->assertFalse(VerifikasiRektorResource::canViewAny());
    }

    /**
     * Daftar rekening bank hanya memuat rekening unit yang sedang aktif ditambah
     * rekening umum yang memang berlaku untuk semua unit.
     */
    public function test_rekening_bank_terbatas_pada_unit_aktif_dan_rekening_umum(): void
    {
        $unitLain = UnitKerja::create(['name' => 'Unit Lain']);

        $milikSendiri = $this->rekeningBank($this->unitUtama->id);
        $milikUnitKedua = $this->rekeningBank($this->unitKedua->id);
        $milikUnitLain = $this->rekeningBank($unitLain->id);
        $umum = $this->rekeningBank(null);

        $this->actingAs($this->pimpinan());
        $this->startSession();

        Livewire::test(ListRekeningBanks::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$milikSendiri, $umum])
            ->assertCanNotSeeTableRecords([$milikUnitKedua, $milikUnitLain]);
    }

    /**
     * Rekening bank hanya boleh didaftarkan atas unit kerja yang sedang aktif; unit
     * lain tidak tersedia sebagai pilihan sehingga ditolak saat disimpan.
     */
    public function test_rekening_bank_hanya_dapat_ditambahkan_untuk_unit_aktif(): void
    {
        $bank = Bank::factory()->create();
        $unitLain = UnitKerja::create(['name' => 'Unit Lain']);

        $this->actingAs($this->pimpinan());
        $this->startSession();

        Livewire::test(CreateRekeningBank::class)
            ->assertFormSet(['unit_kerja_id' => $this->unitUtama->id])
            ->fillForm([
                'bank_id' => $bank->id,
                'unit_kerja_id' => $unitLain->id,
                'nomor_rekening' => '1234567890',
                'atas_nama' => 'Unit Lain',
            ])
            ->call('create')
            ->assertHasFormErrors(['unit_kerja_id']);

        Livewire::test(CreateRekeningBank::class)
            ->fillForm([
                'bank_id' => $bank->id,
                'nomor_rekening' => '9876543210',
                'atas_nama' => 'Perpustakaan',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(RekeningBank::class, [
            'nomor_rekening' => '9876543210',
            'unit_kerja_id' => $this->unitUtama->id,
        ]);
    }

    /**
     * Rekening umum yang dipakai bersama tetap terlihat, tetapi pengubahannya
     * merupakan wewenang pengguna berakses penuh.
     */
    public function test_rekening_umum_dan_milik_unit_lain_tidak_dapat_diubah(): void
    {
        $unitLain = UnitKerja::create(['name' => 'Unit Lain']);

        $milikSendiri = $this->rekeningBank($this->unitUtama->id);
        $milikUnitLain = $this->rekeningBank($unitLain->id);
        $umum = $this->rekeningBank(null);

        $this->actingAs($this->pimpinan());
        $this->startSession();

        $this->assertTrue(RekeningBankResource::canEdit($milikSendiri));
        $this->assertFalse(RekeningBankResource::canEdit($umum));
        $this->assertFalse(RekeningBankResource::canEdit($milikUnitLain));
    }

    /**
     * Buku Anggaran Unit hanya menawarkan unit kerja yang boleh diakses, dan terbuka
     * pada unit kerja pengguna sendiri.
     */
    public function test_buku_anggaran_hanya_menawarkan_unit_yang_boleh_diakses(): void
    {
        $unitLain = UnitKerja::create(['name' => 'Unit Lain']);

        $this->actingAs($this->pimpinan());
        $this->startSession();

        Livewire::test(BukuAnggaranPage::class)
            ->assertOk()
            ->assertFormSet(['unitKerjaId' => $this->unitUtama->id])
            ->assertSee($this->unitUtama->name)
            ->assertDontSee($unitLain->name);
    }

    private function rekeningBank(?int $unitKerjaId): RekeningBank
    {
        return RekeningBank::factory()->create(['unit_kerja_id' => $unitKerjaId]);
    }

    public function test_pimpinan_unit_tidak_melewati_pembatasan_data(): void
    {
        $pimpinan = $this->pimpinan();

        $this->assertFalse($pimpinan->isPrivileged());
    }

    public function test_pengalih_unit_memindahkan_unit_aktif(): void
    {
        $this->actingAs($this->pimpinan());
        $this->startSession();

        $this->assertSame($this->unitUtama->id, UnitKerjaAktif::id());

        Livewire::test('unit-kerja-switcher')
            ->assertOk()
            ->assertSee($this->unitUtama->name)
            ->assertSee($this->unitKedua->name)
            ->call('pilih', $this->unitKedua->id);

        $this->assertSame($this->unitKedua->id, UnitKerjaAktif::id());
    }

    public function test_pengalih_kosong_bagi_pimpinan_satu_unit(): void
    {
        $this->actingAs($this->pimpinan(duaUnit: false));
        $this->startSession();

        Livewire::test('unit-kerja-switcher')
            ->assertOk()
            ->assertDontSee($this->unitUtama->name);
    }
}
