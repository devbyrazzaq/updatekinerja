<?php

namespace Tests\Feature;

use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Resources\VerifikasiBiroKeuangans\Pages\ListVerifikasiBiroKeuangans;
use App\Filament\Resources\VerifikasiLaporans\Pages\ListVerifikasiLaporans;
use App\Filament\Resources\VerifikasiPengajuans\Pages\ListVerifikasiPengajuans;
use App\Filament\Resources\VerifikasiRektors\Pages\ListVerifikasiRektors;
use App\Filament\Resources\VerifikasiWakilRektors\Pages\ListVerifikasiWakilRektors;
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
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Penyaring unit kerja dan rentang waktu pengajuan yang dipakai bersama seluruh
 * menu Verifikasi.
 */
class VerifikasiTableFilterTest extends TestCase
{
    use RefreshDatabase;

    private TahunKerja $tahunKerja;

    private UnitKerja $unitA;

    private UnitKerja $unitB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $periode = Periode::create(['name' => 'P1', 'start_datetime' => now(), 'end_datetime' => now()->addYear()]);
        $this->tahunKerja = TahunKerja::create(['periode_id' => $periode->id, 'name' => 'TA 2026', 'start_datetime' => now()->subYear(), 'end_datetime' => now()->addYear(), 'status' => EnumStatusTahunKerja::Berjalan]);
        $this->unitA = UnitKerja::create(['name' => 'Unit A']);
        $this->unitB = UnitKerja::create(['name' => 'Unit B']);
    }

    private function pengajuan(UnitKerja $unit, string $diajukanPada): PengajuanProgramKerja
    {
        $penawaran = PenawaranProgramKerja::create([
            'tahun_kerja_id' => $this->tahunKerja->id,
            'unit_kerja_id' => $unit->id,
            'bidang_id' => Bidang::create(['code' => 'B'.fake()->unique()->numerify('##'), 'name' => 'B'])->id,
            'kategori_id' => Kategori::create(['code' => 'K'.fake()->unique()->numerify('##'), 'name' => 'K'])->id,
            'program_id' => Program::create(['name' => 'P'])->id,
            'name' => 'Prokerja '.$unit->name,
            'is_active' => true,
        ]);

        $pengajuan = PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $unit->id,
            'alokasi_anggaran' => 10000000,
            'status' => EnumStatusPengajuan::Diajukan,
        ]);

        $pengajuan->forceFill(['created_at' => $diajukanPada])->save();

        return $pengajuan->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function realisasi(UnitKerja $unit, string $diajukanPada, array $attributes): RealisasiProgramKerja
    {
        $pengajuan = $this->pengajuan($unit, $diajukanPada);
        $pengajuan->update(['status' => EnumStatusPengajuan::Diterima, 'verifikator_id' => auth()->id(), 'diverifikasi_at' => now()]);

        $realisasi = RealisasiProgramKerja::create([
            'pengajuan_program_kerja_id' => $pengajuan->id,
            'name' => 'Kegiatan '.$unit->name,
            'anggaran_digunakan' => 8000000,
            ...$attributes,
        ]);

        $realisasi->forceFill(['created_at' => $diajukanPada])->save();

        return $realisasi->refresh();
    }

    public function test_verifikasi_pengajuan_disaring_unit_kerja_dan_rentang_waktu(): void
    {
        $lama = $this->pengajuan($this->unitA, '2026-01-10 08:00:00');
        $baru = $this->pengajuan($this->unitB, '2026-03-20 08:00:00');

        Livewire::test(ListVerifikasiPengajuans::class)
            ->assertCanSeeTableRecords([$lama, $baru])
            ->filterTable('unit_kerja_id', $this->unitA->id)
            ->assertCanSeeTableRecords([$lama])
            ->assertCanNotSeeTableRecords([$baru])
            ->resetTableFilters()
            ->filterTable('waktu_pengajuan', ['dari' => '2026-03-01', 'sampai' => '2026-03-31'])
            ->assertCanSeeTableRecords([$baru])
            ->assertCanNotSeeTableRecords([$lama]);
    }

    public function test_rentang_waktu_inklusif_pada_kedua_ujungnya(): void
    {
        $pengajuan = $this->pengajuan($this->unitA, '2026-02-15 23:30:00');

        Livewire::test(ListVerifikasiPengajuans::class)
            ->filterTable('waktu_pengajuan', ['dari' => '2026-02-15', 'sampai' => '2026-02-15'])
            ->assertCanSeeTableRecords([$pengajuan]);
    }

    public function test_verifikasi_rektor_dan_wakil_rektor_disaring_unit_kerja_pengaju(): void
    {
        $rektorA = $this->realisasi($this->unitA, '2026-01-10 08:00:00', ['status' => EnumStatusRealisasi::VerifikasiRektor]);
        $rektorB = $this->realisasi($this->unitB, '2026-01-10 08:00:00', ['status' => EnumStatusRealisasi::VerifikasiRektor]);

        Livewire::test(ListVerifikasiRektors::class)
            ->assertCanSeeTableRecords([$rektorA, $rektorB])
            ->filterTable('unit_kerja_id', $this->unitA->id)
            ->assertCanSeeTableRecords([$rektorA])
            ->assertCanNotSeeTableRecords([$rektorB]);

        $wakilA = $this->realisasi($this->unitA, '2026-01-10 08:00:00', ['status' => EnumStatusRealisasi::VerifikasiWakil]);
        $wakilB = $this->realisasi($this->unitB, '2026-01-10 08:00:00', ['status' => EnumStatusRealisasi::VerifikasiWakil]);

        Livewire::test(ListVerifikasiWakilRektors::class)
            ->filterTable('unit_kerja_id', $this->unitB->id)
            ->assertCanSeeTableRecords([$wakilB])
            ->assertCanNotSeeTableRecords([$wakilA]);
    }

    public function test_verifikasi_biro_keuangan_disaring_rentang_waktu_pengajuan(): void
    {
        $lama = $this->realisasi($this->unitA, '2026-01-10 08:00:00', ['status' => EnumStatusRealisasi::VerifikasiKeuangan]);
        $baru = $this->realisasi($this->unitB, '2026-04-05 08:00:00', ['status' => EnumStatusRealisasi::VerifikasiKeuangan]);

        Livewire::test(ListVerifikasiBiroKeuangans::class)
            ->filterTable('waktu_pengajuan', ['dari' => '2026-04-01'])
            ->assertCanSeeTableRecords([$baru])
            ->assertCanNotSeeTableRecords([$lama]);
    }

    /**
     * Pada tahap laporan, rentang waktu mengikuti tanggal laporan diserahkan.
     */
    public function test_verifikasi_laporan_disaring_tanggal_laporan_diserahkan(): void
    {
        $lama = $this->realisasi($this->unitA, '2026-01-10 08:00:00', [
            'status' => EnumStatusRealisasi::VerifikasiLaporan,
            'laporan_diserahkan_at' => '2026-05-02 09:00:00',
        ]);
        $baru = $this->realisasi($this->unitB, '2026-01-10 08:00:00', [
            'status' => EnumStatusRealisasi::VerifikasiLaporan,
            'laporan_diserahkan_at' => '2026-06-11 09:00:00',
        ]);

        Livewire::test(ListVerifikasiLaporans::class)
            ->assertCanSeeTableRecords([$lama, $baru])
            ->filterTable('waktu_pengajuan', ['sampai' => '2026-05-31'])
            ->assertCanSeeTableRecords([$lama])
            ->assertCanNotSeeTableRecords([$baru])
            ->resetTableFilters()
            ->filterTable('unit_kerja_id', $this->unitB->id)
            ->assertCanSeeTableRecords([$baru])
            ->assertCanNotSeeTableRecords([$lama]);
    }
}
