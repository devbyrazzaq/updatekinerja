<?php

namespace Tests\Feature;

use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
use App\Enums\EnumTahapanRealisasi;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealisasiTahapanRevisiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_revisi_wakil_menempatkan_stepper_dan_label_pada_tahap_wakil(): void
    {
        $realisasi = $this->seedRealisasiRevisiWakil();

        $this->assertSame(EnumTahapanRealisasi::VerifikasiWakil, $realisasi->tahapanStepper());
        $this->assertSame('Revisi Wakil Rektor', $realisasi->labelStatus());
    }

    public function test_pengajuan_ulang_setelah_revisi_wakil_kembali_ke_wakil_bukan_rektor(): void
    {
        $realisasi = $this->seedRealisasiRevisiWakil();

        $this->assertSame(EnumStatusRealisasi::VerifikasiWakil, $realisasi->statusTujuanPengajuan());
    }

    public function test_revisi_rektor_pengajuan_ulang_kembali_ke_rektor(): void
    {
        $realisasi = $this->seedRealisasi();
        $realisasi->catatLog(EnumStatusRealisasi::Diajukan, auth()->id());
        $realisasi->update(['status' => EnumStatusRealisasi::Revisi]);
        $realisasi->catatLog(EnumStatusRealisasi::Revisi, auth()->id());

        $this->assertSame(EnumTahapanRealisasi::VerifikasiRektor, $realisasi->tahapanStepper());
        $this->assertSame('Revisi Rektor', $realisasi->labelStatus());
        $this->assertSame(EnumStatusRealisasi::Diajukan, $realisasi->statusTujuanPengajuan());
    }

    public function test_membatalkan_dari_revisi_menjadi_dibatalkan_pada_tahap_yang_sama(): void
    {
        $realisasi = $this->seedRealisasiRevisiWakil();

        $realisasi->update(['status' => EnumStatusRealisasi::Dibatalkan]);
        $realisasi->catatLog(EnumStatusRealisasi::Dibatalkan, auth()->id());

        $this->assertSame(EnumTahapanRealisasi::VerifikasiWakil, $realisasi->tahapanStepper());
        $this->assertFalse($realisasi->status->isBerjalan());
    }

    public function test_pencairan_dan_pelaksanaan_menempati_tahapan_stepper_yang_berbeda(): void
    {
        $realisasi = $this->seedRealisasi();

        $realisasi->update(['status' => EnumStatusRealisasi::Dijadwalkan]);
        $this->assertSame(EnumTahapanRealisasi::Pencairan, $realisasi->tahapanStepper());

        $realisasi->update(['status' => EnumStatusRealisasi::MenungguLaporan]);
        $this->assertSame(EnumTahapanRealisasi::Pelaksanaan, $realisasi->tahapanStepper());
    }

    public function test_deskripsi_tahap_pencairan_menyebut_tanggal_setelah_anggaran_dicairkan(): void
    {
        $realisasi = $this->seedRealisasi();

        $this->assertSame(
            EnumTahapanRealisasi::Pencairan->description(),
            $realisasi->deskripsiTahapan(EnumTahapanRealisasi::Pencairan),
        );

        $realisasi->update(['dicairkan_at' => now()->setDate(2026, 8, 1)]);

        $this->assertSame(
            'Anggaran dicairkan pada 01 Agustus 2026.',
            $realisasi->deskripsiTahapan(EnumTahapanRealisasi::Pencairan),
        );
        $this->assertSame(
            EnumTahapanRealisasi::Pelaksanaan->description(),
            $realisasi->deskripsiTahapan(EnumTahapanRealisasi::Pelaksanaan),
        );
    }

    private function seedRealisasiRevisiWakil(): RealisasiProgramKerja
    {
        $realisasi = $this->seedRealisasi();

        // Alur: diajukan -> Rektor setujui (VerifikasiWakil) -> Wakil minta revisi.
        $realisasi->catatLog(EnumStatusRealisasi::Diajukan, auth()->id());
        $realisasi->catatLog(EnumStatusRealisasi::VerifikasiWakil, auth()->id());
        $realisasi->update(['status' => EnumStatusRealisasi::Revisi]);
        $realisasi->catatLog(EnumStatusRealisasi::Revisi, auth()->id());

        return $realisasi->refresh();
    }

    private function seedRealisasi(float $alokasi = 20_000_000): RealisasiProgramKerja
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
            'status' => EnumStatusRealisasi::Draft,
        ]);
    }
}
