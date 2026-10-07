<?php

namespace Tests\Feature;

use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Widgets\AksiCepatWidget;
use App\Filament\Widgets\MenungguKeputusanWidget;
use App\Filament\Widgets\PenyerapanBulananWidget;
use App\Filament\Widgets\PeriodeBerjalanWidget;
use App\Filament\Widgets\PerluTindakLanjutWidget;
use App\Filament\Widgets\PintasanMenuWidget;
use App\Filament\Widgets\RingkasanAnggaranWidget;
use App\Filament\Widgets\RingkasanProgramKerjaWidget;
use App\Filament\Widgets\SisaWaktuTahunKerjaWidget;
use App\Filament\Widgets\StatusRealisasiWidget;
use App\Filament\Widgets\TentangSistemWidget;
use App\Models\Bidang;
use App\Models\Kategori;
use App\Models\PaguAnggaran;
use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use App\Models\Periode;
use App\Models\Program;
use App\Models\RealisasiProgramKerja;
use App\Models\Setting;
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
 * Widget dashboard: kartu tentang sistem, ringkasan anggaran tahun berjalan, dua daftar
 * tugas yang menunggu, pintasan menu, dan grafik penyerapan serta status realisasi.
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
            ->assertSeeLivewire(TentangSistemWidget::class)
            ->assertSeeLivewire(PeriodeBerjalanWidget::class)
            ->assertSeeLivewire(SisaWaktuTahunKerjaWidget::class)
            ->assertSeeLivewire(RingkasanAnggaranWidget::class)
            ->assertSeeLivewire(RingkasanProgramKerjaWidget::class)
            ->assertSeeLivewire(AksiCepatWidget::class)
            ->assertSeeLivewire(MenungguKeputusanWidget::class)
            ->assertSeeLivewire(PerluTindakLanjutWidget::class)
            ->assertSeeLivewire(PintasanMenuWidget::class)
            ->assertSeeLivewire(PenyerapanBulananWidget::class)
            ->assertSeeLivewire(StatusRealisasiWidget::class);
    }

    public function test_tentang_sistem_membaca_identitas_aplikasi(): void
    {
        Livewire::test(TentangSistemWidget::class)
            ->assertOk()
            ->assertSee('SIM KINERJA')
            ->assertSee('Universitas Muhammadiyah Lamongan');

        Setting::set(Setting::BRAND_NAMA, 'SIM ANGGARAN');
        Setting::set(Setting::BRAND_INSTANSI, 'Universitas Contoh');

        Livewire::test(TentangSistemWidget::class)
            ->assertOk()
            ->assertSee('SIM ANGGARAN')
            ->assertSee('Universitas Contoh');
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

        $baris = $this->barisTugas(new MenungguKeputusanWidget);

        $this->assertSame(1, $baris['Verifikasi Rektor']['jumlah'] ?? null);
        $this->assertStringContainsString(
            'Realisasi program kerja menunggu verifikasi Rektor',
            $baris['Verifikasi Rektor']['keterangan'],
        );

        Livewire::test(MenungguKeputusanWidget::class)
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

        $baris = $this->barisTugas(new PerluTindakLanjutWidget);

        $this->assertSame(1, $baris['Pengajuan Perlu Revisi']['jumlah'] ?? null);
        $this->assertSame(1, $baris['Realisasi Perlu Revisi']['jumlah'] ?? null);
        $this->assertSame(1, $baris['Laporan Realisasi Belum Dikirim']['jumlah'] ?? null);

        Livewire::test(PerluTindakLanjutWidget::class)
            ->assertOk()
            ->assertSee('Perlu Ditindaklanjuti')
            ->assertSee('Pengajuan Perlu Revisi');
    }

    public function test_antrean_tugas_menyembunyikan_menu_yang_tidak_boleh_dibuka(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 10_000_000, EnumStatusPengajuan::Diterima);
        $this->seedRealisasi($pengajuan, 4_000_000, EnumStatusRealisasi::Diajukan);

        // Permission-nya ada tetapi tidak diberikan, sehingga menu benar-benar tertutup.
        Permission::findOrCreate('view_any_verifikasi_rektor', 'web');

        $this->actingAs(User::factory()->create(['unit_kerja_id' => $this->unitA->id]));

        $this->assertArrayNotHasKey('Verifikasi Rektor', $this->barisTugas(new MenungguKeputusanWidget));
    }

    public function test_antrean_tugas_kosong_ketika_tidak_ada_yang_menunggu(): void
    {
        Livewire::test(MenungguKeputusanWidget::class)
            ->assertOk()
            ->assertSee('Tidak ada berkas yang menunggu keputusan');

        Livewire::test(PerluTindakLanjutWidget::class)
            ->assertOk()
            ->assertSee('Tidak ada berkas yang perlu ditindaklanjuti');
    }

    public function test_periode_berjalan_membaca_periode_tahun_kerja_berjalan(): void
    {
        Livewire::test(PeriodeBerjalanWidget::class)
            ->assertOk()
            ->assertSee('Periode Berjalan')
            ->assertSee('P1')
            ->assertSee('TA 2026')
            ->assertSee('01 Januari 2026 — 31 Desember 2026');
    }

    public function test_periode_berjalan_kosong_tanpa_tahun_kerja_berjalan(): void
    {
        $this->tahunKerja->update(['status' => EnumStatusTahunKerja::Selesai]);

        Livewire::test(PeriodeBerjalanWidget::class)
            ->assertOk()
            ->assertSee('Belum ada periode berjalan');
    }

    public function test_sisa_waktu_menghitung_mundur_akhir_tahun_kerja(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 12, 1, 8));

        $widget = new SisaWaktuTahunKerjaWidget;

        $this->assertSame(30, $widget->sisaHari());
        $this->assertSame('warning', $widget->warna());

        Livewire::test(SisaWaktuTahunKerjaWidget::class)
            ->assertOk()
            ->assertSee('30 hari lagi')
            ->assertSee('31 Desember 2026');

        Carbon::setTestNow();
    }

    public function test_sisa_waktu_menandai_tahun_kerja_yang_sudah_terlampaui(): void
    {
        Carbon::setTestNow(Carbon::create(2027, 1, 15, 8));

        $widget = new SisaWaktuTahunKerjaWidget;

        $this->assertLessThan(0, $widget->sisaHari());
        $this->assertSame('danger', $widget->warna());
        $this->assertSame('Sudah terlampaui', $widget->sisaWaktu());

        Carbon::setTestNow();
    }

    public function test_ringkasan_program_kerja_menghitung_berkas_tahun_berjalan(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 10_000_000, EnumStatusPengajuan::Diterima);

        $this->seedRealisasi($pengajuan, 4_000_000, EnumStatusRealisasi::Diajukan);
        $this->seedRealisasi($pengajuan, 3_000_000, EnumStatusRealisasi::Selesai, [
            'nominal_disetujui' => 3_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
        ]);

        Livewire::test(RingkasanProgramKerjaWidget::class)
            ->assertOk()
            ->assertSee('Total Program Kerja')
            ->assertSee('Total Pengajuan')
            ->assertSee('Total Realisasi')
            ->assertSee('Realisasi Selesai')
            ->assertSee('1 realisasi masih berjalan')
            ->assertSee('50,0% dari seluruh realisasi');
    }

    public function test_ringkasan_program_kerja_mengabaikan_unit_di_luar_cakupan(): void
    {
        $this->seedPengajuan($this->unitA, 10_000_000, EnumStatusPengajuan::Diterima);
        $this->seedPengajuan($this->unitB, 10_000_000, EnumStatusPengajuan::Diterima);

        $this->actingAs(User::factory()->create(['unit_kerja_id' => $this->unitA->id]));

        $widget = new RingkasanProgramKerjaWidget;
        $jumlahPengajuan = new ReflectionMethod($widget, 'jumlahPengajuan');

        $this->assertSame(1, $jumlahPengajuan->invoke($widget, null));
    }

    public function test_aksi_cepat_menawarkan_pintasan_pembuatan_berkas(): void
    {
        $kartu = collect((new AksiCepatWidget)->aksiCepat());

        $this->assertSame([
            'Buat Pengajuan Program Kerja',
            'Buat Realisasi Program Kerja',
            'Catat Pemasukan Unit',
            'Catat Capaian Program',
        ], $kartu->pluck('label')->all());

        // Kartunya membuka modal aksi, bukan berpindah halaman.
        $this->assertSame("mountAction('buatPengajuan')", $kartu->firstWhere('label', 'Buat Pengajuan Program Kerja')['pemicu']);

        Livewire::test(AksiCepatWidget::class)
            ->assertOk()
            ->assertSee('Aksi Cepat')
            ->assertSee('Buat Realisasi Program Kerja');
    }

    /**
     * Modal tiap aksi cepat memakai form Resource yang utuh, jadi yang diuji di sini
     * modalnya benar-benar dapat dirakit — kesalahan skema akan terlihat sejak dipasang.
     */
    public function test_setiap_aksi_cepat_dapat_membuka_modalnya(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $this->seedPengajuan($this->unitA, 10_000_000, EnumStatusPengajuan::Diterima);

        foreach (['buatPengajuan', 'buatRealisasi', 'catatPemasukan', 'catatCapaian'] as $aksi) {
            Livewire::test(AksiCepatWidget::class)
                ->call('mountAction', $aksi)
                ->assertOk()
                ->assertHasNoErrors();
        }
    }

    public function test_aksi_cepat_menyembunyikan_aksi_yang_tidak_boleh_dibuat(): void
    {
        Permission::findOrCreate('create_realisasi_program_kerja', 'web');
        Permission::findOrCreate('update_capaian_monitoring_program_kerja', 'web');

        $this->actingAs(User::factory()->create(['unit_kerja_id' => $this->unitA->id]));

        $label = collect((new AksiCepatWidget)->aksiCepat())->pluck('label')->all();

        $this->assertNotContains('Buat Realisasi Program Kerja', $label);
        $this->assertNotContains('Catat Capaian Program', $label);
    }

    public function test_warna_status_realisasi_tidak_ada_yang_kembar(): void
    {
        $warna = collect(EnumStatusRealisasi::cases())
            ->map(fn (EnumStatusRealisasi $status): string => $status->getColor());

        $this->assertCount($warna->count(), $warna->unique());
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
        $this->assertContains('view_widget_menunggu_keputusan', $dashboard);
        $this->assertContains('view_widget_perlu_tindak_lanjut', $dashboard);
        $this->assertContains('view_widget_tentang_sistem', $dashboard);
        $this->assertContains('view_widget_periode_berjalan', $dashboard);
        $this->assertContains('view_widget_sisa_waktu_tahun_kerja', $dashboard);
        $this->assertContains('view_widget_ringkasan_program_kerja', $dashboard);
        $this->assertContains('view_widget_aksi_cepat', $dashboard);
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
     * Baris tugas sebuah widget, dikunci menurut labelnya agar mudah diperiksa.
     *
     * @param  MenungguKeputusanWidget|PerluTindakLanjutWidget  $widget
     * @return array<string, array<string, mixed>>
     */
    private function barisTugas(object $widget): array
    {
        return collect($widget->barisTugas())->keyBy('label')->all();
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
