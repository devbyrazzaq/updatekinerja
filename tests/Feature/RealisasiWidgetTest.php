<?php

namespace Tests\Feature;

use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Resources\RealisasiProgramKerjas\Widgets\PenggunaanAnggaranChart;
use App\Filament\Resources\RealisasiProgramKerjas\Widgets\RealisasiProgramKerjaOverview;
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
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Widget halaman Data Realisasi Program Kerja: ringkasan berpersentase dan grafik
 * batang penggunaan anggaran menurut bulan pencairan.
 */
class RealisasiWidgetTest extends TestCase
{
    use RefreshDatabase;

    private TahunKerja $tahunKerja;

    private UnitKerja $unitA;

    private UnitKerja $unitB;

    protected function setUp(): void
    {
        parent::setUp();

        $periode = Periode::create(['name' => 'P1', 'start_datetime' => now(), 'end_datetime' => now()->addYear()]);
        // Rentang tahun kerja menjadi sumbu bulan grafik: Januari–Desember 2026.
        $this->tahunKerja = TahunKerja::create([
            'periode_id' => $periode->id,
            'name' => 'TA 2026',
            'start_datetime' => Carbon::create(2026, 1, 1),
            'end_datetime' => Carbon::create(2026, 12, 31),
            'status' => EnumStatusTahunKerja::Berjalan,
        ]);

        $this->unitA = UnitKerja::create(['name' => 'Unit A']);
        $this->unitB = UnitKerja::create(['name' => 'Unit B']);

        $user = User::factory()->create(['unit_kerja_id' => null]);
        $user->givePermissionTo(Permission::findOrCreate('bypass_data_scope', 'web'));

        $this->actingAs($user);
    }

    public function test_ringkasan_menampilkan_persentase_pada_deskripsi(): void
    {
        // Diajukan 10jt, disetujui 8jt (80%); realisasi memakai 2jt dari 8jt (25%).
        $pengajuanDiterima = $this->seedPengajuan($this->unitA, 8_000_000, EnumStatusPengajuan::Diterima);
        $this->seedPengajuan($this->unitA, 2_000_000, EnumStatusPengajuan::Ditolak);

        $this->seedRealisasi($pengajuanDiterima, 2_000_000, EnumStatusRealisasi::Selesai);

        Livewire::test(RealisasiProgramKerjaOverview::class, ['unitKerjaId' => null])
            ->assertOk()
            ->assertSee('80% dari anggaran yang diajukan')
            ->assertSee('25% dari anggaran disetujui')
            ->assertSee('75% dari anggaran disetujui belum terpakai')
            ->assertSee('100% sudah selesai');
    }

    public function test_ringkasan_menandai_anggaran_yang_melebihi_persetujuan(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 4_000_000, EnumStatusPengajuan::Diterima);
        $this->seedRealisasi($pengajuan, 5_000_000, EnumStatusRealisasi::VerifikasiLaporan);

