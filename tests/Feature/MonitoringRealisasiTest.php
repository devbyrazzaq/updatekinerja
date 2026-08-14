<?php

namespace Tests\Feature;

use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Pages\DetailMonitoringRealisasi;
use App\Filament\Pages\MonitoringRealisasi as MonitoringRealisasiPage;
use App\Filament\Pages\Widgets\AnggaranRealisasiChart;
use App\Filament\Pages\Widgets\MonitoringRealisasiOverview;
use App\Filament\Pages\Widgets\RealisasiUnitKerjaChart;
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
use App\Services\MonitoringRealisasi;
use App\Services\PermissionRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Monitoring realisasi: jumlah kegiatan yang berjalan maupun tuntas, aliran
 * anggarannya, dan penelusuran rinciannya sampai ke tahun kerja yang sudah lewat.
 */
class MonitoringRealisasiTest extends TestCase
{
    use RefreshDatabase;

    private TahunKerja $tahunKerja;

    private UnitKerja $unitA;

    private UnitKerja $unitB;

    protected function setUp(): void
    {
        parent::setUp();

        $periode = Periode::create([
            'name' => 'P1',
            'start_datetime' => Carbon::create(2026, 1, 1),
            'end_datetime' => Carbon::create(2026, 12, 31),
        ]);

        $this->tahunKerja = TahunKerja::create([
            'periode_id' => $periode->id,
            'name' => 'TA 2026',
            'start_datetime' => Carbon::create(2026, 1, 1),
            'end_datetime' => Carbon::create(2026, 12, 31),
            'status' => EnumStatusTahunKerja::Berjalan,
        ]);

        $this->unitA = UnitKerja::create(['name' => 'Unit A']);
        $this->unitB = UnitKerja::create(['name' => 'Unit B']);

        $this->actingAs($this->penggunaPenuh());
    }

    public function test_ringkasan_memisahkan_realisasi_berjalan_dan_selesai(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 20_000_000);

        $this->seedRealisasi($pengajuan, 5_000_000, EnumStatusRealisasi::MenungguLaporan, [
            'nominal_disetujui' => 5_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
        ]);
        $this->seedRealisasi($pengajuan, 4_000_000, EnumStatusRealisasi::VerifikasiKeuangan, [
            'nominal_disetujui' => 4_000_000,
        ]);
        $this->seedRealisasi($pengajuan, 3_000_000, EnumStatusRealisasi::Selesai, [
            'nominal_disetujui' => 3_500_000,
            'dicairkan_at' => Carbon::create(2026, 5, 1),
            'laporan_diserahkan_at' => Carbon::create(2026, 6, 1),
            'persentase_ketercapaian' => 80,
        ]);

        $ringkasan = MonitoringRealisasi::untukUnits([$this->unitA->id], $this->tahunKerja->id)->ringkasan();

