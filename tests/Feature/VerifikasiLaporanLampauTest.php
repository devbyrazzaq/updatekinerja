<?php

namespace Tests\Feature;

use App\Enums\EnumStatusAnggaran;
use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Actions\TerimaLaporanRealisasiAction;
use App\Filament\Pages\PenyelesaianTahunLalu;
use App\Filament\Resources\VerifikasiLaporanLampaus\Pages\ListVerifikasiLaporanLampaus;
use App\Filament\Resources\VerifikasiLaporanLampaus\VerifikasiLaporanLampauResource;
use App\Filament\Resources\VerifikasiLaporans\Pages\ListVerifikasiLaporans;
use App\Models\Bidang;
use App\Models\Kategori;
use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use App\Models\Periode;
use App\Models\Program;
use App\Models\RealisasiProgramKerja;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\TransisiTahunKerja;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Unit kerja menuntaskan laporan tahun lalu dari Penyelesaian Tahun Lalu, lalu
 * laporannya diverifikasi lewat menu Verifikasi Laporan Lampau — terpisah dari
 * Verifikasi Laporan yang hanya memuat tahun berjalan.
 */
class VerifikasiLaporanLampauTest extends TestCase
{
    use RefreshDatabase;

    protected TahunKerja $lama;

    protected TahunKerja $baru;

    protected UnitKerja $unit;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filament.default_filesystem_disk'));

        $periode = Periode::create([
            'name' => 'Periode 2024-2028',
            'start_datetime' => now()->subYears(2),
            'end_datetime' => now()->addYears(2),
        ]);

        $this->lama = TahunKerja::create([
            'periode_id' => $periode->id,
            'name' => 'TA 2026',
            'tahun' => 2026,
            'start_datetime' => now(),
            'end_datetime' => now()->addYear(),
            'status' => EnumStatusTahunKerja::Berjalan,
        ]);

        $this->baru = TahunKerja::create([
            'periode_id' => $periode->id,
            'name' => 'TA 2027',
            'tahun' => 2027,
            'start_datetime' => now()->addYear(),
            'end_datetime' => now()->addYears(2),
            'status' => EnumStatusTahunKerja::Perencanaan,
        ]);

        $this->unit = UnitKerja::create(['name' => 'Fakultas Teknik']);

        $this->actingAs(User::factory()->create(['unit_kerja_id' => $this->unit->id]));
    }

    public function test_unit_kerja_mengunggah_laporan_lampau_dan_melihat_detailnya(): void
    {
        $realisasi = $this->realisasi(EnumStatusRealisasi::MenungguLaporan);

        $this->tutupTahunLama();

        Livewire::test(PenyelesaianTahunLalu::class)
            ->assertTableActionVisible('view', $realisasi)
            ->assertActionDoesNotExist(TestAction::make('terimaLaporan')->table($realisasi))
            ->mountAction(TestAction::make('view')->table($realisasi))
            ->assertMountedActionModalSee($realisasi->name);

        $this->kirimLaporan($realisasi);

        $this->assertSame(EnumStatusRealisasi::VerifikasiLaporan, $realisasi->refresh()->status);
    }

    public function test_laporan_lampau_hanya_muncul_di_verifikasi_laporan_lampau(): void
    {
        $lampau = $this->realisasi(EnumStatusRealisasi::MenungguLaporan);

        $this->tutupTahunLama();
        $this->kirimLaporan($lampau);

        $tahunBerjalan = $this->realisasi(EnumStatusRealisasi::VerifikasiLaporan, $this->baru->refresh());

        Livewire::test(ListVerifikasiLaporanLampaus::class)
            ->assertCanSeeTableRecords([$lampau])
            ->assertCanNotSeeTableRecords([$tahunBerjalan]);

        Livewire::test(ListVerifikasiLaporans::class)
            ->assertCanSeeTableRecords([$tahunBerjalan])
            ->assertCanNotSeeTableRecords([$lampau]);

        $this->assertSame('1', VerifikasiLaporanLampauResource::getNavigationBadge());
    }

    public function test_verifikator_menerima_laporan_lampau_hingga_tunggakan_tuntas(): void
    {
        $realisasi = $this->realisasi(EnumStatusRealisasi::MenungguLaporan);

        $this->tutupTahunLama();
        $this->kirimLaporan($realisasi);

        Livewire::test(ListVerifikasiLaporanLampaus::class)
            ->callAction([
                TestAction::make('view')->table($realisasi),
                TestAction::make(TerimaLaporanRealisasiAction::class),
            ])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $this->assertSame(EnumStatusRealisasi::Selesai, $realisasi->refresh()->status);
        $this->assertSame(0, RealisasiProgramKerja::tunggakanTahunPenutupan()->count());
    }

    public function test_menu_tersembunyi_tanpa_tahun_penutupan(): void
    {
        $this->assertFalse(VerifikasiLaporanLampauResource::shouldRegisterNavigation());

        $this->tutupTahunLama();

        $this->assertTrue(VerifikasiLaporanLampauResource::shouldRegisterNavigation());
    }

    protected function kirimLaporan(RealisasiProgramKerja $realisasi): void
    {
        Livewire::test(PenyelesaianTahunLalu::class)
            ->callAction(TestAction::make('unggahLaporan')->table($realisasi), [
                'evaluasi_pengerjaan' => '<p>Kegiatan terlaksana sesuai rencana.</p>',
                'status_anggaran' => EnumStatusAnggaran::Habis->value,
                'persentase_ketercapaian' => 100,
                'laporan_path' => [UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf')],
            ])
            ->assertHasNoActionErrors()
            ->assertNotified();
    }

    protected function tutupTahunLama(): void
    {
        app(TransisiTahunKerja::class)->mulaiTahunKerja($this->baru);

        $this->assertSame(EnumStatusTahunKerja::Penutupan, $this->lama->refresh()->status);
    }

    protected function realisasi(EnumStatusRealisasi $status, ?TahunKerja $tahunKerja = null): RealisasiProgramKerja
    {
        $penawaran = PenawaranProgramKerja::create([
            'tahun_kerja_id' => ($tahunKerja ?? $this->lama)->id,
            'unit_kerja_id' => $this->unit->id,
            'bidang_id' => Bidang::firstOrCreate(['code' => 'B1'], ['name' => 'Akademik'])->id,
            'kategori_id' => Kategori::firstOrCreate(['code' => 'K1'], ['name' => 'Pendidikan'])->id,
            'program_id' => Program::firstOrCreate(['name' => 'Tridharma'])->id,
            'name' => 'Kegiatan '.fake()->unique()->word(),
            'is_active' => true,
        ]);

        $pengajuan = PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $this->unit->id,
            'user_id' => auth()->id(),
            'alokasi_anggaran' => 10_000_000,
            'status' => EnumStatusPengajuan::Diterima,
        ]);

        return RealisasiProgramKerja::create([
            'pengajuan_program_kerja_id' => $pengajuan->id,
            'name' => 'Realisasi '.fake()->unique()->word(),
            'nominal_diajukan' => 2_000_000,
            'nominal_disetujui' => 2_000_000,
            'anggaran_digunakan' => 2_000_000,
            'status' => $status,
        ]);
    }
}
