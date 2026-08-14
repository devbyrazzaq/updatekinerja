<?php

namespace Tests\Feature;

use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Widgets\AntreanTugasWidget;
use App\Filament\Widgets\PenyerapanBulananWidget;
use App\Filament\Widgets\PintasanMenuWidget;
use App\Filament\Widgets\RingkasanAnggaranWidget;
use App\Filament\Widgets\StatusRealisasiWidget;
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
use App\Services\PermissionRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Widget dashboard: ringkasan anggaran tahun berjalan, daftar tugas yang menunggu,
 * pintasan menu, dan grafik penyerapan serta status realisasi.
 *
 * Cakupan seluruh widget mengikuti tahun kerja berjalan dan unit kerja yang boleh
 * diakses pengguna — tanpa penyaring, karena dashboard tidak punya form.
 */
class DashboardWidgetTest extends TestCase
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

    /**
     * Widget dashboard dimuat malas (lazy), jadi yang diperiksa di sini pemasangannya
     * pada halaman — isinya diuji lewat pengujian komponen masing-masing.
     */
    public function test_dashboard_memasang_seluruh_widget(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);

        $this->get('/app')
            ->assertOk()
            ->assertSeeLivewire(RingkasanAnggaranWidget::class)
            ->assertSeeLivewire(AntreanTugasWidget::class)
            ->assertSeeLivewire(PintasanMenuWidget::class)
            ->assertSeeLivewire(PenyerapanBulananWidget::class)
            ->assertSeeLivewire(StatusRealisasiWidget::class);
    }

    public function test_ringkasan_anggaran_membaca_tahun_kerja_berjalan(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $pengajuan = $this->seedPengajuan($this->unitA, 10_000_000, EnumStatusPengajuan::Diterima);

        $this->seedRealisasi($pengajuan, 5_000_000, EnumStatusRealisasi::MenungguLaporan, [
            'nominal_disetujui' => 5_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
        ]);

        Livewire::test(RingkasanAnggaranWidget::class)
            ->assertOk()
            ->assertSee('Ringkasan Anggaran TA 2026')
            ->assertSee('Rp 20.000.000')
            ->assertSee('Rp 5.000.000')
            ->assertSee('25,0% dari pagu anggaran');
    }

    public function test_ringkasan_anggaran_hanya_menghitung_unit_yang_boleh_diakses(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $this->seedPagu($this->unitB, 10_000_000);

        $this->actingAs(User::factory()->create(['unit_kerja_id' => $this->unitA->id]));

        Livewire::test(RingkasanAnggaranWidget::class)
            ->assertOk()
            ->assertSee('Rp 20.000.000')
            ->assertDontSee('Rp 30.000.000');
    }

    public function test_ringkasan_anggaran_tetap_terbuka_tanpa_tahun_kerja_berjalan(): void
    {
        $this->tahunKerja->update(['status' => EnumStatusTahunKerja::Selesai]);

        Livewire::test(RingkasanAnggaranWidget::class)
            ->assertOk()
            ->assertSee('Belum ada tahun kerja yang dijalankan');
    }

    public function test_antrean_tugas_menampilkan_berkas_yang_menunggu_verifikasi(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 10_000_000, EnumStatusPengajuan::Diterima);
        $this->seedRealisasi($pengajuan, 4_000_000, EnumStatusRealisasi::Diajukan);

        $baris = $this->barisTugas('Menunggu Keputusan Anda');

        $this->assertSame(1, $baris['Verifikasi Rektor']['jumlah'] ?? null);
        $this->assertStringContainsString(
            'Realisasi program kerja menunggu verifikasi Rektor',
            $baris['Verifikasi Rektor']['keterangan'],
        );

        Livewire::test(AntreanTugasWidget::class)
            ->assertOk()
            ->assertSee('Menunggu Keputusan Anda')
            ->assertSee('Verifikasi Rektor');
    }

    public function test_antrean_tugas_menampilkan_berkas_yang_perlu_ditindaklanjuti(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 10_000_000, EnumStatusPengajuan::Revisi);
        $this->seedRealisasi($pengajuan, 4_000_000, EnumStatusRealisasi::Revisi);
        $this->seedRealisasi($pengajuan, 3_000_000, EnumStatusRealisasi::MenungguLaporan, [
            'nominal_disetujui' => 3_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
        ]);

        $baris = $this->barisTugas('Perlu Ditindaklanjuti');

        $this->assertSame(1, $baris['Pengajuan Perlu Revisi']['jumlah'] ?? null);
        $this->assertSame(1, $baris['Realisasi Perlu Revisi']['jumlah'] ?? null);
        $this->assertSame(1, $baris['Laporan Realisasi Belum Dikirim']['jumlah'] ?? null);
    }

    public function test_antrean_tugas_menyembunyikan_menu_yang_tidak_boleh_dibuka(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 10_000_000, EnumStatusPengajuan::Diterima);
        $this->seedRealisasi($pengajuan, 4_000_000, EnumStatusRealisasi::Diajukan);

        // Permission-nya ada tetapi tidak diberikan, sehingga menu benar-benar tertutup.
        Permission::findOrCreate('view_any_verifikasi_rektor', 'web');

        $this->actingAs(User::factory()->create(['unit_kerja_id' => $this->unitA->id]));

        $this->assertArrayNotHasKey('Verifikasi Rektor', $this->barisTugas('Menunggu Keputusan Anda'));
    }

    public function test_antrean_tugas_kosong_ketika_tidak_ada_yang_menunggu(): void
    {
        Livewire::test(AntreanTugasWidget::class)
            ->assertOk()
            ->assertSee('Tidak ada tugas yang menunggu');
    }

    public function test_pintasan_menu_mengikuti_hak_akses_pengguna(): void
    {
        $penuh = collect((new PintasanMenuWidget)->pintasan())->pluck('label')->all();

        $this->assertContains('Realisasi Program Kerja', $penuh);
        $this->assertContains('Monitoring Program Kerja', $penuh);

        Livewire::test(PintasanMenuWidget::class)
            ->assertOk()
            ->assertSee('Pintasan Menu')
            ->assertSee('Realisasi Program Kerja');

        // Permission-nya ada tetapi tidak diberikan, sehingga pintasannya ikut hilang.
        Permission::findOrCreate('view_any_realisasi_program_kerja', 'web');
        Permission::findOrCreate('view_page_monitoring_program_kerja', 'web');

        $this->actingAs(User::factory()->create(['unit_kerja_id' => $this->unitA->id]));

        $terbatas = collect((new PintasanMenuWidget)->pintasan())->pluck('label')->all();

        $this->assertNotContains('Realisasi Program Kerja', $terbatas);
        $this->assertNotContains('Monitoring Program Kerja', $terbatas);
    }

    public function test_status_realisasi_memecah_realisasi_menurut_tahapannya(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 20_000_000, EnumStatusPengajuan::Diterima);

        $this->seedRealisasi($pengajuan, 4_000_000, EnumStatusRealisasi::Diajukan);
        $this->seedRealisasi($pengajuan, 3_000_000, EnumStatusRealisasi::Selesai, [
            'nominal_disetujui' => 3_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
        ]);
        $this->seedRealisasi($pengajuan, 2_000_000, EnumStatusRealisasi::Selesai, [
            'nominal_disetujui' => 2_000_000,
            'dicairkan_at' => Carbon::create(2026, 4, 1),
        ]);

        $data = $this->dataGrafik(new StatusRealisasiWidget);

        $this->assertSame(['Diajukan', 'Selesai'], $data['labels']);
        $this->assertSame([1, 2], $data['datasets'][0]['data']);
    }

    public function test_status_realisasi_mengabaikan_realisasi_unit_lain(): void
    {
        $pengajuanA = $this->seedPengajuan($this->unitA, 10_000_000, EnumStatusPengajuan::Diterima);
        $pengajuanB = $this->seedPengajuan($this->unitB, 10_000_000, EnumStatusPengajuan::Diterima);

        $this->seedRealisasi($pengajuanA, 4_000_000, EnumStatusRealisasi::Diajukan);
        $this->seedRealisasi($pengajuanB, 5_000_000, EnumStatusRealisasi::Diajukan);

        $this->actingAs(User::factory()->create(['unit_kerja_id' => $this->unitA->id]));

        $data = $this->dataGrafik(new StatusRealisasiWidget);

        $this->assertSame([1], $data['datasets'][0]['data']);
    }

    public function test_permission_widget_dashboard_terdaftar_pada_grup_dashboard(): void
    {
        $dashboard = collect(PermissionRegistrar::collect())
            ->filter(fn (array $section): bool => ($section['nav_group'] ?? null) === 'Dashboard')
            ->flatMap(fn (array $section): array => array_keys($section['permissions']))
            ->all();

        $this->assertContains('view_widget_ringkasan_anggaran', $dashboard);
        $this->assertContains('view_widget_antrean_tugas', $dashboard);
        $this->assertContains('view_widget_pintasan_menu', $dashboard);
        $this->assertContains('view_widget_penyerapan_bulanan', $dashboard);
        $this->assertContains('view_widget_status_realisasi', $dashboard);
    }

    public function test_widget_dashboard_tertutup_tanpa_permission(): void
    {
        Permission::findOrCreate('view_widget_ringkasan_anggaran', 'web');

        $pengguna = User::factory()->create(['unit_kerja_id' => $this->unitA->id]);

        $this->actingAs($pengguna);
        $this->assertFalse(RingkasanAnggaranWidget::canView());

        $pengguna->givePermissionTo('view_widget_ringkasan_anggaran');

        $this->actingAs($pengguna->fresh());
        $this->assertTrue(RingkasanAnggaranWidget::canView());
    }

    /**
     * Pengguna yang boleh membaca seluruh unit kerja dan membuka semua menu.
     */
    private function penggunaPenuh(): User
    {
        $user = User::factory()->create(['unit_kerja_id' => $this->unitA->id]);
        $user->givePermissionTo(Permission::findOrCreate('bypass_data_scope', 'web'));

        return $user;
    }

    /**
     * Baris satu kelompok tugas, dikunci menurut labelnya agar mudah diperiksa.
     *
     * @return array<string, array<string, mixed>>
     */
    private function barisTugas(string $judul): array
    {
        $kelompok = collect((new AntreanTugasWidget)->kelompokTugas())->firstWhere('judul', $judul);

        return collect($kelompok['baris'] ?? [])->keyBy('label')->all();
    }

    /**
     * Data grafik sebuah widget chart, dibaca langsung dari instansinya karena
     * `getData()` memang tertutup untuk pemakaian di luar widget.
     *
     * @return array<string, mixed>
     */
    private function dataGrafik(object $widget): array
    {
        /** @var array<string, mixed> $data */
        $data = (new ReflectionMethod($widget, 'getData'))->invoke($widget);

        return $data;
    }

    private function seedPagu(UnitKerja $unit, float $amount): PaguAnggaran
    {
        return PaguAnggaran::create([
            'tahun_kerja_id' => $this->tahunKerja->id,
            'unit_kerja_id' => $unit->id,
            'amount' => $amount,
        ]);
    }

    private function seedPengajuan(UnitKerja $unit, float $alokasi, EnumStatusPengajuan $status): PengajuanProgramKerja
    {
        $penawaran = PenawaranProgramKerja::create([
            'tahun_kerja_id' => $this->tahunKerja->id,
            'unit_kerja_id' => $unit->id,
            'bidang_id' => Bidang::firstOrCreate(['code' => 'B1'], ['name' => 'Akademik'])->id,
            'kategori_id' => Kategori::firstOrCreate(['code' => 'K1'], ['name' => 'Pendidikan'])->id,
            'program_id' => Program::firstOrCreate(['name' => 'Tridharma'])->id,
            'name' => 'Workshop '.$unit->name.' '.fake()->unique()->word(),
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
        return RealisasiProgramKerja::create([
            'pengajuan_program_kerja_id' => $pengajuan->id,
            'name' => 'Pelaksanaan',
            'anggaran_digunakan' => $digunakan,
            'status' => $status,
            ...$atribut,
        ]);
    }
}
