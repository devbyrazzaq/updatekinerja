<?php

namespace Tests\Feature;

use App\Exports\BukuAnggaranExport;
use App\Exports\Export;
use App\Exports\MonitoringProgramKerjasExport;
use App\Exports\MonitoringRealisasisExport;
use App\Exports\PerbandinganMonitoringExport;
use App\Exports\RingkasanUnitKerjasExport;
use App\Filament\Pages\BukuAnggaran;
use App\Filament\Pages\BukuAnggaranKeseluruhan;
use App\Filament\Pages\MonitoringProgramKerja;
use App\Filament\Pages\MonitoringRealisasi;
use App\Filament\Pages\PerbandinganMonitoring;
use App\Filament\Pages\RingkasanUnitKerja;
use App\Models\PaguAnggaran;
use App\Models\Periode;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Models\User;
use App\Reports\TabularReport;
use App\Services\Excel\SpreadsheetExporter;
use Filament\Actions\Action;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Seluruh halaman pada grup menu Monitoring wajib punya tombol Ekspor dan Laporan
 * PDF pada header action-nya, dan tiap kelas ekspornya harus benar-benar menghasilkan
 * berkas — bukan sekadar tombol yang ada.
 */
class EksporMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private TahunKerja $tahunKerja;

    private UnitKerja $unitKerja;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $periode = Periode::create([
            'name' => 'Periode 2025-2029',
            'start_datetime' => now()->subYear(),
            'end_datetime' => now()->addYears(3),
        ]);

        $this->tahunKerja = TahunKerja::create([
            'periode_id' => $periode->id,
            'name' => 'TA 2026',
            'tahun' => 2026,
            'start_datetime' => now()->startOfYear(),
            'end_datetime' => now()->endOfYear(),
        ]);

        $this->unitKerja = UnitKerja::create(['name' => 'Fakultas Teknik']);

        PaguAnggaran::create([
            'tahun_kerja_id' => $this->tahunKerja->id,
            'unit_kerja_id' => $this->unitKerja->id,
            'amount' => 250_000_000,
        ]);
    }

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function halamanMonitoring(): array
    {
        return [
            'Monitoring Program Kerja' => [MonitoringProgramKerja::class],
            'Monitoring Realisasi' => [MonitoringRealisasi::class],
            'Ringkasan Unit Kerja' => [RingkasanUnitKerja::class],
            'Perbandingan Monitoring' => [PerbandinganMonitoring::class],
            'Buku Anggaran Keseluruhan' => [BukuAnggaranKeseluruhan::class],
            'Buku Anggaran Unit' => [BukuAnggaran::class],
        ];
    }

    /**
     * @param  class-string  $halaman
     */
    #[DataProvider('halamanMonitoring')]
    public function test_halaman_punya_tombol_ekspor_dan_laporan_pdf(string $halaman): void
    {
        Livewire::test($halaman)
            ->assertOk()
            ->assertActionExists('export')
            ->assertActionExists('report');
    }

    /**
     * @return array<string, array{0: callable(): Export}>
     */
    public static function kelasEkspor(): array
    {
        return [
            'Monitoring Program Kerja' => [fn (self $test): Export => new MonitoringProgramKerjasExport(
                [$test->unitKerja->id], $test->tahunKerja->id, $test->unitKerja->name,
            )],
            'Monitoring Realisasi' => [fn (self $test): Export => new MonitoringRealisasisExport(
                [$test->unitKerja->id], $test->tahunKerja->id, $test->unitKerja->name,
            )],
            'Ringkasan Unit Kerja' => [fn (self $test): Export => new RingkasanUnitKerjasExport(
                [$test->unitKerja->id], $test->tahunKerja->id,
            )],
        ];
    }

    /**
     * @param  callable(self): Export  $buat
     */
    #[DataProvider('kelasEkspor')]
    public function test_ekspor_monitoring_menghasilkan_xlsx_dan_laporan(callable $buat): void
    {
        $export = $buat($this);
        $path = tempnam(sys_get_temp_dir(), 'monitoring_').'.xlsx';

        app(SpreadsheetExporter::class)->write($export, $path);

        $this->assertGreaterThan(0, filesize($path));
        @unlink($path);

        $report = new TabularReport($export);
        $html = View::make($report->view(), $report->data())->render();

        $this->assertStringContainsString($export->title(), $html);
    }

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function halamanBercakupan(): array
    {
        return [
            'Monitoring Program Kerja' => [MonitoringProgramKerja::class],
            'Monitoring Realisasi' => [MonitoringRealisasi::class],
            'Buku Anggaran Keseluruhan' => [BukuAnggaranKeseluruhan::class],
            'Buku Anggaran Unit' => [BukuAnggaran::class],
        ];
    }

    /**
     * @param  class-string  $halaman
     */
    #[DataProvider('halamanBercakupan')]
    public function test_laporan_pdf_menanyakan_cakupan_unit_kerja(string $halaman): void
    {
        Livewire::test($halaman)
            ->assertActionExists('report', fn (Action $action): bool => $action->shouldOpenModal())
            // Ekspor spreadsheet tetap sekali klik: berkasnya salinan tabel di layar.
            ->assertActionExists('export', fn (Action $action): bool => ! $action->shouldOpenModal());
    }

    /**
     * Ringkasan Unit Kerja justru menyandingkan seluruh unit, jadi laporannya tidak
     * pernah dipersempit ke satu unit kerja.
     */
    public function test_laporan_ringkasan_unit_kerja_selalu_memuat_seluruh_unit(): void
    {
        Livewire::test(RingkasanUnitKerja::class)
            ->assertActionExists('report', fn (Action $action): bool => ! $action->shouldOpenModal());

        $export = new RingkasanUnitKerjasExport([$this->unitKerja->id], $this->tahunKerja->id);

        $this->assertNull($export->groupLabel([]));
    }

    /**
     * Pada mode Antar Unit Kerja, unit yang dibandingkan justru menjadi kolom
     * matriksnya — cakupan baru bisa ditanyakan pada mode Antar Tahun Kerja.
     */
    public function test_perbandingan_menanyakan_cakupan_hanya_pada_mode_antar_tahun(): void
    {
        Livewire::test(PerbandinganMonitoring::class)
            ->assertActionExists('report', fn (Action $action): bool => ! $action->shouldOpenModal())
            ->set('mode', PerbandinganMonitoring::MODE_TAHUN)
            ->assertActionExists('report', fn (Action $action): bool => $action->shouldOpenModal());
    }

    public function test_cakupan_laporan_menentukan_unit_kerja_yang_diekspor(): void
    {
        // Cakupan tidak pernah melampaui unit kerja yang boleh dibaca pengguna.
        $this->actingAs(User::factory()->create(['unit_kerja_id' => $this->unitKerja->id]));

        $halaman = new MonitoringRealisasi;
        $export = new \ReflectionMethod($halaman, 'export');

        $this->assertSame($this->unitKerja->name, $export->invoke($halaman, $this->unitKerja->id)->subtitle());
        $this->assertSame('Seluruh Unit Kerja', $export->invoke($halaman, null)->subtitle());
    }

    public function test_buku_anggaran_dikelompokkan_per_bulan_mutasi(): void
    {
        $export = new BukuAnggaranExport(unitKerjaId: $this->unitKerja->id, tahunKerjaId: $this->tahunKerja->id);
        $headings = $export->headings();

        $baris = fn (?string $tanggal): array => array_replace(
            array_fill(0, count($headings), null),
            [(int) array_search('tanggal', $headings, true) => $tanggal],
        );

        $this->assertSame('Mutasi Maret 2026', $export->groupLabel($baris('2026-03-11')));
        $this->assertSame('Mutasi April 2026', $export->groupLabel($baris('2026-04-02')));
        $this->assertNull($export->groupLabel($baris(null)));
    }

    public function test_ekspor_realisasi_dikelompokkan_per_bulan_pencairan(): void
    {
        $export = new MonitoringRealisasisExport;
        $headings = $export->headings();

        $baris = fn (?string $dicairkan): array => array_replace(
            array_fill(0, count($headings), null),
            [(int) array_search('dicairkan_at', $headings, true) => $dicairkan],
        );

        $this->assertSame('Dicairkan Maret 2026', $export->groupLabel($baris('2026-03-11')));
        $this->assertSame('Dicairkan Maret 2026', $export->groupLabel($baris('2026-03-28')));
        $this->assertSame('Dicairkan April 2026', $export->groupLabel($baris('2026-04-01')));
        $this->assertSame('Belum Dicairkan', $export->groupLabel($baris(null)));
    }

    public function test_perbandingan_merakit_kolom_dari_pilihan_pengguna(): void
    {
        $export = new PerbandinganMonitoringExport(
            kolom: [
                ['label' => 'Fakultas Teknik', 'pagu' => 250_000_000.0, 'capaian' => 80.0],
                ['label' => 'Fakultas Ekonomi', 'pagu' => 125_000_000.0, 'capaian' => null],
            ],
            metrik: [
                ['label' => 'Pagu Anggaran', 'kunci' => 'pagu', 'format' => 'rupiah', 'keterangan' => 'Anggaran yang ditetapkan'],
                ['label' => 'Capaian Target', 'kunci' => 'capaian', 'format' => 'persen', 'keterangan' => 'Rata-rata ketercapaian'],
            ],
            cakupan: 'Antar unit kerja pada satu tahun kerja yang sama.',
        );

        // Kolom pembanding menjadi kolom berlabel nama entitasnya.
        $this->assertSame(
            ['metrik', 'keterangan', 'fakultas_teknik', 'fakultas_ekonomi'],
            $export->headings(),
        );
        $this->assertSame('Fakultas Teknik', $export->columnLabels()['fakultas_teknik']);

        $rows = iterator_to_array($export->rows());

        $this->assertSame(['Pagu Anggaran', 'Anggaran yang ditetapkan', 'Rp 250.000.000', 'Rp 125.000.000'], $rows[0]);
        $this->assertSame(['Capaian Target', 'Rata-rata ketercapaian', '80,0%', '—'], $rows[1]);
    }
}
