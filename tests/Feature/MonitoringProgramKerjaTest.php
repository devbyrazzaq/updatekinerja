<?php

namespace Tests\Feature;

use App\Enums\EnumStatusAnggaran;
use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusPenyelesaianAnggaran;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Pages\MonitoringProgramKerja as MonitoringProgramKerjaPage;
use App\Filament\Pages\PerbandinganMonitoring;
use App\Filament\Pages\RingkasanUnitKerja;
use App\Filament\Pages\Widgets\MonitoringOverview;
use App\Filament\Pages\Widgets\PenyerapanAnggaranChart;
use App\Models\Bidang;
use App\Models\Kategori;
use App\Models\PaguAnggaran;
use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use App\Models\Periode;
use App\Models\Program;
use App\Models\RealisasiProgramKerja;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\BukuAnggaran;
use App\Services\MonitoringAnggaran;
use App\Services\PermissionRegistrar;
use App\Services\RingkasanMonitoring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Monitoring program kerja: penyerapan anggaran berbasis kas beserta capaian target,
 * baik per unit kerja, direkap sekaligus, maupun disandingkan sebagai perbandingan.
 */
class MonitoringProgramKerjaTest extends TestCase
{
    use RefreshDatabase;

    private TahunKerja $tahunKerja;

    private UnitKerja $unitA;

    private UnitKerja $unitB;

    protected function setUp(): void
    {
        parent::setUp();

        $periode = Periode::create(['name' => 'P1', 'start_datetime' => Carbon::create(2026, 1, 1), 'end_datetime' => Carbon::create(2026, 12, 31)]);

        $this->tahunKerja = TahunKerja::create([
            'periode_id' => $periode->id,
            'name' => 'TA 2026',
            'start_datetime' => Carbon::create(2026, 1, 1),
            'end_datetime' => Carbon::create(2026, 12, 31),
            'status' => EnumStatusTahunKerja::Berjalan,
        ]);

        $this->unitA = UnitKerja::create(['name' => 'Unit A']);
        $this->unitB = UnitKerja::create(['name' => 'Unit B']);

        $user = User::factory()->create(['unit_kerja_id' => $this->unitA->id]);
        $user->givePermissionTo(Permission::findOrCreate('bypass_data_scope', 'web'));

        $this->actingAs($user);
    }

    public function test_hanya_anggaran_yang_sudah_cair_yang_dihitung_terserap(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $pengajuan = $this->seedPengajuan($this->unitA, 12_000_000, EnumStatusPengajuan::Diterima);

        $this->seedRealisasi($pengajuan, 5_000_000, EnumStatusRealisasi::MenungguLaporan, [
            'nominal_disetujui' => 5_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
        ]);
        // Sudah disetujui tetapi belum cair: menjadi komitmen, belum penyerapan.
        $this->seedRealisasi($pengajuan, 4_000_000, EnumStatusRealisasi::VerifikasiKeuangan, [
            'nominal_disetujui' => 4_000_000,
        ]);
        // Draf tidak pernah menyentuh anggaran.
        $this->seedRealisasi($pengajuan, 3_000_000, EnumStatusRealisasi::Draft);

        $ringkasan = MonitoringAnggaran::untukUnit($this->unitA->id, $this->tahunKerja)->ringkasan();

        $this->assertSame(20_000_000.0, $ringkasan->pagu);
        $this->assertSame(5_000_000.0, $ringkasan->terserap());
        $this->assertSame(4_000_000.0, $ringkasan->komitmen);
        $this->assertSame(15_000_000.0, $ringkasan->sisaPagu());
        $this->assertSame(25.0, $ringkasan->persentasePenyerapan());
    }

    public function test_penyelesaian_selisih_anggaran_mengoreksi_penyerapan(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $pengajuan = $this->seedPengajuan($this->unitA, 10_000_000, EnumStatusPengajuan::Diterima);

        $this->seedRealisasi($pengajuan, 8_000_000, EnumStatusRealisasi::Selesai, [
            'nominal_disetujui' => 10_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
            'status_anggaran' => EnumStatusAnggaran::Sisa,
            'nominal_selisih_anggaran' => 2_000_000,
            'status_penyelesaian_anggaran' => EnumStatusPenyelesaianAnggaran::Dikembalikan,
            'penyelesaian_anggaran_at' => Carbon::create(2026, 4, 1),
        ]);

        $ringkasan = MonitoringAnggaran::untukUnit($this->unitA->id, $this->tahunKerja)->ringkasan();

        // Cair 10jt, 2jt dikembalikan, sehingga yang benar-benar terserap 8jt.
        $this->assertSame(8_000_000.0, $ringkasan->terserap());
        $this->assertSame(12_000_000.0, $ringkasan->sisaPagu());
    }