        Livewire::test(RealisasiProgramKerjaOverview::class, ['unitKerjaId' => null])
            ->assertOk()
            ->assertSee('125% dari anggaran disetujui')
            ->assertSee('25% melebihi anggaran disetujui');
    }

    public function test_sumbu_grafik_membentang_sepanjang_rentang_waktu_tahun_kerja(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 20_000_000, EnumStatusPengajuan::Diterima);

        $this->seedRealisasi($pengajuan, 1_000_000, EnumStatusRealisasi::Selesai, Carbon::create(2026, 1, 10));
        $this->seedRealisasi($pengajuan, 500_000, EnumStatusRealisasi::Selesai, Carbon::create(2026, 1, 25));
        $this->seedRealisasi($pengajuan, 2_000_000, EnumStatusRealisasi::Selesai, Carbon::create(2026, 3, 5));
        // Belum dicairkan: tidak boleh ikut terhitung.
        $this->seedRealisasi($pengajuan, 9_000_000, EnumStatusRealisasi::Dijadwalkan);

        $data = $this->dataGrafik(new PenggunaanAnggaranChart);

        $this->assertSame(
            ['Jan 2026', 'Feb 2026', 'Mar 2026', 'Apr 2026', 'Mei 2026', 'Jun 2026', 'Jul 2026', 'Agt 2026', 'Sep 2026', 'Okt 2026', 'Nov 2026', 'Des 2026'],
            $data['labels'],
        );
        $this->assertSame(
            [1_500_000.0, 0.0, 2_000_000.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0],
            $data['datasets'][0]['data'],
        );
    }

    public function test_sumbu_grafik_merangkul_pencairan_di_luar_rentang_tahun_kerja(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 20_000_000, EnumStatusPengajuan::Diterima);

        $this->seedRealisasi($pengajuan, 3_000_000, EnumStatusRealisasi::Selesai, Carbon::create(2025, 11, 20));

        $data = $this->dataGrafik(new PenggunaanAnggaranChart);

        $this->assertSame('Nov 2025', $data['labels'][0]);
        $this->assertSame('Des 2026', end($data['labels']));
        $this->assertSame(3_000_000.0, $data['datasets'][0]['data'][0]);
    }

    public function test_grafik_mengikuti_unit_kerja_terpilih(): void
    {
        $pengajuanA = $this->seedPengajuan($this->unitA, 10_000_000, EnumStatusPengajuan::Diterima);
        $pengajuanB = $this->seedPengajuan($this->unitB, 10_000_000, EnumStatusPengajuan::Diterima);

        $this->seedRealisasi($pengajuanA, 1_000_000, EnumStatusRealisasi::Selesai, Carbon::create(2026, 2, 1));
        $this->seedRealisasi($pengajuanB, 7_000_000, EnumStatusRealisasi::Selesai, Carbon::create(2026, 2, 1));

        $widget = new PenggunaanAnggaranChart;
        $widget->unitKerjaId = $this->unitA->id;

        $data = $this->dataGrafik($widget);

        $this->assertCount(12, $data['labels']);
        $this->assertSame('Feb 2026', $data['labels'][1]);
        $this->assertSame(1_000_000.0, $data['datasets'][0]['data'][1]);
        // Unit B tidak ikut terhitung.
        $this->assertSame(1_000_000.0, array_sum($data['datasets'][0]['data']));
    }

    public function test_grafik_kosong_saat_belum_ada_pencairan(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 10_000_000, EnumStatusPengajuan::Diterima);
        $this->seedRealisasi($pengajuan, 1_000_000, EnumStatusRealisasi::Dijadwalkan);

        $this->assertSame([], $this->dataGrafik(new PenggunaanAnggaranChart));

        Livewire::test(PenggunaanAnggaranChart::class)
            ->assertOk()
            ->assertSee('Penggunaan Anggaran');
    }

    /**
     * Data grafik yang biasanya dipakai Chart.js, diambil lewat refleksi karena
     * `getData()` bersifat protected.
     *
     * @return array<string, mixed>
     */
    private function dataGrafik(PenggunaanAnggaranChart $widget): array
    {
        $metode = new \ReflectionMethod($widget, 'getData');

        /** @var array<string, mixed> $data */
        $data = $metode->invoke($widget);

        return $data;
    }

    private function seedPengajuan(UnitKerja $unit, float $alokasi, EnumStatusPengajuan $status): PengajuanProgramKerja
    {
        $penawaran = PenawaranProgramKerja::create([
            'tahun_kerja_id' => $this->tahunKerja->id,
            'unit_kerja_id' => $unit->id,
            'bidang_id' => Bidang::firstOrCreate(['code' => 'B1'], ['name' => 'Akademik'])->id,
            'kategori_id' => Kategori::firstOrCreate(['code' => 'K1'], ['name' => 'Pendidikan'])->id,
            'program_id' => Program::firstOrCreate(['name' => 'Tridharma'])->id,
            'name' => 'Workshop '.$unit->name,
            'is_active' => true,
        ]);

        return PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $unit->id,
            'alokasi_anggaran' => $alokasi,
            'status' => $status,
        ]);
    }

    private function seedRealisasi(
        PengajuanProgramKerja $pengajuan,
        float $digunakan,
        EnumStatusRealisasi $status,
        ?Carbon $dicairkanAt = null,
    ): RealisasiProgramKerja {
        return RealisasiProgramKerja::create([
            'pengajuan_program_kerja_id' => $pengajuan->id,
            'name' => 'Pelaksanaan',
            'anggaran_digunakan' => $digunakan,
            'status' => $status,
            'dicairkan_at' => $dicairkanAt,
        ]);
    }
}
