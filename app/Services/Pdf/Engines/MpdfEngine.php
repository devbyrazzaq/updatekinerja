<?php

namespace App\Services\Pdf\Engines;

use App\Reports\Report;
use App\Services\Pdf\PdfEngine;
use Illuminate\Support\Facades\File;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Merender laporan sepenuhnya di dalam PHP memakai mPDF — tanpa Chromium, tanpa
 * Node, tanpa binary tambahan — sehingga laporan tetap bisa diunduh di hosting
 * yang tidak mengizinkan pemasangan peramban.
 *
 * Harganya: mPDF hanya mengerti sebagian CSS 2.1. View laporan karenanya ditulis
 * dengan tata letak tabel, warna heksa tertulis penuh (bukan `var()`), dan tanpa
 * flexbox — lihat `resources/views/reports/layout.blade.php`.
 */
class MpdfEngine implements PdfEngine
{
    public function render(Report $report, string $path): void
    {
        $mpdf = $this->mpdf($report);

        $mpdf->SetHTMLFooter($this->footer($report));

        $mpdf->WriteHTML(
            view($report->view(), [...$report->data(), 'engine' => 'mpdf'])->render(),
            HTMLParserMode::DEFAULT_MODE,
        );

        $mpdf->Output($path, Destination::FILE);
    }

    /**
     * Catatan kaki diulang di setiap halaman oleh mPDF sendiri. `{PAGENO}` dan
     * `{nbpg}` adalah penanda bawaannya yang ditukar nomor halaman saat dicetak.
     */
    protected function footer(Report $report): string
    {
        return view('reports.footer', [
            'engine' => 'mpdf',
            'note' => $report->footerNote(),
            'pageNumber' => '{PAGENO}',
            'totalPages' => '{nbpg}',
        ])->render();
    }

    protected function mpdf(Report $report): Mpdf
    {
        $tempDir = config('pdf.mpdf.temp_dir');

        File::ensureDirectoryExists($tempDir);

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $report->landscape() ? 'A4-L' : 'A4',
            'margin_top' => 12,
            'margin_right' => 12,
            'margin_bottom' => 18,
            'margin_left' => 12,
            'margin_footer' => 6,
            'tempDir' => $tempDir,
            'default_font' => config('pdf.mpdf.font_family'),
            'default_font_size' => 9.5,
            ...$this->fonts(),
        ]);

        // Tabel berkolom banyak dikecilkan otomatis agar tetap muat selebar kertas,
        // meniru perilaku Chromium yang tidak pernah memotong kolom di tepi halaman.
        $mpdf->shrink_tables_to_fit = 1;

        // Berkas hanya dibaca, bukan diedit lebih lanjut, jadi metadatanya cukup
        // menerangkan asal dokumen.
        $mpdf->SetTitle($report->filename());
        $mpdf->SetCreator(config('app.name'));

        return $mpdf;
    }

    /**
     * Font bawaan mPDF tetap didaftarkan sebagai cadangan: aksara yang tidak ada
     * pada subset Plus Jakarta Sans masih tergambar, bukan jadi kotak kosong.
     *
     * @return array{fontDir: list<string>, fontdata: array<string, array<string, mixed>>}
     */
    protected function fonts(): array
    {
        $fontDir = (new ConfigVariables)->getDefaults()['fontDir'];
        $fontData = (new FontVariables)->getDefaults()['fontdata'];

        return [
            'fontDir' => [...$fontDir, config('pdf.mpdf.font_dir')],
            'fontdata' => [
                ...$fontData,
                config('pdf.mpdf.font_family') => config('pdf.mpdf.font_data'),
            ],
        ];
    }
}