    public function test_selisih_yang_belum_dituntaskan_belum_mengoreksi_penyerapan(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $pengajuan = $this->seedPengajuan($this->unitA, 10_000_000, EnumStatusPengajuan::Diterima);

        $this->seedRealisasi($pengajuan, 8_000_000, EnumStatusRealisasi::VerifikasiLaporan, [
            'nominal_disetujui' => 10_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
            'status_anggaran' => EnumStatusAnggaran::Sisa,
            'nominal_selisih_anggaran' => 2_000_000,
            'status_penyelesaian_anggaran' => EnumStatusPenyelesaianAnggaran::Menunggu,
        ]);

        $ringkasan = MonitoringAnggaran::untukUnit($this->unitA->id, $this->tahunKerja)->ringkasan();

        $this->assertSame(10_000_000.0, $ringkasan->terserap());
    }

    public function test_penyerapan_sama_dengan_pagu_dikurangi_saldo_buku_anggaran(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $pengajuan = $this->seedPengajuan($this->unitA, 12_000_000, EnumStatusPengajuan::Diterima);

        $this->seedRealisasi($pengajuan, 9_000_000, EnumStatusRealisasi::Selesai, [
            'nominal_disetujui' => 12_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
            'status_anggaran' => EnumStatusAnggaran::Sisa,
            'nominal_selisih_anggaran' => 3_000_000,
            'status_penyelesaian_anggaran' => EnumStatusPenyelesaianAnggaran::Dikembalikan,
            'penyelesaian_anggaran_at' => Carbon::create(2026, 5, 1),
        ]);

        $buku = BukuAnggaran::untukUnit($this->unitA->id, $this->tahunKerja)->ringkasan();
        $ringkasan = MonitoringAnggaran::untukUnit($this->unitA->id, $this->tahunKerja)->ringkasan();

        // Monitoring menjumlahkan lewat SQL, Buku Anggaran merakit baris demi baris —
        // keduanya wajib bertemu di angka yang sama.
        $this->assertSame($buku['pagu'] - $buku['saldo'], $ringkasan->terserap());
        $this->assertSame($buku['saldo'], $ringkasan->sisaPagu());
    }

    public function test_capaian_diambil_dari_realisasi_terakhir_yang_selesai(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $pengajuan = $this->seedPengajuan($this->unitA, 10_000_000, EnumStatusPengajuan::Diterima);

        $this->seedRealisasi($pengajuan, 4_000_000, EnumStatusRealisasi::Selesai, [
            'dicairkan_at' => Carbon::create(2026, 2, 1),
            'persentase_ketercapaian' => 40,
            'created_at' => Carbon::create(2026, 2, 1),
        ]);
        $this->seedRealisasi($pengajuan, 4_000_000, EnumStatusRealisasi::Selesai, [
            'dicairkan_at' => Carbon::create(2026, 6, 1),
            'persentase_ketercapaian' => 90,
            'created_at' => Carbon::create(2026, 6, 1),
        ]);
        // Belum selesai, capaiannya belum diakui.
        $this->seedRealisasi($pengajuan, 2_000_000, EnumStatusRealisasi::MenungguLaporan, [
            'persentase_ketercapaian' => 10,
        ]);

        $ringkasan = MonitoringAnggaran::untukUnit($this->unitA->id, $this->tahunKerja)->ringkasan();

        $this->assertSame(90.0, $ringkasan->capaian);
        $this->assertSame(2, $ringkasan->jumlahSelesai);
    }

