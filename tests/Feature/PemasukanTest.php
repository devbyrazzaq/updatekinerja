<?php

namespace Tests\Feature;

use App\Enums\EnumJenisWaktuPemasukan;
use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
use App\Enums\EnumSumberPemasukan;
use App\Filament\Resources\Pemasukans\Pages\CreatePemasukan;
use App\Filament\Resources\Pemasukans\Pages\ListPemasukans;
use App\Filament\Resources\Pemasukans\Widgets\PemasukanOverview;
use App\Models\Bidang;
use App\Models\Kategori;
use App\Models\Pemasukan;
use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use App\Models\Periode;
use App\Models\Program;
use App\Models\RealisasiProgramKerja;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PemasukanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    private function seedProgramKerja(): array
    {
        $periode = Periode::create(['name' => 'P1', 'start_datetime' => now(), 'end_datetime' => now()->addYear()]);
        $tk = TahunKerja::create(['periode_id' => $periode->id, 'name' => 'TA 2026', 'start_datetime' => now(), 'end_datetime' => now()->addYear(), 'status' => EnumStatusTahunKerja::Berjalan]);
        $unit = UnitKerja::create(['name' => 'Unit A']);
        $penawaran = PenawaranProgramKerja::create([
            'tahun_kerja_id' => $tk->id, 'unit_kerja_id' => $unit->id,
            'bidang_id' => Bidang::create(['code' => 'B1', 'name' => 'B'])->id,
            'kategori_id' => Kategori::create(['code' => 'K1', 'name' => 'K'])->id,
            'program_id' => Program::create(['name' => 'P'])->id,
            'name' => 'Prokerja', 'is_active' => true,
        ]);
        $pengajuan = PengajuanProgramKerja::create(['penawaran_program_kerja_id' => $penawaran->id, 'unit_kerja_id' => $unit->id, 'alokasi_anggaran' => 20000000, 'status' => EnumStatusPengajuan::Diterima]);
        $realisasi = RealisasiProgramKerja::create(['pengajuan_program_kerja_id' => $pengajuan->id, 'name' => 'Kegiatan', 'anggaran_digunakan' => 15000000, 'status' => EnumStatusRealisasi::Diajukan]);

        // Pemasukan hanya boleh dicatatkan untuk unit yang menjadi cakupan pengguna.
        auth()->user()->update(['unit_kerja_id' => $unit->id]);

        return compact('unit', 'pengajuan', 'realisasi');
    }

    public function test_list_and_create_pages_render(): void
    {
        Livewire::test(ListPemasukans::class)->assertOk();
        Livewire::test(CreatePemasukan::class)->assertOk();
    }

    public function test_can_record_pemasukan_from_realisasi(): void
    {
        ['unit' => $unit, 'realisasi' => $realisasi] = $this->seedProgramKerja();

        Livewire::test(CreatePemasukan::class)
            ->fillForm([
                'unit_kerja_id' => $unit->id,
                'sumber' => EnumSumberPemasukan::Realisasi->value,
                'realisasi_program_kerja_id' => $realisasi->id,
                'rincian_kegiatan' => 'Kontribusi peserta eksternal',
                'tanggal_pelaksanaan' => now()->toDateString(),
                'nominal_pendapatan' => 5000000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Pemasukan::class, [
            'unit_kerja_id' => $unit->id,
            'sumber' => EnumSumberPemasukan::Realisasi->value,
            'realisasi_program_kerja_id' => $realisasi->id,
            'nominal_pendapatan' => 5000000,
        ]);
    }

    public function test_can_record_pemasukan_from_pengajuan(): void
    {
        ['unit' => $unit, 'pengajuan' => $pengajuan] = $this->seedProgramKerja();

        Livewire::test(CreatePemasukan::class)
            ->fillForm([
                'unit_kerja_id' => $unit->id,
                'sumber' => EnumSumberPemasukan::Pengajuan->value,
                'pengajuan_program_kerja_id' => $pengajuan->id,
                'rincian_kegiatan' => 'Iuran kegiatan',
                'tanggal_pelaksanaan' => now()->toDateString(),
                'nominal_pendapatan' => 1500000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Pemasukan::class, [
            'sumber' => EnumSumberPemasukan::Pengajuan->value,
            'pengajuan_program_kerja_id' => $pengajuan->id,
            'nominal_pendapatan' => 1500000,
        ]);
    }

    public function test_rentang_waktu_wajib_mengisi_tanggal_selesai(): void
    {
        ['unit' => $unit, 'realisasi' => $realisasi] = $this->seedProgramKerja();

        Livewire::test(CreatePemasukan::class)
            ->fillForm([
                'unit_kerja_id' => $unit->id,
                'sumber' => EnumSumberPemasukan::Realisasi->value,
                'realisasi_program_kerja_id' => $realisasi->id,
                'rincian_kegiatan' => 'Pameran tiga hari',
                'jenis_waktu' => EnumJenisWaktuPemasukan::Rentang->value,
                'tanggal_pelaksanaan' => now()->toDateString(),
                'nominal_pendapatan' => 2500000,
            ])
            ->call('create')
            ->assertHasFormErrors(['tanggal_selesai' => 'required']);
    }

    public function test_tanggal_selesai_tidak_boleh_mendahului_tanggal_mulai(): void
    {
        ['unit' => $unit, 'realisasi' => $realisasi] = $this->seedProgramKerja();

        Livewire::test(CreatePemasukan::class)
            ->fillForm([
                'unit_kerja_id' => $unit->id,
                'sumber' => EnumSumberPemasukan::Realisasi->value,
                'realisasi_program_kerja_id' => $realisasi->id,
                'rincian_kegiatan' => 'Pameran mundur',
                'jenis_waktu' => EnumJenisWaktuPemasukan::Rentang->value,
                'tanggal_pelaksanaan' => now()->toDateString(),
                'tanggal_selesai' => now()->subDay()->toDateString(),
                'nominal_pendapatan' => 2500000,
            ])
            ->call('create')
            ->assertHasFormErrors(['tanggal_selesai' => 'after_or_equal']);
    }

    public function test_jenis_waktu_satu_hari_mengabaikan_tanggal_selesai(): void
    {
        ['unit' => $unit, 'realisasi' => $realisasi] = $this->seedProgramKerja();

        Livewire::test(CreatePemasukan::class)
            ->fillForm([
                'unit_kerja_id' => $unit->id,
                'sumber' => EnumSumberPemasukan::Realisasi->value,
                'realisasi_program_kerja_id' => $realisasi->id,
                'rincian_kegiatan' => 'Seminar sehari',
                'jenis_waktu' => EnumJenisWaktuPemasukan::Rentang->value,
                'tanggal_pelaksanaan' => now()->toDateString(),
                'tanggal_selesai' => now()->addDays(2)->toDateString(),
                'nominal_pendapatan' => 1000000,
            ])
            // Kembali ke 1 hari harus ikut mengosongkan tanggal selesai yang sudah terisi.
            ->fillForm(['jenis_waktu' => EnumJenisWaktuPemasukan::SatuHari->value])
            ->call('create')
            ->assertHasNoFormErrors();

        $pemasukan = Pemasukan::where('rincian_kegiatan', 'Seminar sehari')->firstOrFail();

        $this->assertSame(EnumJenisWaktuPemasukan::SatuHari, $pemasukan->jenis_waktu);
        $this->assertNull($pemasukan->tanggal_selesai);
    }

    public function test_label_periode_menggabungkan_rentang_tanggal(): void
    {
        $sehari = Pemasukan::factory()->make([
            'jenis_waktu' => EnumJenisWaktuPemasukan::SatuHari,
            'tanggal_pelaksanaan' => '2026-08-12',
        ]);

        $rentangSebulan = Pemasukan::factory()->make([
            'jenis_waktu' => EnumJenisWaktuPemasukan::Rentang,
            'tanggal_pelaksanaan' => '2026-08-12',
            'tanggal_selesai' => '2026-08-15',
        ]);

        $rentangLintasBulan = Pemasukan::factory()->make([
            'jenis_waktu' => EnumJenisWaktuPemasukan::Rentang,
            'tanggal_pelaksanaan' => '2026-08-28',
            'tanggal_selesai' => '2026-09-02',
        ]);

        $this->assertSame('12 Agustus 2026', $sehari->labelPeriode());
        $this->assertSame('12 – 15 Agustus 2026', $rentangSebulan->labelPeriode());
        $this->assertSame('28 Agustus – 02 September 2026', $rentangLintasBulan->labelPeriode());
    }

    public function test_page_defaults_the_filter_to_the_first_permitted_unit(): void
    {
        ['unit' => $unit] = $this->seedProgramKerja();

        Livewire::test(ListPemasukans::class)
            ->assertSet('unitKerjaId', $unit->id);
    }

    public function test_page_keeps_all_units_for_privileged_user(): void
    {
        $this->seedProgramKerja();

        $privileged = User::factory()->create(['unit_kerja_id' => null]);
        $privileged->givePermissionTo(Permission::findOrCreate('bypass_data_scope', 'web'));
        $this->actingAs($privileged);

        Livewire::test(ListPemasukans::class)
            ->assertSet('unitKerjaId', null);
    }

    /**
     * Urutan tampilan halaman: penyaring, ringkasan, lalu tabel.
     */
    public function test_page_renders_filter_then_overview_then_table(): void
    {
        $html = Livewire::test(ListPemasukans::class)->html();

        $penyaring = strpos($html, 'Tampilkan Data');
        $ringkasan = strpos($html, 'wire:name="'.PemasukanOverview::class.'"');
        $tabel = strpos($html, 'fi-ta-table');

        $this->assertNotFalse($penyaring);
        $this->assertNotFalse($ringkasan);
        $this->assertNotFalse($tabel);
        $this->assertLessThan($ringkasan, $penyaring);
        $this->assertLessThan($tabel, $ringkasan);
    }

    /**
     * Cakupan satu unit langsung disebut namanya; gabungan unit menyebut daftarnya.
     */
    public function test_widget_names_the_covered_units(): void
    {
        ['unit' => $unit] = $this->seedProgramKerja();
        UnitKerja::create(['name' => 'Unit B']);

        Livewire::test(PemasukanOverview::class, ['unitKerjaId' => null])
            ->assertOk()
            ->assertSee($unit->name)
            ->assertDontSee('Semua Unit');

        $privileged = User::factory()->create(['unit_kerja_id' => null]);
        $privileged->givePermissionTo(Permission::findOrCreate('bypass_data_scope', 'web'));
        $this->actingAs($privileged);

        Livewire::test(PemasukanOverview::class, ['unitKerjaId' => null])
            ->assertOk()
            ->assertSee('Semua Unit')
            ->assertSee('2 unit: Unit A, Unit B');
    }

    public function test_requires_source_record(): void
    {
        ['unit' => $unit] = $this->seedProgramKerja();

        Livewire::test(CreatePemasukan::class)
            ->fillForm([
                'unit_kerja_id' => $unit->id,
                'sumber' => EnumSumberPemasukan::Realisasi->value,
                'rincian_kegiatan' => 'Tanpa sumber',
                'tanggal_pelaksanaan' => now()->toDateString(),
                'nominal_pendapatan' => 1000000,
            ])
            ->call('create')
            ->assertHasFormErrors(['realisasi_program_kerja_id' => 'required']);
    }
}