        $this->assertSame(3, $ringkasan->jumlah);
        $this->assertSame(2, $ringkasan->berjalan);
        $this->assertSame(1, $ringkasan->selesai);
        $this->assertSame(2, $ringkasan->sudahCair);
        $this->assertSame(12_500_000.0, $ringkasan->disetujui);
        $this->assertSame(8_500_000.0, $ringkasan->dicairkan);
        // Hanya realisasi yang laporannya sudah masuk yang dianggap dipertanggungjawabkan.
        $this->assertSame(3_000_000.0, $ringkasan->dilaporkan);
        $this->assertSame(4_000_000.0, $ringkasan->menungguCair());
        $this->assertSame(80.0, $ringkasan->capaian);
    }

    public function test_draf_tidak_pernah_ikut_dipantau(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 10_000_000);

        $draf = $this->seedRealisasi($pengajuan, 6_000_000, EnumStatusRealisasi::Draft);
        $this->seedRealisasi($pengajuan, 4_000_000, EnumStatusRealisasi::Diajukan);

        $monitoring = MonitoringRealisasi::untukUnits([$this->unitA->id], $this->tahunKerja->id);

        $this->assertSame(1, $monitoring->ringkasan()->jumlah);
        $this->assertFalse($monitoring->queryTabel()->whereKey($draf->getKey())->exists());
    }

    public function test_realisasi_yang_kandas_terhitung_tetapi_tidak_menyumbang_anggaran(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 10_000_000);

        $this->seedRealisasi($pengajuan, 4_000_000, EnumStatusRealisasi::Ditolak, [
            'nominal_disetujui' => 4_000_000,
        ]);
        $this->seedRealisasi($pengajuan, 3_000_000, EnumStatusRealisasi::Dibatalkan);
        $this->seedRealisasi($pengajuan, 2_000_000, EnumStatusRealisasi::Selesai, [
            'nominal_disetujui' => 2_000_000,
            'dicairkan_at' => Carbon::create(2026, 4, 1),
        ]);

        $ringkasan = MonitoringRealisasi::untukUnits([$this->unitA->id], $this->tahunKerja->id)->ringkasan();

        $this->assertSame(3, $ringkasan->jumlah);
        $this->assertSame(2, $ringkasan->batal);
        $this->assertSame(2_000_000.0, $ringkasan->disetujui);
        // Pembagi persentase selesai mengabaikan yang kandas: 1 dari 1 pekerjaan tuntas.
        $this->assertSame(100.0, $ringkasan->persentaseSelesai());
    }

    public function test_tanpa_penyaring_tahun_kerja_realisasi_tahun_lalu_ikut_terbaca(): void
    {
        $tahunLalu = $this->seedTahunKerjaLalu();

        $pengajuanLalu = $this->seedPengajuan($this->unitA, 8_000_000, $tahunLalu, 'Seminar Lama');
        $this->seedRealisasi($pengajuanLalu, 8_000_000, EnumStatusRealisasi::Selesai, [
            'nominal_disetujui' => 8_000_000,
            'dicairkan_at' => Carbon::create(2025, 6, 1),
        ]);

        $pengajuanKini = $this->seedPengajuan($this->unitA, 5_000_000);
        $this->seedRealisasi($pengajuanKini, 5_000_000, EnumStatusRealisasi::MenungguLaporan, [
            'nominal_disetujui' => 5_000_000,
            'dicairkan_at' => Carbon::create(2026, 4, 1),
        ]);

        $berjalan = MonitoringRealisasi::untukUnits([$this->unitA->id], $this->tahunKerja->id)->ringkasan();
        $seluruhnya = MonitoringRealisasi::untukUnits([$this->unitA->id])->ringkasan();

        $this->assertSame(5_000_000.0, $berjalan->dicairkan);
        $this->assertSame(13_000_000.0, $seluruhnya->dicairkan);
        $this->assertSame(2, $seluruhnya->jumlah);
    }

    public function test_sebaran_status_menghitung_tiap_tahap(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 10_000_000);

        $this->seedRealisasi($pengajuan, 1_000_000, EnumStatusRealisasi::VerifikasiRektor);
        $this->seedRealisasi($pengajuan, 1_000_000, EnumStatusRealisasi::VerifikasiRektor);
        $this->seedRealisasi($pengajuan, 1_000_000, EnumStatusRealisasi::Selesai);

        $sebaran = MonitoringRealisasi::untukUnits([$this->unitA->id], $this->tahunKerja->id)->sebaranStatus();

        $this->assertSame(2, $sebaran['Verifikasi Rektor']);
        $this->assertSame(1, $sebaran['Selesai']);
        $this->assertArrayNotHasKey('Draf', $sebaran->all());
    }

    /**
     * Satu tahun kerja terpilih menggambar sumbu bulan sepanjang tahun tersebut;
     * tanpa tahun kerja terpilih sumbunya berganti menjadi antar tahun kerja.
     */
    public function test_grafik_anggaran_berganti_sumbu_mengikuti_cakupan(): void
    {
        $tahunLalu = $this->seedTahunKerjaLalu();

        $pengajuanLalu = $this->seedPengajuan($this->unitA, 8_000_000, $tahunLalu);
        $this->seedRealisasi($pengajuanLalu, 8_000_000, EnumStatusRealisasi::Selesai, [
            'nominal_disetujui' => 8_000_000,
            'dicairkan_at' => Carbon::create(2025, 6, 1),
            'laporan_diserahkan_at' => Carbon::create(2025, 7, 1),
        ]);

        $pengajuanKini = $this->seedPengajuan($this->unitA, 6_000_000);
        $this->seedRealisasi($pengajuanKini, 6_000_000, EnumStatusRealisasi::MenungguLaporan, [
            'nominal_disetujui' => 6_000_000,
            'dicairkan_at' => Carbon::create(2026, 4, 1),
        ]);

        $perBulan = MonitoringRealisasi::untukUnits([$this->unitA->id], $this->tahunKerja->id)->anggaranPerPeriode();

        $this->assertCount(12, $perBulan);
        $this->assertSame(6_000_000.0, $perBulan->values()->get(3)['dicairkan']);
        $this->assertSame(0.0, $perBulan->values()->get(3)['dilaporkan']);

        $perTahun = MonitoringRealisasi::untukUnits([$this->unitA->id])->anggaranPerPeriode();

        $this->assertSame(['TA 2025', 'TA 2026'], $perTahun->keys()->all());
        $this->assertSame(8_000_000.0, $perTahun['TA 2025']['dicairkan']);
        $this->assertSame(8_000_000.0, $perTahun['TA 2025']['dilaporkan']);
        $this->assertSame(6_000_000.0, $perTahun['TA 2026']['dicairkan']);
    }

    public function test_halaman_menampilkan_realisasi_lalu_menyaring_unit_kerja(): void
    {
        $pengajuanA = $this->seedPengajuan($this->unitA, 5_000_000, nama: 'Workshop Akademik');
        $this->seedRealisasi($pengajuanA, 5_000_000, EnumStatusRealisasi::MenungguLaporan, [
            'name' => 'Pelaksanaan Workshop Akademik',
            'nominal_disetujui' => 5_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
        ]);

        $pengajuanB = $this->seedPengajuan($this->unitB, 4_000_000, nama: 'Pelatihan Organisasi');
        $this->seedRealisasi($pengajuanB, 4_000_000, EnumStatusRealisasi::Selesai, [
            'name' => 'Pelaksanaan Pelatihan Organisasi',
            'nominal_disetujui' => 4_000_000,
            'dicairkan_at' => Carbon::create(2026, 4, 1),
        ]);

        Livewire::test(MonitoringRealisasiPage::class)
            ->assertOk()
            ->assertSet('tahunKerjaId', $this->tahunKerja->id)
            ->assertSet('unitKerjaId', null)
            ->assertSee('Pelaksanaan Workshop Akademik')
            ->assertSee('Pelaksanaan Pelatihan Organisasi')
            ->assertSee('Rp 9.000.000')
            ->set('data.unitKerjaId', $this->unitB->id)
            ->assertSet('unitKerjaId', $this->unitB->id)
            ->assertSee('Pelaksanaan Pelatihan Organisasi')
            ->assertDontSee('Pelaksanaan Workshop Akademik');
    }

    /**
     * Menu Pelaksanaan hanya berbicara tentang tahun kerja berjalan; halaman ini
     * sengaja tidak, sehingga realisasi tahun lalu muncul begitu penyaring tahun
     * kerjanya dikosongkan.
     */
    public function test_halaman_dapat_menelusuri_tahun_kerja_sebelumnya(): void
    {
        $tahunLalu = $this->seedTahunKerjaLalu();

        $pengajuanLalu = $this->seedPengajuan($this->unitA, 8_000_000, $tahunLalu);
        $this->seedRealisasi($pengajuanLalu, 8_000_000, EnumStatusRealisasi::Selesai, [
            'name' => 'Pelaksanaan Kegiatan Tahun Lalu',
            'nominal_disetujui' => 8_000_000,
            'dicairkan_at' => Carbon::create(2025, 6, 1),
        ]);

        Livewire::test(MonitoringRealisasiPage::class)
            ->assertOk()
            ->assertDontSee('Pelaksanaan Kegiatan Tahun Lalu')
            ->set('data.tahunKerjaId', null)
            ->assertSet('tahunKerjaId', null)
            ->assertSee('Pelaksanaan Kegiatan Tahun Lalu');
    }

    public function test_halaman_mengikuti_scope_data_pengguna(): void
    {
        $pengajuanA = $this->seedPengajuan($this->unitA, 5_000_000);
        $this->seedRealisasi($pengajuanA, 5_000_000, EnumStatusRealisasi::MenungguLaporan, [
            'name' => 'Kegiatan Unit A',
            'nominal_disetujui' => 5_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
        ]);

        $pengajuanB = $this->seedPengajuan($this->unitB, 4_000_000);
        $this->seedRealisasi($pengajuanB, 4_000_000, EnumStatusRealisasi::MenungguLaporan, [
            'name' => 'Kegiatan Unit B',
            'nominal_disetujui' => 4_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
        ]);

        $this->actingAs($this->penggunaUnit($this->unitA));

        Livewire::test(MonitoringRealisasiPage::class)
            ->assertOk()
            ->assertSee('Kegiatan Unit A')
            ->assertDontSee('Kegiatan Unit B')
            ->assertSee('Unit A')
            ->assertDontSee('Unit B');
    }

    public function test_detail_realisasi_menampilkan_rincian_dan_dokumennya(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 5_000_000, nama: 'Workshop Penulisan');
        $realisasi = $this->seedRealisasi($pengajuan, 5_000_000, EnumStatusRealisasi::MenungguLaporan, [
            'name' => 'Pelaksanaan Workshop Penulisan',
            'nominal_disetujui' => 5_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
        ]);

        $realisasi->update([
            'proposal_path' => ['proposal-realisasi/proposal-workshop.pdf'],
            'proposal_original_names' => ['proposal-realisasi/proposal-workshop.pdf' => 'Proposal Workshop.pdf'],
        ]);

        Livewire::test(DetailMonitoringRealisasi::class, ['record' => $realisasi->uuid])
            ->assertOk()
            ->assertSee('Pelaksanaan Workshop Penulisan')
            ->assertSee('Workshop Penulisan')
            ->assertSee('Unit A')
            ->assertSee('Dokumen Proposal')
            ->assertSee('Rp 5.000.000');
    }

    public function test_detail_realisasi_tertutup_bagi_unit_di_luar_scope(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitB, 4_000_000);
        $realisasi = $this->seedRealisasi($pengajuan, 4_000_000, EnumStatusRealisasi::MenungguLaporan);

        $this->actingAs($this->penggunaUnit($this->unitA));

        $this->get(DetailMonitoringRealisasi::getUrl(['record' => $realisasi->uuid]))
            ->assertForbidden();
    }

    public function test_detail_realisasi_terbuka_lewat_alamatnya_sendiri(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 5_000_000);
        $realisasi = $this->seedRealisasi($pengajuan, 5_000_000, EnumStatusRealisasi::MenungguLaporan, [
            'name' => 'Pelaksanaan Kegiatan Unit A',
        ]);

        $this->actingAs($this->penggunaUnit($this->unitA));

        $this->get(DetailMonitoringRealisasi::getUrl(['record' => $realisasi->uuid]))
            ->assertOk()
            ->assertSee('Pelaksanaan Kegiatan Unit A');
    }

    public function test_detail_realisasi_menumpang_hak_akses_halaman_monitoring(): void
    {
        Permission::findOrCreate('view_page_monitoring_realisasi', 'web');

        $this->actingAs(User::factory()->create(['unit_kerja_id' => $this->unitA->id]));

        $this->assertFalse(MonitoringRealisasiPage::canAccess());
        $this->assertFalse(DetailMonitoringRealisasi::canAccess());
        $this->assertSame([], DetailMonitoringRealisasi::getPermissionDefinitions());

        $this->actingAs($this->penggunaUnit($this->unitA));

        $this->assertTrue(MonitoringRealisasiPage::canAccess());
        $this->assertTrue(DetailMonitoringRealisasi::canAccess());
    }

    public function test_widget_ringkasan_menampilkan_jumlah_dan_anggaran(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 10_000_000);

        $this->seedRealisasi($pengajuan, 6_000_000, EnumStatusRealisasi::Selesai, [
            'nominal_disetujui' => 6_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
            'laporan_diserahkan_at' => Carbon::create(2026, 4, 1),
        ]);
        $this->seedRealisasi($pengajuan, 2_000_000, EnumStatusRealisasi::MenungguLaporan, [
            'nominal_disetujui' => 2_000_000,
            'dicairkan_at' => Carbon::create(2026, 5, 1),
        ]);

        Livewire::test(MonitoringRealisasiOverview::class, [
            'unitKerjaId' => $this->unitA->id,
            'tahunKerjaId' => $this->tahunKerja->id,
        ])
            ->assertOk()
            ->assertSee('Realisasi Berjalan')
            ->assertSee('Rp 8.000.000')
            ->assertSee('Rp 6.000.000')
            ->assertSee('50,0% realisasi tuntas');
    }

    public function test_grafik_unit_kerja_memisahkan_berjalan_dan_selesai(): void
    {
        $pengajuanA = $this->seedPengajuan($this->unitA, 5_000_000);
        $this->seedRealisasi($pengajuanA, 5_000_000, EnumStatusRealisasi::MenungguLaporan);

        $pengajuanB = $this->seedPengajuan($this->unitB, 4_000_000);
        $this->seedRealisasi($pengajuanB, 2_000_000, EnumStatusRealisasi::Selesai);
        $this->seedRealisasi($pengajuanB, 2_000_000, EnumStatusRealisasi::Selesai);

        $data = $this->dataGrafik(new RealisasiUnitKerjaChart, null, $this->tahunKerja->id);

        // Unit dengan realisasi terbanyak digambar lebih dahulu.
        $this->assertSame(['Unit B', 'Unit A'], $data['labels']);
        $this->assertSame([0, 1], $data['datasets'][0]['data']);
        $this->assertSame([2, 0], $data['datasets'][1]['data']);
    }

    public function test_grafik_anggaran_menyandingkan_pencairan_dan_laporan(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 10_000_000);

        $this->seedRealisasi($pengajuan, 7_000_000, EnumStatusRealisasi::Selesai, [
            'nominal_disetujui' => 8_000_000,
            'dicairkan_at' => Carbon::create(2026, 4, 1),
            'laporan_diserahkan_at' => Carbon::create(2026, 5, 1),
        ]);

        $data = $this->dataGrafik(new AnggaranRealisasiChart, $this->unitA->id, $this->tahunKerja->id);

        $this->assertSame(8_000_000.0, $data['datasets'][0]['data'][3]);
        $this->assertSame(7_000_000.0, $data['datasets'][1]['data'][3]);
    }

    public function test_halaman_terdaftar_pada_grup_permission_monitoring(): void
    {
        $monitoring = collect(PermissionRegistrar::collect())
            ->filter(fn (array $section): bool => ($section['nav_group'] ?? null) === 'Monitoring')
            ->flatMap(fn (array $section): array => array_keys($section['permissions']))
            ->all();

        $this->assertContains('view_page_monitoring_realisasi', $monitoring);
    }

    private function penggunaPenuh(): User
    {
        $user = User::factory()->create(['unit_kerja_id' => $this->unitA->id]);
        $user->givePermissionTo(Permission::findOrCreate('bypass_data_scope', 'web'));

        return $user;
    }

    /**
     * Pengguna yang hanya boleh memantau satu unit kerja, tanpa `bypass_data_scope`.
     */
    private function penggunaUnit(UnitKerja $unitKerja): User
    {
        $user = User::factory()->create(['unit_kerja_id' => $unitKerja->id]);
        $user->givePermissionTo(Permission::findOrCreate('view_page_monitoring_realisasi', 'web'));

        return $user;
    }

    private function seedTahunKerjaLalu(): TahunKerja
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
        ?TahunKerja $tahunKerja = null,
        ?string $nama = null,
    ): PengajuanProgramKerja {
        $penawaran = PenawaranProgramKerja::create([
            'tahun_kerja_id' => ($tahunKerja ?? $this->tahunKerja)->id,
            'unit_kerja_id' => $unit->id,
            'bidang_id' => Bidang::firstOrCreate(['code' => 'B1'], ['name' => 'Akademik'])->id,
            'kategori_id' => Kategori::firstOrCreate(['code' => 'K1'], ['name' => 'Pendidikan'])->id,
            'program_id' => Program::firstOrCreate(['name' => 'Tridharma'])->id,
            'name' => $nama ?? 'Program '.$unit->name.' '.fake()->unique()->word(),
            'is_active' => true,
        ]);

        return PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $unit->id,
            'alokasi_anggaran' => $alokasi,
            'status' => EnumStatusPengajuan::Diterima,
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
        return RealisasiProgramKerja::create([
            'pengajuan_program_kerja_id' => $pengajuan->id,
            'name' => 'Pelaksanaan',
            'anggaran_digunakan' => $digunakan,
            'status' => $status,
            ...$atribut,
        ]);
    }

    /**
     * Data grafik sebuah widget chart, dibaca langsung dari instansinya karena
     * `getData()` memang tertutup untuk pemakaian di luar widget.
     *
     * @return array<string, mixed>
     */
    private function dataGrafik(object $widget, ?int $unitKerjaId, ?int $tahunKerjaId): array
    {
        $widget->unitKerjaId = $unitKerjaId;
        $widget->tahunKerjaId = $tahunKerjaId;

        /** @var array<string, mixed> $data */
        $data = (new \ReflectionMethod($widget, 'getData'))->invoke($widget);

        return $data;
    }
}