    public function test_rekap_per_unit_kerja_memisahkan_angka_tiap_unit(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $this->seedPagu($this->unitB, 10_000_000);

        $pengajuanA = $this->seedPengajuan($this->unitA, 8_000_000, EnumStatusPengajuan::Diterima);
        $pengajuanB = $this->seedPengajuan($this->unitB, 9_000_000, EnumStatusPengajuan::Diterima);

        $this->seedRealisasi($pengajuanA, 5_000_000, EnumStatusRealisasi::Selesai, [
            'nominal_disetujui' => 5_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
            'persentase_ketercapaian' => 100,
        ]);
        $this->seedRealisasi($pengajuanB, 9_000_000, EnumStatusRealisasi::Selesai, [
            'nominal_disetujui' => 9_000_000,
            'dicairkan_at' => Carbon::create(2026, 4, 1),
            'persentase_ketercapaian' => 60,
        ]);

        $perUnit = MonitoringAnggaran::untukUnits([$this->unitA->id, $this->unitB->id], $this->tahunKerja)
            ->perUnitKerja()
            ->keyBy(fn (RingkasanMonitoring $ringkasan): int => $ringkasan->unitKerjaId);

        $this->assertSame(25.0, $perUnit[$this->unitA->id]->persentasePenyerapan());
        $this->assertSame(90.0, $perUnit[$this->unitB->id]->persentasePenyerapan());
        $this->assertSame(100.0, $perUnit[$this->unitA->id]->capaian);
        $this->assertSame(60.0, $perUnit[$this->unitB->id]->capaian);

        // Gabungannya menjumlahkan nominal, dan merata-rata capaian dengan bobot
        // jumlah realisasi selesai — di sini satu banding satu.
        $gabungan = MonitoringAnggaran::untukUnits([$this->unitA->id, $this->unitB->id], $this->tahunKerja)->ringkasan();

        $this->assertSame(30_000_000.0, $gabungan->pagu);
        $this->assertSame(14_000_000.0, $gabungan->terserap());
        $this->assertSame(80.0, $gabungan->capaian);
    }

    public function test_penyerapan_per_bulan_terkumpul_pada_bulan_pencairannya(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $pengajuan = $this->seedPengajuan($this->unitA, 12_000_000, EnumStatusPengajuan::Diterima);

        $this->seedRealisasi($pengajuan, 4_000_000, EnumStatusRealisasi::MenungguLaporan, [
            'nominal_disetujui' => 4_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 15),
        ]);
        $this->seedRealisasi($pengajuan, 6_000_000, EnumStatusRealisasi::MenungguLaporan, [
            'nominal_disetujui' => 6_000_000,
            'dicairkan_at' => Carbon::create(2026, 5, 2),
        ]);

        $perBulan = MonitoringAnggaran::untukUnit($this->unitA->id, $this->tahunKerja)->penyerapanPerBulan();

