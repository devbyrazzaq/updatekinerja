<?php

namespace Tests\Feature;

use App\Enums\EnumStatusAnggaran;
use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusPenyelesaianAnggaran;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Resources\RealisasiProgramKerjas\Pages\ListRealisasiProgramKerjas;
use App\Filament\Resources\RealisasiProgramKerjas\Pages\ViewRealisasiProgramKerja;
use App\Filament\Resources\RealisasiProgramKerjas\RealisasiProgramKerjaResource;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class LaporanRealisasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake($this->disk());

        $this->actingAs(User::factory()->create());
    }

    public function test_laporan_wajib_lengkap(): void
    {
        $realisasi = $this->seedRealisasi();

        Livewire::test(ListRealisasiProgramKerjas::class)
            ->callAction(TestAction::make('unggahLaporan')->table($realisasi), [
                'evaluasi_pengerjaan' => null,
                'status_anggaran' => null,
                'persentase_ketercapaian' => null,
                'laporan_path' => null,
            ])
            ->assertHasActionErrors([
                // RichEditor kosong ditolak lewat aturan tertutupnya sendiri, bukan rule `required`.
                'evaluasi_pengerjaan',
                'status_anggaran' => 'required',
                'persentase_ketercapaian' => 'required',
                'laporan_path' => 'required',
            ]);

        $this->assertSame(EnumStatusRealisasi::MenungguLaporan, $realisasi->refresh()->status);
    }

    public function test_selisih_dan_tindak_lanjut_wajib_saat_anggaran_tidak_habis(): void
    {
        $realisasi = $this->seedRealisasi();

        Livewire::test(ListRealisasiProgramKerjas::class)
            ->callAction(TestAction::make('unggahLaporan')->table($realisasi), [
                'evaluasi_pengerjaan' => '<p>Kegiatan terlaksana.</p>',
                'status_anggaran' => EnumStatusAnggaran::Sisa->value,
                'nominal_selisih_anggaran' => null,
                'status_penyelesaian_anggaran' => null,
                'persentase_ketercapaian' => 80,
                'laporan_path' => [UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf')],
            ])
            ->assertHasActionErrors([
                'nominal_selisih_anggaran' => 'required',
                'status_penyelesaian_anggaran' => 'required',
            ]);
    }

    public function test_persentase_ketercapaian_dibatasi_maksimal_100(): void
    {
        $realisasi = $this->seedRealisasi();

        Livewire::test(ListRealisasiProgramKerjas::class)
            ->callAction(TestAction::make('unggahLaporan')->table($realisasi), [
                'evaluasi_pengerjaan' => '<p>Kegiatan terlaksana.</p>',
                'status_anggaran' => EnumStatusAnggaran::Habis->value,
                'persentase_ketercapaian' => 120,
                'laporan_path' => [UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf')],
            ])
            ->assertHasActionErrors(['persentase_ketercapaian' => 'max']);
    }

    public function test_persentase_ketercapaian_tidak_boleh_mundur_dari_realisasi_sebelumnya(): void
    {
        $realisasi = $this->seedRealisasi();
        $this->seedRealisasiLain($realisasi->pengajuan_program_kerja_id, persentase: 70);

        $this->assertSame(70, $realisasi->persentaseKetercapaianMinimum());

        Livewire::test(ListRealisasiProgramKerjas::class)
            ->callAction(TestAction::make('unggahLaporan')->table($realisasi), [
                'evaluasi_pengerjaan' => '<p>Kegiatan terlaksana.</p>',
                'status_anggaran' => EnumStatusAnggaran::Habis->value,
                'persentase_ketercapaian' => 60,
                'laporan_path' => [UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf')],
            ])
            ->assertHasActionErrors(['persentase_ketercapaian' => 'min']);
    }

    public function test_laporan_dengan_anggaran_tergunakan_semua(): void
    {
        $realisasi = $this->seedRealisasi(alokasi: 20_000_000);

        $this->kirimLaporan($realisasi, [
            'status_anggaran' => EnumStatusAnggaran::Habis->value,
            'persentase_ketercapaian' => 100,
        ]);

        $realisasi->refresh();

        $this->assertSame(EnumStatusRealisasi::VerifikasiLaporan, $realisasi->status);
        $this->assertSame(EnumStatusAnggaran::Habis, $realisasi->status_anggaran);
        $this->assertSame(20_000_000.0, (float) $realisasi->anggaran_digunakan);
        $this->assertNull($realisasi->nominal_selisih_anggaran);
        $this->assertNull($realisasi->status_penyelesaian_anggaran);
        $this->assertSame(0.0, $realisasi->penyesuaianAnggaran());
        $this->assertNotNull($realisasi->laporan_diserahkan_at);
        $this->assertTrue($realisasi->sudahAdaLaporan());
        Storage::disk($this->disk())->assertExists($realisasi->laporan_path);
    }

    public function test_laporan_dengan_anggaran_bersisa_yang_dikembalikan(): void
    {
        $realisasi = $this->seedRealisasi(alokasi: 20_000_000);

        $this->kirimLaporan($realisasi, [
            'status_anggaran' => EnumStatusAnggaran::Sisa->value,
            'nominal_selisih_anggaran' => 2_000_000,
            'status_penyelesaian_anggaran' => EnumStatusPenyelesaianAnggaran::Dikembalikan->value,
            'persentase_ketercapaian' => 90,
        ]);

        $realisasi->refresh();

        $this->assertSame(EnumStatusAnggaran::Sisa, $realisasi->status_anggaran);
        $this->assertSame(18_000_000.0, (float) $realisasi->anggaran_digunakan);
        $this->assertSame(2_000_000.0, (float) $realisasi->nominal_selisih_anggaran);
        $this->assertSame(2_000_000.0, $realisasi->selisihAnggaran());
        $this->assertSame(EnumStatusPenyelesaianAnggaran::Dikembalikan, $realisasi->status_penyelesaian_anggaran);
        $this->assertNotNull($realisasi->penyelesaian_anggaran_at);
        $this->assertSame(2_000_000.0, $realisasi->penyesuaianAnggaran());
        $this->assertSame(90, $realisasi->persentase_ketercapaian);
    }

    public function test_laporan_dengan_anggaran_kurang_yang_masih_menunggu_keuangan(): void
    {
        $realisasi = $this->seedRealisasi(alokasi: 20_000_000);

        $this->kirimLaporan($realisasi, [
            'status_anggaran' => EnumStatusAnggaran::Kurang->value,
            'nominal_selisih_anggaran' => 3_000_000,
            'status_penyelesaian_anggaran' => EnumStatusPenyelesaianAnggaran::Menunggu->value,
            'persentase_ketercapaian' => 100,
        ]);

        $realisasi->refresh();

        $this->assertSame(EnumStatusAnggaran::Kurang, $realisasi->status_anggaran);
        $this->assertSame(23_000_000.0, (float) $realisasi->anggaran_digunakan);
        $this->assertSame(-3_000_000.0, $realisasi->selisihAnggaran());
        $this->assertSame(EnumStatusPenyelesaianAnggaran::Menunggu, $realisasi->status_penyelesaian_anggaran);
        $this->assertNull($realisasi->penyelesaian_anggaran_at);
        $this->assertSame(0.0, $realisasi->penyesuaianAnggaran());
    }

    public function test_penyesuaian_anggaran_unit_kerja_dari_selisih_yang_dituntaskan(): void
    {
        $realisasi = $this->seedRealisasi(alokasi: 20_000_000);
        $pengajuan = $realisasi->pengajuanProgramKerja;

        $realisasi->update([
            'status_anggaran' => EnumStatusAnggaran::Sisa,
            'nominal_selisih_anggaran' => 2_000_000,
            'status_penyelesaian_anggaran' => EnumStatusPenyelesaianAnggaran::Dikembalikan,
        ]);

        $this->seedRealisasiLain($pengajuan->id, persentase: 50)->update([
            'status_anggaran' => EnumStatusAnggaran::Kurang,
            'nominal_selisih_anggaran' => 500_000,
            'status_penyelesaian_anggaran' => EnumStatusPenyelesaianAnggaran::Dilunasi,
        ]);

        // Selisih yang masih menunggu Biro Keuangan tidak ikut menyesuaikan anggaran.
        $this->seedRealisasiLain($pengajuan->id, persentase: 40)->update([
            'status_anggaran' => EnumStatusAnggaran::Sisa,
            'nominal_selisih_anggaran' => 9_000_000,
            'status_penyelesaian_anggaran' => EnumStatusPenyelesaianAnggaran::Menunggu,
        ]);

        $tahunKerjaId = $pengajuan->penawaranProgramKerja->tahun_kerja_id;

        $this->assertSame(
            1_500_000.0,
            RealisasiProgramKerja::totalPenyesuaianAnggaran($pengajuan->unit_kerja_id, $tahunKerjaId),
        );
    }

    public function test_halaman_detail_menampilkan_aksi_kirim_laporan_dan_target(): void
    {
        $realisasi = $this->seedRealisasi();

        $this->get(RealisasiProgramKerjaResource::getUrl('view', ['record' => $realisasi]))
            ->assertOk()
            ->assertSee('Target Program Kerja')
            ->assertSee('Kirim Laporan Realisasi');

        Livewire::test(ViewRealisasiProgramKerja::class, ['record' => $realisasi->uuid])
            ->assertActionVisible('unggahLaporan')
            ->callAction('unggahLaporan', [
                'evaluasi_pengerjaan' => '<p>Kegiatan terlaksana sesuai rencana.</p>',
                'status_anggaran' => EnumStatusAnggaran::Habis->value,
                'persentase_ketercapaian' => 100,
                'laporan_path' => [UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf')],
            ])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $this->assertSame(EnumStatusRealisasi::VerifikasiLaporan, $realisasi->refresh()->status);
    }

    public function test_revisi_laporan_diperbaiki_lewat_aksi_perbaiki_laporan_bukan_edit_mode(): void
    {
        $realisasi = $this->seedRevisiLaporan();

        $this->assertTrue($realisasi->adalahRevisiLaporan());
        $this->assertFalse(RealisasiProgramKerjaResource::canEdit($realisasi));

        Livewire::test(ViewRealisasiProgramKerja::class, ['record' => $realisasi->uuid])
            ->assertActionVisible('unggahLaporan')
            ->assertActionHasLabel('unggahLaporan', 'Perbaiki Laporan')
            ->assertActionHidden('edit')
            // Revisi laporan tidak boleh diajukan ulang lewat perbaikan proposal.
            ->assertActionHidden('ajukanRealisasi')
            ->callAction('unggahLaporan', [
                'evaluasi_pengerjaan' => '<p>Rincian penggunaan anggaran sudah dilengkapi.</p>',
                'status_anggaran' => EnumStatusAnggaran::Habis->value,
                'persentase_ketercapaian' => 100,
                'laporan_path' => [UploadedFile::fake()->create('laporan-perbaikan.pdf', 100, 'application/pdf')],
            ])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $realisasi->refresh();

        $this->assertSame(EnumStatusRealisasi::VerifikasiLaporan, $realisasi->status);
        $this->assertNull($realisasi->catatan_verifikasi);
        $this->assertStringContainsString('mengajukan kembali', (string) $realisasi->logs()->latest('id')->value('description'));
    }

    public function test_realisasi_tidak_dapat_dibatalkan_dan_tanpa_opsi_ajukan_kembali(): void
    {
        $realisasi = $this->seedRevisiLaporan();

        Livewire::test(ViewRealisasiProgramKerja::class, ['record' => $realisasi->uuid])
            ->assertActionDoesNotExist('batalkan')
            ->assertActionDoesNotExist('ajukanKembali');
    }

    public function test_revisi_tahap_proposal_tetap_diperbaiki_lewat_pengajuan_ulang(): void
    {
        $realisasi = $this->seedRealisasi();
        $realisasi->catatLog(EnumStatusRealisasi::Diajukan, auth()->id());
        $realisasi->update([
            'status' => EnumStatusRealisasi::Revisi,
            'catatan_verifikasi' => '<p>Rincian anggaran proposal belum sesuai.</p>',
        ]);
        $realisasi->catatLog(EnumStatusRealisasi::Revisi, auth()->id());

        $realisasi->refresh();

        $this->assertFalse($realisasi->adalahRevisiLaporan());
        $this->assertTrue(RealisasiProgramKerjaResource::canEdit($realisasi));

        Livewire::test(ViewRealisasiProgramKerja::class, ['record' => $realisasi->uuid])
            ->assertActionVisible('ajukanRealisasi')
            ->assertActionHasLabel('ajukanRealisasi', 'Perbaiki Proposal')
            ->assertActionHidden('unggahLaporan')
            ->callAction('ajukanRealisasi', [
                'proposal_path' => [UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf')],
            ])
            ->assertHasNoActionErrors();

        $realisasi->refresh();

        $this->assertSame(EnumStatusRealisasi::Diajukan, $realisasi->status);
        $this->assertNull($realisasi->catatan_verifikasi);
    }

    public function test_halaman_detail_menampilkan_aksi_pratinjau_dokumen(): void
    {
        $realisasi = $this->seedRealisasi();
        $realisasi->update([
            'proposal_path' => 'proposal-realisasi/proposal.pdf',
            'laporan_path' => 'laporan-realisasi/laporan.pdf',
            'laporan_diserahkan_at' => now(),
        ]);

        $this->get(RealisasiProgramKerjaResource::getUrl('view', ['record' => $realisasi]))
            ->assertOk()
            ->assertSee('Dokumen Proposal')
            ->assertSee('Dokumen Laporan')
            ->assertSee('Pratinjau');
    }

    protected function disk(): string
    {
        return config('filament.default_filesystem_disk');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function kirimLaporan(RealisasiProgramKerja $realisasi, array $data): void
    {
        Livewire::test(ListRealisasiProgramKerjas::class)
            ->callAction(TestAction::make('unggahLaporan')->table($realisasi), [
                'evaluasi_pengerjaan' => '<p>Kegiatan terlaksana sesuai rencana.</p>',
                'laporan_path' => [UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf')],
                ...$data,
            ])
            ->assertHasNoActionErrors()
            ->assertNotified();
    }

    /**
     * Realisasi yang laporannya sudah dikirim lalu diminta revisi oleh verifikator
     * laporan, sehingga perbaikannya adalah laporan itu sendiri.
     */
    protected function seedRevisiLaporan(): RealisasiProgramKerja
    {
        $realisasi = $this->seedRealisasi();

        $this->kirimLaporan($realisasi, [
            'status_anggaran' => EnumStatusAnggaran::Habis->value,
            'persentase_ketercapaian' => 90,
        ]);

        $realisasi->refresh()->update([
            'status' => EnumStatusRealisasi::Revisi,
            'catatan_verifikasi' => '<p>Rincian penggunaan anggaran belum dilampirkan.</p>',
        ]);
        $realisasi->catatLog(EnumStatusRealisasi::Revisi, auth()->id(), '<p>Rincian penggunaan anggaran belum dilampirkan.</p>');

        return $realisasi->refresh();
    }

    /**
     * Realisasi lain atas pengajuan yang sama, dipakai untuk menguji batas bawah
     * ketercapaian dan akumulasi penyesuaian anggaran.
     */
    protected function seedRealisasiLain(int $pengajuanId, int $persentase): RealisasiProgramKerja
    {
        return RealisasiProgramKerja::create([
            'pengajuan_program_kerja_id' => $pengajuanId,
            'name' => 'Realisasi Lain '.$persentase,
            'anggaran_digunakan' => 1_000_000,
            'persentase_ketercapaian' => $persentase,
            'status' => EnumStatusRealisasi::Selesai,
        ]);
    }

    protected function seedRealisasi(float $alokasi = 20_000_000): RealisasiProgramKerja
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
        auth()->user()->update(['unit_kerja_id' => $unit->id]);

        $penawaran = PenawaranProgramKerja::create([
            'tahun_kerja_id' => $tahunKerja->id,
            'unit_kerja_id' => $unit->id,
            'bidang_id' => Bidang::create(['code' => 'B1', 'name' => 'Akademik'])->id,
            'kategori_id' => Kategori::create(['code' => 'K1', 'name' => 'Pendidikan'])->id,
            'program_id' => Program::create(['name' => 'Tridharma'])->id,
            'name' => 'Workshop Kurikulum',
            'target' => '3 kegiatan per tahun',
            'indikator' => 'Jumlah workshop terlaksana',
            'nilai_standar' => '3',
            'satuan_nilai_standar' => 'kegiatan',
            'is_active' => true,
        ]);

        $pengajuan = PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $unit->id,
            'alokasi_anggaran' => $alokasi,
            'status' => EnumStatusPengajuan::Diterima,
        ]);

        return RealisasiProgramKerja::create([
            'pengajuan_program_kerja_id' => $pengajuan->id,
            'name' => 'Pelaksanaan Workshop',
            'anggaran_digunakan' => $alokasi,
            'status' => EnumStatusRealisasi::MenungguLaporan,
        ]);
    }
}
