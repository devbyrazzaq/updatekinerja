<?php

namespace Tests\Feature;

use App\Exports\Export;
use App\Exports\ProgramsExport;
use App\Exports\RealisasiProgramKerjasExport;
use App\Filament\Actions\PdfReportAction;
use App\Filament\Resources\Programs\Pages\ListPrograms;
use App\Models\Program;
use App\Models\User;
use App\Reports\TabularReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Menguji perakitan data laporan dan hasil render Blade-nya. Render Chromium
 * sengaja tidak ikut diuji: terlalu berat untuk pengujian dan butuh binary yang
 * hanya ada di environment tertentu.
 */
class LaporanPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_laporan_tabular_merakit_kolom_ringkasan_dan_baris(): void
    {
        Program::create(['name' => 'Tri Dharma', 'description' => 'Program induk utama', 'is_active' => true]);
        Program::create(['name' => 'Pengabdian', 'is_active' => false]);

        $data = (new TabularReport(new ProgramsExport))->data();

        $this->assertSame('Data Program Induk', $data['title']);
        $this->assertSame(
            ['Nama Program Induk', 'Deskripsi', 'Status'],
            array_column($data['columns'], 'label'),
        );
        $this->assertSame('2', $data['summary']['Total Program Induk']);
        $this->assertSame('1', $data['summary']['Aktif']);
        $this->assertCount(2, $data['rows']);
    }

    public function test_nilai_baris_diformat_sesuai_tipe_kolom(): void
    {
        Program::create(['name' => 'Tri Dharma', 'is_active' => false]);

        $rows = (new TabularReport(new ProgramsExport))->data()['rows'];

        // name (teks), description (kosong), is_active (boolean → lencana).
        $this->assertSame('Tri Dharma', $rows[0][0]['text']);
        $this->assertSame('—', $rows[0][1]['text']);
        $this->assertSame('Nonaktif', $rows[0][2]['text']);
        $this->assertTrue($rows[0][2]['badge']);
        $this->assertSame('center', $rows[0][2]['align']);
    }

    public function test_view_laporan_merender_judul_label_dan_data(): void
    {
        Program::create(['name' => 'Tri Dharma', 'is_active' => true]);

        $report = new TabularReport(new ProgramsExport);
        $html = View::make($report->view(), $report->data())->render();

        $this->assertStringContainsString('Data Program Induk', $html);
        $this->assertStringContainsString('Nama Program Induk', $html);
        $this->assertStringContainsString('Tri Dharma', $html);
        $this->assertStringContainsString('Total 1 baris data.', $html);
        // Font Plus Jakarta Sans tertanam, bukan ditautkan ke jaringan.
        $this->assertStringContainsString('data:font/woff2', $html);
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
    }

    public function test_laporan_menyisipkan_pita_pembatas_saat_ekspor_mengelompokkan(): void
    {
        $report = new TabularReport(new class extends Export
        {
            public function filename(): string
            {
                return 'uji-pengelompokan';
            }

            public function title(): string
            {
                return 'Uji Pengelompokan';
            }

            public function headings(): array
            {
                return ['nama', 'dicairkan_at'];
            }

            public function rows(): iterable
            {
                return [
                    ['Kegiatan A', '2026-03-04'],
                    ['Kegiatan B', '2026-03-20'],
                    ['Kegiatan C', '2026-02-11'],
                    ['Kegiatan D', null],
                ];
            }

            public function groupLabel(array $row): ?string
            {
                $dicairkan = $this->columnValue($row, 'dicairkan_at');

                return blank($dicairkan)
                    ? 'Belum Dicairkan'
                    : 'Dicairkan '.mb_substr((string) $dicairkan, 0, 7);
            }
        });

        $data = $report->data();

        // Pita dikunci nomor baris data, jadi barisnya sendiri tetap empat.
        $this->assertSame([
            0 => 'Dicairkan 2026-03',
            2 => 'Dicairkan 2026-02',
            3 => 'Belum Dicairkan',
        ], $data['groups']);
        $this->assertCount(4, $data['rows']);

        $html = View::make($report->view(), $data)->render();

        $this->assertSame(3, substr_count($html, 'class="group-band"'));
        $this->assertStringContainsString('Dicairkan 2026-02', $html);
        $this->assertStringContainsString('Belum Dicairkan', $html);
        // Penomoran dan hitungan baris tetap menghitung data saja.
        $this->assertStringContainsString('Total 4 baris data.', $html);
    }

    public function test_laporan_tanpa_data_menampilkan_keadaan_kosong(): void
    {
        $report = new TabularReport(new ProgramsExport);
        $html = View::make($report->view(), $report->data())->render();

        $this->assertStringContainsString('Belum ada data untuk dilaporkan.', $html);
    }

    public function test_laporan_berkolom_banyak_dicetak_melintang(): void
    {
        $this->assertFalse((new TabularReport(new ProgramsExport))->landscape());
        $this->assertTrue((new TabularReport(new RealisasiProgramKerjasExport))->landscape());
    }

    public function test_nama_berkas_laporan_diawali_kata_laporan(): void
    {
        $this->assertSame(
            'laporan-program-'.now()->format('Y-m-d'),
            (new TabularReport(new ProgramsExport))->filename(),
        );
    }

    public function test_tombol_laporan_pdf_tersedia_di_halaman_daftar(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ListPrograms::class)
            ->assertOk()
            ->assertActionExists('report')
            ->assertActionExists('export');
    }

    public function test_aksi_laporan_membungkus_kelas_ekspor_menjadi_laporan_tabular(): void
    {
        $action = PdfReportAction::make()->reporter(ProgramsExport::class);

        $resolveReport = new \ReflectionMethod($action, 'resolveReport');

        $this->assertInstanceOf(TabularReport::class, $resolveReport->invoke($action));
    }
}
