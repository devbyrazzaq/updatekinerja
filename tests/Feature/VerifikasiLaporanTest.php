<?php

namespace Tests\Feature;

use App\Enums\EnumCaraPenyelesaianAnggaran;
use App\Enums\EnumStatusAnggaran;
use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusPenyelesaianAnggaran;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Actions\RevisiLaporanRealisasiAction;
use App\Filament\Actions\TerimaLaporanRealisasiAction;
use App\Filament\Resources\VerifikasiLaporans\Pages\ListVerifikasiLaporans;
use App\Filament\Resources\VerifikasiLaporans\Pages\ViewVerifikasiLaporan;
use App\Filament\Resources\VerifikasiLaporans\VerifikasiLaporanResource;
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
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Verifikasi laporan realisasi dilakukan dari modal detail: aksi Terima menutup
 * realisasi (menetapkan cara penyelesaian bila anggaran menyisakan selisih) dan aksi
 * Revisi mengembalikannya ke unit kerja beserta catatan perbaikan.
 */
class VerifikasiLaporanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_tabel_hanya_menyediakan_aksi_detail_untuk_verifikasi(): void
    {
        $realisasi = $this->seedLaporan();

        Livewire::test(ListVerifikasiLaporans::class)
            ->assertTableActionVisible('view', $realisasi)
            ->assertActionDoesNotExist(TestAction::make('verifikasiLaporan')->table($realisasi));
    }

    public function test_terima_laporan_anggaran_habis_hanya_konfirmasi(): void
    {
        $realisasi = $this->seedLaporan(EnumStatusAnggaran::Habis);

        Livewire::test(ListVerifikasiLaporans::class)
            ->callAction([
                TestAction::make('view')->table($realisasi),
                TestAction::make(TerimaLaporanRealisasiAction::class),
            ])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $realisasi->refresh();

        $this->assertSame(EnumStatusRealisasi::Selesai, $realisasi->status);
        $this->assertNotNull($realisasi->laporan_disetujui_at);
        $this->assertSame(auth()->id(), $realisasi->verifikator_laporan_id);
        $this->assertNull($realisasi->cara_penyelesaian_anggaran);
    }

    public function test_terima_laporan_dengan_selisih_wajib_menetapkan_cara_penyelesaian(): void
    {
        $realisasi = $this->seedLaporan(EnumStatusAnggaran::Kurang);

        Livewire::test(ListVerifikasiLaporans::class)
            ->callAction([
                TestAction::make('view')->table($realisasi),
                TestAction::make(TerimaLaporanRealisasiAction::class),
            ], ['cara_penyelesaian_anggaran' => null])
            ->assertHasActionErrors(['cara_penyelesaian_anggaran' => 'required']);

        $this->assertSame(EnumStatusRealisasi::VerifikasiLaporan, $realisasi->refresh()->status);
    }

    public function test_kekurangan_yang_dilunasi_lewat_pencairan_tambahan_masih_menunggu_keuangan(): void
    {
        $realisasi = $this->seedLaporan(EnumStatusAnggaran::Kurang);

        Livewire::test(ListVerifikasiLaporans::class)
            ->callAction([
                TestAction::make('view')->table($realisasi),
                TestAction::make(TerimaLaporanRealisasiAction::class),
            ], ['cara_penyelesaian_anggaran' => EnumCaraPenyelesaianAnggaran::PencairanTambahan->value])
            ->assertHasNoActionErrors();

        $realisasi->refresh();

        $this->assertSame(EnumStatusRealisasi::Selesai, $realisasi->status);
        $this->assertSame(EnumCaraPenyelesaianAnggaran::PencairanTambahan, $realisasi->cara_penyelesaian_anggaran);
        $this->assertSame(EnumStatusPenyelesaianAnggaran::Menunggu, $realisasi->status_penyelesaian_anggaran);
        $this->assertNull($realisasi->penyelesaian_anggaran_at);
        $this->assertSame(0.0, $realisasi->penyesuaianAnggaran());
    }

    public function test_kekurangan_yang_ditalangi_unit_kerja_mengurangi_anggaran(): void
    {
        $realisasi = $this->seedLaporan(EnumStatusAnggaran::Kurang);

        Livewire::test(ListVerifikasiLaporans::class)
            ->callAction([
                TestAction::make('view')->table($realisasi),
                TestAction::make(TerimaLaporanRealisasiAction::class),
            ], ['cara_penyelesaian_anggaran' => EnumCaraPenyelesaianAnggaran::TalanganUnitKerja->value])
            ->assertHasNoActionErrors();

        $realisasi->refresh();

        $this->assertSame(EnumStatusPenyelesaianAnggaran::Dilunasi, $realisasi->status_penyelesaian_anggaran);
        $this->assertNotNull($realisasi->penyelesaian_anggaran_at);
        $this->assertSame(-3_000_000.0, $realisasi->penyesuaianAnggaran());
    }

    public function test_sisa_anggaran_yang_menjadi_saldo_unit_kerja_menambah_anggaran(): void
    {
        $realisasi = $this->seedLaporan(EnumStatusAnggaran::Sisa);

        Livewire::test(ListVerifikasiLaporans::class)
            ->callAction([
                TestAction::make('view')->table($realisasi),
                TestAction::make(TerimaLaporanRealisasiAction::class),
            ], ['cara_penyelesaian_anggaran' => EnumCaraPenyelesaianAnggaran::SaldoUnitKerja->value])
            ->assertHasNoActionErrors();

        $realisasi->refresh();

        $this->assertSame(EnumStatusRealisasi::Selesai, $realisasi->status);
        $this->assertSame(EnumCaraPenyelesaianAnggaran::SaldoUnitKerja, $realisasi->cara_penyelesaian_anggaran);
        $this->assertSame(EnumStatusPenyelesaianAnggaran::Dikembalikan, $realisasi->status_penyelesaian_anggaran);
        $this->assertNotNull($realisasi->penyelesaian_anggaran_at);
        $this->assertSame(3_000_000.0, $realisasi->penyesuaianAnggaran());
    }

    public function test_revisi_laporan_mengembalikan_ke_unit_kerja_dengan_catatan(): void
    {
        $realisasi = $this->seedLaporan();

        Livewire::test(ListVerifikasiLaporans::class)
            ->callAction([
                TestAction::make('view')->table($realisasi),
                TestAction::make(RevisiLaporanRealisasiAction::class),
            ], ['catatan' => '<p>Rincian penggunaan anggaran belum dilampirkan.</p>'])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $realisasi->refresh();

        $this->assertSame(EnumStatusRealisasi::Revisi, $realisasi->status);
        $this->assertSame('<p>Rincian penggunaan anggaran belum dilampirkan.</p>', $realisasi->catatan_verifikasi);
        $this->assertSame(auth()->id(), $realisasi->verifikator_laporan_id);
    }

    public function test_catatan_revisi_wajib_diisi(): void
    {
        $realisasi = $this->seedLaporan();

        Livewire::test(ListVerifikasiLaporans::class)
            ->callAction([
                TestAction::make('view')->table($realisasi),
                TestAction::make(RevisiLaporanRealisasiAction::class),
            ], ['catatan' => null])
            ->assertHasActionErrors(['catatan']);

        $this->assertSame(EnumStatusRealisasi::VerifikasiLaporan, $realisasi->refresh()->status);
    }

    public function test_halaman_detail_menyediakan_aksi_verifikasi_dan_kembali_ke_daftar(): void
    {
        $realisasi = $this->seedLaporan(EnumStatusAnggaran::Sisa);

        Livewire::test(ViewVerifikasiLaporan::class, ['record' => $realisasi->getRouteKey()])
            ->assertActionVisible(TerimaLaporanRealisasiAction::class)
            ->assertActionVisible(RevisiLaporanRealisasiAction::class)
            ->callAction(TerimaLaporanRealisasiAction::class, [
                'cara_penyelesaian_anggaran' => EnumCaraPenyelesaianAnggaran::KembaliBiroKeuangan->value,
            ])
            ->assertHasNoActionErrors()
            ->assertRedirect(VerifikasiLaporanResource::getUrl('index'));

        $realisasi->refresh();

        $this->assertSame(EnumStatusRealisasi::Selesai, $realisasi->status);
        $this->assertSame(EnumCaraPenyelesaianAnggaran::KembaliBiroKeuangan, $realisasi->cara_penyelesaian_anggaran);
    }

    public function test_aksi_verifikasi_tersembunyi_saat_laporan_sudah_diputuskan(): void
    {
        $realisasi = $this->seedLaporan();
        $realisasi->update([
            'status' => EnumStatusRealisasi::Selesai,
            'laporan_disetujui_at' => now(),
            'verifikator_laporan_id' => auth()->id(),
        ]);

        Livewire::test(ViewVerifikasiLaporan::class, ['record' => $realisasi->getRouteKey()])
            ->assertActionHidden(TerimaLaporanRealisasiAction::class)
            ->assertActionHidden(RevisiLaporanRealisasiAction::class);
    }

    /**
     * Realisasi yang laporannya sudah dikirim dan menunggu verifikasi, dengan selisih
     * anggaran Rp 3.000.000 bila status anggarannya tidak habis.
     */
    protected function seedLaporan(EnumStatusAnggaran $statusAnggaran = EnumStatusAnggaran::Habis): RealisasiProgramKerja
    {
        $periode = Periode::create(['name' => 'P1', 'start_datetime' => now(), 'end_datetime' => now()->addYear()]);
        $tahunKerja = TahunKerja::create([
            'periode_id' => $periode->id,
            'name' => 'TA 2026',
            'start_datetime' => now(),
            'end_datetime' => now()->addYear(),
            'status' => EnumStatusTahunKerja::Berjalan,
        ]);
        $unit = UnitKerja::create(['name' => 'Fakultas Teknik']);

        $penawaran = PenawaranProgramKerja::create([
            'tahun_kerja_id' => $tahunKerja->id,
            'unit_kerja_id' => $unit->id,
            'bidang_id' => Bidang::create(['code' => 'B1', 'name' => 'Akademik'])->id,
            'kategori_id' => Kategori::create(['code' => 'K1', 'name' => 'Pendidikan'])->id,
            'program_id' => Program::create(['name' => 'Tridharma'])->id,
            'name' => 'Workshop Kurikulum',
            'is_active' => true,
        ]);

        $pengajuan = PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $unit->id,
            'alokasi_anggaran' => 20_000_000,
            'status' => EnumStatusPengajuan::Diterima,
        ]);

        $realisasi = RealisasiProgramKerja::create([
            'pengajuan_program_kerja_id' => $pengajuan->id,
            'name' => 'Pelaksanaan Workshop',
            'anggaran_digunakan' => 20_000_000,
            'status' => EnumStatusRealisasi::MenungguLaporan,
        ]);

        $selisih = $statusAnggaran->memerlukanSelisih() ? 3_000_000.0 : null;

        $realisasi->update([
            'laporan_path' => ['laporan-realisasi/laporan.pdf'],
            'laporan_diserahkan_at' => now(),
            'evaluasi_pengerjaan' => '<p>Kegiatan terlaksana sesuai rencana.</p>',
            'status_anggaran' => $statusAnggaran,
            'nominal_selisih_anggaran' => $selisih,
            'status_penyelesaian_anggaran' => $selisih === null ? null : EnumStatusPenyelesaianAnggaran::Menunggu,
            'anggaran_digunakan' => $realisasi->anggaranDigunakanDariSelisih($statusAnggaran, $selisih ?? 0.0),
            'persentase_ketercapaian' => 100,
            'status' => EnumStatusRealisasi::VerifikasiLaporan,
        ]);

        return $realisasi->refresh();
    }
}
