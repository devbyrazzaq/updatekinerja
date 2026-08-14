<?php

namespace Tests\Feature;

use App\Models\RealisasiProgramKerja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealisasiNominalTest extends TestCase
{
    use RefreshDatabase;

    public function test_nominal_diajukan_terisi_dari_anggaran_saat_dibuat(): void
    {
        $realisasi = RealisasiProgramKerja::factory()->create(['anggaran_digunakan' => 10_000_000]);

        $this->assertSame('10000000.00', $realisasi->nominal_diajukan);
        $this->assertSame(10_000_000.0, $realisasi->nominalDiajukan());
    }

    public function test_nominal_diajukan_terkunci_setelah_laporan_menimpa_anggaran_digunakan(): void
    {
        $realisasi = RealisasiProgramKerja::factory()->create(['anggaran_digunakan' => 10_000_000]);

        // Laporan akhir menimpa anggaran_digunakan menjadi realisasi akhir.
        $realisasi->update([
            'laporan_diserahkan_at' => now(),
            'anggaran_digunakan' => 6_000_000,
        ]);

        $this->assertSame('10000000.00', $realisasi->refresh()->nominal_diajukan, 'Nominal diajukan tidak boleh ikut berubah oleh laporan akhir.');
        $this->assertSame('6000000.00', $realisasi->anggaran_digunakan);
    }

    public function test_persentase_persetujuan_dihitung_terhadap_nominal_diajukan(): void
    {
        $realisasi = RealisasiProgramKerja::factory()->create([
            'anggaran_digunakan' => 10_000_000,
            'nominal_disetujui' => 8_000_000,
        ]);

        $this->assertSame(80, $realisasi->persentasePersetujuan());

        // Persentase tetap mengacu ke nominal diajukan meski laporan akhir berbeda.
        $realisasi->update(['laporan_diserahkan_at' => now(), 'anggaran_digunakan' => 5_000_000]);

        $this->assertSame(80, $realisasi->refresh()->persentasePersetujuan());
    }

    public function test_persentase_persetujuan_null_tanpa_nominal_disetujui(): void
    {
        $realisasi = RealisasiProgramKerja::factory()->create([
            'anggaran_digunakan' => 10_000_000,
            'nominal_disetujui' => null,
        ]);

        $this->assertNull($realisasi->persentasePersetujuan());
        $this->assertNull($realisasi->nominalDisetujui());
    }
}
