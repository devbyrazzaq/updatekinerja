<?php

namespace Tests\Feature;

use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Resources\PaguAnggarans\Pages\CreatePaguAnggaran;
use App\Filament\Resources\PaguAnggarans\Pages\EditPaguAnggaran;
use App\Filament\Resources\PaguAnggarans\Pages\ListPaguAnggarans;
use App\Filament\Resources\PaguAnggarans\Widgets\PaguAnggaranOverview;
use App\Filament\Resources\Rekenings\Pages\CreateRekening;
use App\Filament\Resources\Rekenings\Pages\ListRekenings;
use App\Models\Bidang;
use App\Models\Kategori;
use App\Models\PaguAnggaran;
use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use App\Models\Periode;
use App\Models\Program;
use App\Models\RealisasiProgramKerja;
use App\Models\Rekening;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AnggaranSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_list_and_create_pages_render(): void
    {
        Livewire::test(ListRekenings::class)->assertOk();
        Livewire::test(CreateRekening::class)->assertOk();
        Livewire::test(ListPaguAnggarans::class)->assertOk();
        Livewire::test(CreatePaguAnggaran::class)->assertOk();
    }

    public function test_can_create_rekening(): void
    {
        $unitKerja = UnitKerja::create(['name' => 'Fakultas Teknik']);

        Livewire::test(CreateRekening::class)
            ->fillForm([
                'code' => '5.1.02.01',
                'name' => 'Belanja ATK',
                'unit_kerja_id' => $unitKerja->id,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Rekening::class, [
            'code' => '5.1.02.01',
            'slug' => 'belanja-atk',
            'unit_kerja_id' => $unitKerja->id,
        ]);
    }

    public function test_can_create_pagu_anggaran(): void
    {
        $periode = Periode::create([
            'name' => 'Periode 2026',
            'start_datetime' => now(),
            'end_datetime' => now()->addYear(),
        ]);
        $tahunKerja = TahunKerja::create([
            'periode_id' => $periode->id,
            'name' => 'TA 2026',
            'start_datetime' => now(),
            'end_datetime' => now()->addYear(),
            'status' => EnumStatusTahunKerja::Berjalan,
        ]);
        $unitKerja = UnitKerja::create(['name' => 'Fakultas Teknik']);

        Livewire::test(CreatePaguAnggaran::class)
            ->fillForm([
                'tahun_kerja_id' => $tahunKerja->id,
                'unit_kerja_id' => $unitKerja->id,
                'amount' => 50000000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(PaguAnggaran::class, [
            'tahun_kerja_id' => $tahunKerja->id,
            'unit_kerja_id' => $unitKerja->id,
            'amount' => 50000000,
        ]);
    }

    public function test_pagu_anggaran_stores_masked_rupiah_input_and_shows_it_masked_again(): void
    {
        $periode = Periode::create([
            'name' => 'Periode 2026',
            'start_datetime' => now(),
            'end_datetime' => now()->addYear(),
        ]);
        $tahunKerja = TahunKerja::create([
            'periode_id' => $periode->id,
            'name' => 'TA 2026',
            'start_datetime' => now(),
            'end_datetime' => now()->addYear(),
            'status' => EnumStatusTahunKerja::Berjalan,
        ]);
        $unitKerja = UnitKerja::create(['name' => 'Fakultas Teknik']);

        Livewire::test(CreatePaguAnggaran::class)
            ->fillForm([
                'tahun_kerja_id' => $tahunKerja->id,
                'unit_kerja_id' => $unitKerja->id,
                'amount' => '50.000.000',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $paguAnggaran = PaguAnggaran::query()->sole();
        $this->assertSame(50_000_000.0, (float) $paguAnggaran->amount);

        Livewire::test(EditPaguAnggaran::class, ['record' => $paguAnggaran->getRouteKey()])
            ->assertSet('data.amount', '50.000.000')
            ->assertFormSet(['amount' => 50_000_000.0]);
    }

    public function test_overview_widget_summarises_the_active_tahun_kerja(): void
    {
        $periode = Periode::create([
            'name' => 'Periode 2026',
            'start_datetime' => now(),
            'end_datetime' => now()->addYear(),
        ]);
        $tahunKerja = TahunKerja::create([
            'periode_id' => $periode->id,
            'name' => 'TA 2026',
            'tahun' => 2026,
            'batas_anggaran' => 100_000_000,
            'start_datetime' => now(),
            'end_datetime' => now()->addYear(),
            'status' => EnumStatusTahunKerja::Berjalan,
        ]);
        $unitKerja = UnitKerja::create(['name' => 'Fakultas Teknik']);

        $penawaran = PenawaranProgramKerja::create([
            'tahun_kerja_id' => $tahunKerja->id,
            'unit_kerja_id' => $unitKerja->id,
            'bidang_id' => Bidang::create(['code' => 'B1', 'name' => 'Akademik'])->id,
            'kategori_id' => Kategori::create(['code' => 'K1', 'name' => 'Pendidikan'])->id,
            'program_id' => Program::create(['name' => 'Tridharma'])->id,
            'name' => 'Pelatihan Dosen',
            'is_active' => true,
        ]);

        $pengajuan = PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $unitKerja->id,
            'user_id' => auth()->id(),
            'alokasi_anggaran' => 40_000_000,
            'status' => EnumStatusPengajuan::Diterima,
        ]);

        // Pengajuan draf belum mengikat anggaran, jadi tidak boleh ikut terhitung.
        PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $unitKerja->id,
            'user_id' => auth()->id(),
            'alokasi_anggaran' => 25_000_000,
            'status' => EnumStatusPengajuan::Draft,
        ]);

        RealisasiProgramKerja::create([
            'pengajuan_program_kerja_id' => $pengajuan->id,
            'name' => 'Pelatihan Dosen Batch 1',
            'anggaran_digunakan' => 30_000_000,
            'status' => EnumStatusRealisasi::Selesai,
        ]);

        $this->assertSame(1, $tahunKerja->jumlahProgramKerja());
        $this->assertSame(40_000_000.0, $tahunKerja->totalPengajuanAnggaran());
        $this->assertSame(30_000_000.0, $tahunKerja->totalAnggaranDigunakan());

        Livewire::test(PaguAnggaranOverview::class)
            ->assertOk()
            ->assertSee('Rp 100.000.000')
            ->assertSee('Rp 40.000.000')
            ->assertSee('Rp 30.000.000');
    }

    public function test_list_seeds_pagu_for_every_unit_of_the_active_tahun_kerja(): void
    {
        $tahunKerja = $this->tahunKerjaAktif();
        $unitA = UnitKerja::create(['name' => 'Fakultas Teknik']);
        $unitB = UnitKerja::create(['name' => 'Fakultas Ekonomi']);

        PaguAnggaran::create([
            'tahun_kerja_id' => $tahunKerja->id,
            'unit_kerja_id' => $unitA->id,
            'amount' => 50_000_000,
        ]);

        Livewire::test(ListPaguAnggarans::class)->assertOk();

        // Unit yang belum punya pagu dibuatkan otomatis dengan nominal 0.
        $this->assertDatabaseHas(PaguAnggaran::class, [
            'tahun_kerja_id' => $tahunKerja->id,
            'unit_kerja_id' => $unitB->id,
            'amount' => 0,
        ]);
        $this->assertSame(2, $tahunKerja->paguAnggarans()->count());
    }

    public function test_list_shows_reference_year_budget_column(): void
    {
        $referensi = $this->tahunKerjaAktif('TA 2026', 2026, false);
        $tahunKerja = $this->tahunKerjaAktif('TA 2027', 2027, true);
        $tahunKerja->update(['referensi_tahun_kerja_id' => $referensi->id]);

        $unit = UnitKerja::create(['name' => 'Fakultas Teknik']);

        PaguAnggaran::create([
            'tahun_kerja_id' => $referensi->id,
            'unit_kerja_id' => $unit->id,
            'amount' => 75_000_000,
        ]);
        $paguSaatIni = PaguAnggaran::create([
            'tahun_kerja_id' => $tahunKerja->id,
            'unit_kerja_id' => $unit->id,
            'amount' => 90_000_000,
        ]);

        Livewire::test(ListPaguAnggarans::class)
            ->assertOk()
            ->assertSee('Anggaran TA 2026')
            ->assertCanSeeTableRecords([$paguSaatIni]);
    }

    public function test_list_shows_usage_percentage_from_realisasi(): void
    {
        $tahunKerja = $this->tahunKerjaAktif();
        $unit = UnitKerja::create(['name' => 'Fakultas Teknik']);

        PaguAnggaran::create([
            'tahun_kerja_id' => $tahunKerja->id,
            'unit_kerja_id' => $unit->id,
            'amount' => 100_000_000,
        ]);

        $penawaran = PenawaranProgramKerja::create([
            'tahun_kerja_id' => $tahunKerja->id,
            'unit_kerja_id' => $unit->id,
            'bidang_id' => Bidang::create(['code' => 'B1', 'name' => 'Akademik'])->id,
            'kategori_id' => Kategori::create(['code' => 'K1', 'name' => 'Pendidikan'])->id,
            'program_id' => Program::create(['name' => 'Tridharma'])->id,
            'name' => 'Pelatihan Dosen',
            'is_active' => true,
        ]);
        $pengajuan = PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $unit->id,
            'user_id' => auth()->id(),
            'alokasi_anggaran' => 60_000_000,
            'status' => EnumStatusPengajuan::Diterima,
        ]);
        RealisasiProgramKerja::create([
            'pengajuan_program_kerja_id' => $pengajuan->id,
            'name' => 'Batch 1',
            'anggaran_digunakan' => 30_000_000,
            'status' => EnumStatusRealisasi::Selesai,
        ]);

        // Penggunaan 30jt dari pagu 100jt = 30,0%.
        Livewire::test(ListPaguAnggarans::class)
            ->assertOk()
            ->assertSee('30,0%');
    }

    public function test_edit_action_updates_only_the_amount_via_modal(): void
    {
        $tahunKerja = $this->tahunKerjaAktif();
        $unit = UnitKerja::create(['name' => 'Fakultas Teknik']);
        $pagu = PaguAnggaran::create([
            'tahun_kerja_id' => $tahunKerja->id,
            'unit_kerja_id' => $unit->id,
            'amount' => 10_000_000,
        ]);

        Livewire::test(ListPaguAnggarans::class)
            ->callAction(TestAction::make('ubah')->table($pagu), [
                'amount' => '25.000.000',
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Pagu anggaran berhasil diperbarui');

        $this->assertSame(25_000_000.0, (float) $pagu->fresh()->amount);
    }

    protected function tahunKerjaAktif(string $name = 'TA 2026', int $tahun = 2026, bool $isActive = true): TahunKerja
    {
        $periode = Periode::firstOrCreate([
            'name' => 'Periode 2026',
        ], [
            'start_datetime' => now(),
            'end_datetime' => now()->addYears(3),
        ]);

        return TahunKerja::create([
            'periode_id' => $periode->id,
            'name' => $name,
            'tahun' => $tahun,
            'start_datetime' => now(),
            'end_datetime' => now()->addYear(),
            'status' => $isActive ? EnumStatusTahunKerja::Berjalan : EnumStatusTahunKerja::Selesai,
        ]);
    }
}