        // Sumbunya membentang penuh sepanjang tahun kerja.
        $this->assertCount(12, $perBulan);
        $this->assertSame(4_000_000.0, $perBulan->values()->get(2)['terserap']);
        $this->assertSame(6_000_000.0, $perBulan->values()->get(4)['terserap']);
        // Akumulasinya tidak turun lagi sampai akhir tahun kerja.
        $this->assertSame(10_000_000.0, $perBulan->last()['kumulatif']);
    }

    public function test_tahun_kerja_lain_tidak_ikut_terhitung(): void
    {
        $tahunLalu = $this->seedTahunKerjaLain();

        $this->seedPagu($this->unitA, 20_000_000);
        $this->seedPagu($this->unitA, 5_000_000, $tahunLalu);

        $pengajuanLalu = $this->seedPengajuan($this->unitA, 5_000_000, EnumStatusPengajuan::Diterima, $tahunLalu);
        $this->seedRealisasi($pengajuanLalu, 5_000_000, EnumStatusRealisasi::Selesai, [
            'nominal_disetujui' => 5_000_000,
            'dicairkan_at' => Carbon::create(2025, 6, 1),
        ]);

        $berjalan = MonitoringAnggaran::untukUnit($this->unitA->id, $this->tahunKerja)->ringkasan();
        $lalu = MonitoringAnggaran::untukUnit($this->unitA->id, $tahunLalu)->ringkasan();

        $this->assertSame(0.0, $berjalan->terserap());
        $this->assertSame(5_000_000.0, $lalu->terserap());
    }

    public function test_halaman_monitoring_merangkum_seluruh_unit_lalu_menyaring_satu_unit(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $this->seedPagu($this->unitB, 10_000_000);

        Livewire::test(MonitoringProgramKerjaPage::class)
            ->assertOk()
            ->assertSet('tahunKerjaId', $this->tahunKerja->id)
            ->assertSet('unitKerjaId', null)
            // Kartu penyerapan memakai total pagu kedua unit.
            ->assertSee('Rp 30.000.000')
            ->set('data.unitKerjaId', $this->unitB->id)
            ->assertSet('unitKerjaId', $this->unitB->id)
            ->assertSee('Rp 10.000.000')
            ->assertDontSee('Rp 30.000.000');
    }

    public function test_halaman_monitoring_menampilkan_rincian_program_kerja(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $pengajuan = $this->seedPengajuan($this->unitA, 8_000_000, EnumStatusPengajuan::Diterima, nama: 'Workshop Penulisan');

        $this->seedRealisasi($pengajuan, 7_000_000, EnumStatusRealisasi::Selesai, [
            'nominal_disetujui' => 7_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
            'persentase_ketercapaian' => 85,
        ]);

        Livewire::test(MonitoringProgramKerjaPage::class)
            ->assertOk()
            ->assertSee('Workshop Penulisan')
            ->assertSee('Rp 7.000.000')
            ->assertSee('85,0%');
    }

    public function test_tabel_monitoring_dapat_disaring_unit_bidang_dan_program_induk(): void
    {
        $kemahasiswaan = Bidang::create(['code' => 'B2', 'name' => 'Kemahasiswaan']);
        $tataKelola = Program::create(['name' => 'Penguatan Tata Kelola']);

        $this->seedPengajuan($this->unitA, 5_000_000, EnumStatusPengajuan::Diterima, nama: 'Workshop Akademik');
        $this->seedPengajuan($this->unitB, 6_000_000, EnumStatusPengajuan::Diterima, nama: 'Pelatihan Organisasi', bidang: $kemahasiswaan, program: $tataKelola);

        Livewire::test(MonitoringProgramKerjaPage::class)
            ->assertOk()
            ->assertSee('Workshop Akademik')
            ->assertSee('Pelatihan Organisasi')
            ->filterTable('unit_kerja_id', $this->unitB->id)
            ->assertSee('Pelatihan Organisasi')
            ->assertDontSee('Workshop Akademik')
            ->resetTableFilters()
            ->filterTable('bidang_id', $kemahasiswaan->id)
            ->assertSee('Pelatihan Organisasi')
            ->assertDontSee('Workshop Akademik')
            ->resetTableFilters()
            ->filterTable('program_id', $tataKelola->id)
            ->assertSee('Pelatihan Organisasi')
            ->assertDontSee('Workshop Akademik');
    }

    public function test_halaman_monitoring_mengikuti_scope_data_pengguna(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $this->seedPagu($this->unitB, 7_000_000);

        $pengguna = User::factory()->create(['unit_kerja_id' => $this->unitA->id]);
        $pengguna->givePermissionTo(Permission::findOrCreate('view_page_monitoring_program_kerja', 'web'));

        $this->actingAs($pengguna);

        Livewire::test(MonitoringProgramKerjaPage::class)
            ->assertOk()
            ->assertSee('Rp 20.000.000')
            ->assertDontSee('Rp 27.000.000');
    }

    /**
     * Penyaring "Tampilkan Data" berada di paling atas halaman, mendahului widget
     * ringkasan yang angkanya justru ditentukan olehnya.
     */
    public function test_penyaring_tampilkan_data_berada_di_atas_widget(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);

        $html = Livewire::test(MonitoringProgramKerjaPage::class)->assertOk()->html();

        $penyaring = strpos($html, 'Tampilkan Data');
        $widget = strpos($html, 'fi-wi-stats-overview');

        $this->assertIsInt($penyaring);
        $this->assertIsInt($widget);
        $this->assertLessThan($widget, $penyaring);
    }

    public function test_widget_ringkasan_menampilkan_penyerapan_dan_capaian(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $pengajuan = $this->seedPengajuan($this->unitA, 10_000_000, EnumStatusPengajuan::Diterima);

        $this->seedRealisasi($pengajuan, 10_000_000, EnumStatusRealisasi::Selesai, [
            'nominal_disetujui' => 10_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
            'persentase_ketercapaian' => 75,
        ]);

        Livewire::test(MonitoringOverview::class, [
            'unitKerjaId' => $this->unitA->id,
            'tahunKerjaId' => $this->tahunKerja->id,
        ])
            ->assertOk()
            ->assertSee('Rp 20.000.000')
            ->assertSee('Rp 10.000.000')
            ->assertSee('50,0% dari pagu anggaran')
            ->assertSee('75,0%');
    }

    public function test_grafik_penyerapan_menggambar_akumulasi_terhadap_pagu(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $pengajuan = $this->seedPengajuan($this->unitA, 10_000_000, EnumStatusPengajuan::Diterima);

        $this->seedRealisasi($pengajuan, 6_000_000, EnumStatusRealisasi::MenungguLaporan, [
            'nominal_disetujui' => 6_000_000,
            'dicairkan_at' => Carbon::create(2026, 4, 1),
        ]);

        $data = $this->dataGrafik($this->unitA->id, $this->tahunKerja->id);

        $this->assertSame(6_000_000.0, $data['datasets'][0]['data'][3]);
        $this->assertSame(6_000_000.0, $data['datasets'][1]['data'][11]);
        // Garis pagu datar sepanjang tahun sebagai batas penyerapan.
        $this->assertSame(20_000_000.0, $data['datasets'][2]['data'][0]);
    }

    public function test_halaman_ringkasan_merekap_seluruh_unit_kerja(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $this->seedPagu($this->unitB, 10_000_000);

        $pengajuan = $this->seedPengajuan($this->unitB, 9_000_000, EnumStatusPengajuan::Diterima);
        $this->seedRealisasi($pengajuan, 9_000_000, EnumStatusRealisasi::Selesai, [
            'nominal_disetujui' => 9_000_000,
            'dicairkan_at' => Carbon::create(2026, 4, 1),
        ]);

        Livewire::test(RingkasanUnitKerja::class)
            ->assertOk()
            ->assertSet('tahunKerjaId', $this->tahunKerja->id)
            ->assertSee('Unit A')
            ->assertSee('Unit B')
            // Total pagu kedua unit, dengan 9jt terserap seluruhnya dari Unit B.
            ->assertSee('Rp 30.000.000')
            ->assertSee('Rp 9.000.000')
            ->assertSee('90,0%');
    }

    public function test_halaman_perbandingan_menyandingkan_unit_kerja(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $this->seedPagu($this->unitB, 10_000_000);

        $pengajuan = $this->seedPengajuan($this->unitA, 15_000_000, EnumStatusPengajuan::Diterima);
        $this->seedRealisasi($pengajuan, 15_000_000, EnumStatusRealisasi::MenungguLaporan, [
            'nominal_disetujui' => 15_000_000,
            'dicairkan_at' => Carbon::create(2026, 4, 1),
        ]);

        $komponen = Livewire::test(PerbandinganMonitoring::class)
            ->assertOk()
            ->assertSet('mode', PerbandinganMonitoring::MODE_UNIT)
            ->assertSee('Unit A')
            ->assertSee('Unit B')
            // Baris total menjumlahkan pagu kedua unit.
            ->assertSee('Rp 30.000.000');

        $kolom = $komponen->instance()->kolom();

        $this->assertSame(['Unit A', 'Unit B'], array_column($kolom, 'label'));
        $this->assertSame(75.0, $kolom[0]['persentase_penyerapan']);
        // Unit B berpagu tetapi belum mencairkan apa pun.
        $this->assertSame(0.0, $kolom[1]['persentase_penyerapan']);
        // Unit A menyerap paling banyak, jadi kolomnya yang ditandai unggul.
        $this->assertSame(0, $komponen->instance()->kolomTerbaik('persentase_penyerapan'));
    }

    public function test_halaman_perbandingan_dapat_beralih_ke_antar_tahun_kerja(): void
    {
        $tahunLalu = $this->seedTahunKerjaLain();

        $this->seedPagu($this->unitA, 20_000_000);
        $this->seedPagu($this->unitA, 8_000_000, $tahunLalu);

        $pengajuanLalu = $this->seedPengajuan($this->unitA, 8_000_000, EnumStatusPengajuan::Diterima, $tahunLalu);
        $this->seedRealisasi($pengajuanLalu, 8_000_000, EnumStatusRealisasi::Selesai, [
            'nominal_disetujui' => 8_000_000,
            'dicairkan_at' => Carbon::create(2025, 6, 1),
        ]);

        $komponen = Livewire::test(PerbandinganMonitoring::class)
            ->set('data.mode', PerbandinganMonitoring::MODE_TAHUN)
            ->assertSet('mode', PerbandinganMonitoring::MODE_TAHUN)
            ->assertSee('TA 2026')
            ->assertSee('TA 2025');

        $kolom = $komponen->instance()->kolom();

        $this->assertSame(['TA 2026', 'TA 2025'], array_column($kolom, 'label'));
        $this->assertSame(0.0, $kolom[0]['terserap']);
        $this->assertSame(8_000_000.0, $kolom[1]['terserap']);
        $this->assertSame(100.0, $kolom[1]['persentase_penyerapan']);
    }

    public function test_ketiga_halaman_terdaftar_pada_grup_permission_monitoring(): void
    {
        $monitoring = collect(PermissionRegistrar::collect())
            ->filter(fn (array $section): bool => ($section['nav_group'] ?? null) === 'Monitoring')
            ->flatMap(fn (array $section): array => array_keys($section['permissions']))
            ->all();

        $this->assertContains('view_page_monitoring_program_kerja', $monitoring);
        $this->assertContains('view_page_ringkasan_unit_kerja', $monitoring);
        $this->assertContains('view_page_perbandingan_monitoring', $monitoring);
    }

    private function seedPagu(UnitKerja $unit, float $amount, ?TahunKerja $tahunKerja = null): PaguAnggaran
    {
        return PaguAnggaran::create([
            'tahun_kerja_id' => ($tahunKerja ?? $this->tahunKerja)->id,
            'unit_kerja_id' => $unit->id,
            'amount' => $amount,
        ]);
    }

    /**
     * Tahun kerja lain yang sudah lewat, untuk menguji penyaring tahun kerja.
     */
    private function seedTahunKerjaLain(): TahunKerja
    {
        return TahunKerja::create([
            'periode_id' => $this->tahunKerja->periode_id,
            'name' => 'TA 2025',
            'start_datetime' => Carbon::create(2025, 1, 1),
            'end_datetime' => Carbon::create(2025, 12, 31),
            'status' => EnumStatusTahunKerja::Selesai,
        ]);
    }

    private function seedPengajuan(
        UnitKerja $unit,
        float $alokasi,
        EnumStatusPengajuan $status,
        ?TahunKerja $tahunKerja = null,
        ?string $nama = null,
        ?Bidang $bidang = null,
        ?Program $program = null,
    ): PengajuanProgramKerja {
        $penawaran = PenawaranProgramKerja::create([
            'tahun_kerja_id' => ($tahunKerja ?? $this->tahunKerja)->id,
            'unit_kerja_id' => $unit->id,
            'bidang_id' => ($bidang ?? Bidang::firstOrCreate(['code' => 'B1'], ['name' => 'Akademik']))->id,
            'kategori_id' => Kategori::firstOrCreate(['code' => 'K1'], ['name' => 'Pendidikan'])->id,
            'program_id' => ($program ?? Program::firstOrCreate(['name' => 'Tridharma']))->id,
            'name' => $nama ?? 'Workshop '.$unit->name.' '.fake()->unique()->word(),
            'is_active' => true,
        ]);

        return PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $unit->id,
            'alokasi_anggaran' => $alokasi,
            'status' => $status,
        ]);
    }

    /**
     * @param  array<string, mixed>  $atribut
     */
    private function seedRealisasi(
        PengajuanProgramKerja $pengajuan,
        float $digunakan,
        EnumStatusRealisasi $status,
        array $atribut = [],
    ): RealisasiProgramKerja {
        $dicatatPada = $atribut['created_at'] ?? null;
        unset($atribut['created_at']);

        $realisasi = RealisasiProgramKerja::create([
            'pengajuan_program_kerja_id' => $pengajuan->id,
            'name' => 'Pelaksanaan',
            'anggaran_digunakan' => $digunakan,
            'status' => $status,
            ...$atribut,
        ]);

        // created_at bukan kolom fillable, jadi waktu pencatatannya ditulis terpisah
        // ketika sebuah pengujian memang perlu mengatur urutan realisasi.
        if ($dicatatPada !== null) {
            $realisasi->forceFill(['created_at' => $dicatatPada])->saveQuietly();
        }

        return $realisasi;
    }

    /**
     * Data grafik sebuah widget chart, dibaca langsung dari instansinya karena
     * `getData()` memang tertutup untuk pemakaian di luar widget.
     *
     * @return array<string, mixed>
     */
    private function dataGrafik(?int $unitKerjaId, ?int $tahunKerjaId): array
    {
        $widget = new PenyerapanAnggaranChart;
        $widget->unitKerjaId = $unitKerjaId;
        $widget->tahunKerjaId = $tahunKerjaId;

        /** @var array<string, mixed> $data */
        $data = (new \ReflectionMethod($widget, 'getData'))->invoke($widget);

        return $data;
    }
}
