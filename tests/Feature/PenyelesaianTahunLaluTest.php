<?php

namespace Tests\Feature;

use App\Enums\EnumCaraPenyelesaianAnggaran;
use App\Enums\EnumStatusAnggaran;
use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusPenyelesaianAnggaran;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Pages\PenyelesaianTahunLalu;
use App\Models\Bidang;
use App\Models\JadwalPencairan;
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
 * Halaman Penyelesaian Tahun Lalu adalah satu-satunya tempat tunggakan tahun kerja
 * yang sudah ditinggalkan dikerjakan, sehingga seluruh aksi penuntasannya — dari
 * perbaikan proposal unit kerja sampai penyelesaian selisih anggaran Biro Keuangan —
 * harus berjalan langsung dari halaman ini sampai tahun lama boleh dikunci.
 */
class PenyelesaianTahunLaluTest extends TestCase
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

    public function test_halaman_hanya_memuat_tunggakan_tahun_penutupan(): void
    {
        $tunggakan = $this->realisasi($this->lama, EnumStatusRealisasi::VerifikasiRektor);
        $selesai = $this->realisasi($this->lama, EnumStatusRealisasi::Selesai);

        $this->tutupTahunLama();

        $berjalanTahunBaru = $this->realisasi($this->baru->refresh(), EnumStatusRealisasi::VerifikasiRektor);

        Livewire::test(PenyelesaianTahunLalu::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$tunggakan])
            ->assertCanNotSeeTableRecords([$selesai, $berjalanTahunBaru]);
    }

    /**
     * Laporan yang selisihnya masih menunggu Biro Keuangan ikut menjadi tunggakan
     * walaupun realisasinya sendiri sudah selesai — sebab itulah yang menahan
     * penguncian tahun kerja.
     */
    public function test_selisih_anggaran_yang_menunggu_ikut_menjadi_tunggakan(): void
    {
        $realisasi = $this->realisasiSelisihMenunggu();

        $this->tutupTahunLama();

        Livewire::test(PenyelesaianTahunLalu::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$realisasi]);
    }

    public function test_biro_keuangan_menuntaskan_selisih_anggaran_dari_halaman_ini(): void
    {
        $realisasi = $this->realisasiSelisihMenunggu();

        $this->tutupTahunLama();

        Livewire::test(PenyelesaianTahunLalu::class)
            ->callAction(TestAction::make('tuntaskanSelisihAnggaran')->table($realisasi), [
                'cara_penyelesaian_anggaran' => EnumCaraPenyelesaianAnggaran::TalanganUnitKerja->value,
            ])
            ->assertNotified('Selisih anggaran dituntaskan');

        $realisasi->refresh();

        $this->assertSame(EnumStatusPenyelesaianAnggaran::Dilunasi, $realisasi->status_penyelesaian_anggaran);
        $this->assertSame(EnumCaraPenyelesaianAnggaran::TalanganUnitKerja, $realisasi->cara_penyelesaian_anggaran);
        $this->assertNotNull($realisasi->penyelesaian_anggaran_at);

        // Selisih yang tuntas langsung menyesuaikan anggaran unit kerja.
        $this->assertSame(-500_000.0, $realisasi->penyesuaianAnggaran());

        Livewire::test(PenyelesaianTahunLalu::class)
            ->assertOk()
            ->assertCanNotSeeTableRecords([$realisasi]);
    }

    /**
     * Pencairan tambahan yang belum terjadi tidak boleh dipilih ulang, sebab
     * statusnya kembali Menunggu dan tunggakan berputar pada keadaan yang sama.
     */
    public function test_pencairan_tambahan_bukan_pilihan_penuntasan(): void
    {
        $realisasi = $this->realisasiSelisihMenunggu();

        $this->tutupTahunLama();

        Livewire::test(PenyelesaianTahunLalu::class)
            ->callAction(TestAction::make('tuntaskanSelisihAnggaran')->table($realisasi), [
                'cara_penyelesaian_anggaran' => EnumCaraPenyelesaianAnggaran::PencairanTambahan->value,
            ])
            ->assertHasActionErrors(['cara_penyelesaian_anggaran']);

        $this->assertSame(
            EnumStatusPenyelesaianAnggaran::Menunggu,
            $realisasi->refresh()->status_penyelesaian_anggaran,
        );
    }

    /**
     * Alur verifikasi tunggakan berjalan penuh dari halaman ini: Rektor menyetujui,
     * Wakil Rektor menyetujui, Biro Keuangan menjadwalkan lalu menandai cair.
     */
    public function test_seluruh_tahap_verifikasi_berjalan_dari_halaman_ini(): void
    {
        $realisasi = $this->realisasi($this->lama, EnumStatusRealisasi::Diajukan);

        $this->tutupTahunLama();

        Livewire::test(PenyelesaianTahunLalu::class)
            ->callAction(TestAction::make('setujui')->table($realisasi), ['mode' => 'sesuai'])
            ->assertHasNoActionErrors();

        $this->assertSame(EnumStatusRealisasi::VerifikasiWakil, $realisasi->refresh()->status);

        Livewire::test(PenyelesaianTahunLalu::class)
            ->callAction(TestAction::make('setujui')->table($realisasi), ['mode' => 'sesuai'])
            ->assertHasNoActionErrors();

        $this->assertSame(EnumStatusRealisasi::VerifikasiKeuangan, $realisasi->refresh()->status);

        $jadwal = JadwalPencairan::create([
            'tahun_kerja_id' => $this->lama->id,
            'name' => 'Pencairan Susulan',
            'tanggal_pencairan' => now()->addWeek(),
        ]);

        Livewire::test(PenyelesaianTahunLalu::class)
            ->callAction(TestAction::make('prosesPencairan')->table($realisasi), [
                'jadwal_pencairan_id' => $jadwal->id,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(EnumStatusRealisasi::Dijadwalkan, $realisasi->refresh()->status);

        Livewire::test(PenyelesaianTahunLalu::class)
            ->callAction(TestAction::make('tandaiDicairkan')->table($realisasi))
            ->assertHasNoActionErrors();

        $this->assertSame(EnumStatusRealisasi::MenungguLaporan, $realisasi->refresh()->status);
    }

    /**
     * Tahun Penutupan menolak realisasi baru, tetapi tunggakan yang diminta revisi
     * tetap harus bisa dikirim ulang — kalau tidak, tahun lama tak akan pernah bisa
     * dikunci.
     */
    public function test_perbaikan_revisi_tetap_bisa_dikirim_pada_tahun_penutupan(): void
    {
        $realisasi = $this->realisasi($this->lama, EnumStatusRealisasi::VerifikasiRektor);

        $this->tutupTahunLama();

        Livewire::test(PenyelesaianTahunLalu::class)
            ->callAction(TestAction::make('revisi')->table($realisasi), ['catatan' => 'Lampirkan rincian biaya.'])
            ->assertHasNoActionErrors();

        $realisasi->refresh();

        $this->assertSame(EnumStatusRealisasi::Revisi, $realisasi->status);
        $this->assertTrue($realisasi->dapatDiajukan());

        Livewire::test(PenyelesaianTahunLalu::class)
            ->callAction(TestAction::make('ajukanRealisasi')->table($realisasi), [
                'proposal_path' => [UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf')],
            ])
            ->assertNotified('Realisasi berhasil diajukan');

        $this->assertSame(EnumStatusRealisasi::VerifikasiRektor, $realisasi->refresh()->status);
    }

    public function test_menu_navigasi_tersembunyi_tanpa_tahun_penutupan(): void
    {
        $this->assertFalse(PenyelesaianTahunLalu::shouldRegisterNavigation());

        $this->realisasi($this->lama, EnumStatusRealisasi::VerifikasiRektor);
        $this->tutupTahunLama();

        $this->assertTrue(PenyelesaianTahunLalu::shouldRegisterNavigation());
        $this->assertSame('1', PenyelesaianTahunLalu::getNavigationBadge());
    }

    /**
     * Unit kerja lain tidak melihat tunggakan yang bukan miliknya, mengikuti aturan
     * pembatasan data yang sama dengan menu Realisasi Program Kerja.
     */
    public function test_pembatasan_data_unit_kerja_berlaku(): void
    {
        $tunggakan = $this->realisasi($this->lama, EnumStatusRealisasi::VerifikasiRektor);

        $this->tutupTahunLama();

        $this->actingAs(User::factory()->create([
            'unit_kerja_id' => UnitKerja::create(['name' => 'Fakultas Hukum'])->id,
        ]));

        Livewire::test(PenyelesaianTahunLalu::class)
            ->assertOk()
            ->assertCanNotSeeTableRecords([$tunggakan]);
    }

    /**
     * Menutup TA 2026 dengan memulai TA 2027, persis seperti yang dilakukan dari
     * halaman Pengaturan Program Kerja.
     */
    protected function tutupTahunLama(): void
    {
        app(TransisiTahunKerja::class)->mulaiTahunKerja($this->baru);

        $this->assertSame(EnumStatusTahunKerja::Penutupan, $this->lama->refresh()->status);
    }

    /**
     * Realisasi yang sudah selesai tetapi kekurangan anggarannya masih menunggu
     * pencairan tambahan dari Biro Keuangan.
     */
    protected function realisasiSelisihMenunggu(): RealisasiProgramKerja
    {
        $realisasi = $this->realisasi($this->lama, EnumStatusRealisasi::Selesai);

        $realisasi->update([
            'status_anggaran' => EnumStatusAnggaran::Kurang,
            'nominal_selisih_anggaran' => 500_000,
            'cara_penyelesaian_anggaran' => EnumCaraPenyelesaianAnggaran::PencairanTambahan,
            'status_penyelesaian_anggaran' => EnumStatusPenyelesaianAnggaran::Menunggu,
        ]);

        return $realisasi;
    }

    protected function realisasi(TahunKerja $tahunKerja, EnumStatusRealisasi $status): RealisasiProgramKerja
    {
        $penawaran = PenawaranProgramKerja::create([
            'tahun_kerja_id' => $tahunKerja->id,
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
            'anggaran_digunakan' => 1_000_000,
            'status' => $status,
        ]);
    }
}
