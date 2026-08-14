<?php

namespace Tests\Feature;

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
