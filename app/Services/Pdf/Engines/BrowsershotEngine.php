<?php

namespace App\Services\Pdf\Engines;

use App\Reports\Report;
use App\Services\Pdf\PdfEngine;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * Merender laporan lewat headless Chromium (spatie/laravel-pdf + Browsershot).
 * Hasilnya paling setia pada CSS modern, tetapi menuntut binary Chrome/Chromium
 * di mesin yang menjalankannya — karena itu bukan mesin bawaan, melainkan pilihan
 * untuk lingkungan yang memang menyediakannya (mis. container Docker proyek ini).
 */
class BrowsershotEngine implements PdfEngine
{
    public function render(Report $report, string $path): void
    {
        $chromePath = config('pdf.chrome_path');

        Pdf::view($report->view(), [...$report->data(), 'engine' => 'browsershot'])
            ->format('a4')
            ->when($report->landscape(), fn (PdfBuilder $pdf): PdfBuilder => $pdf->landscape())
            // Ruang bawah disisakan lebih lega untuk catatan kaki bernomor halaman.
            ->margins(top: 12, right: 12, bottom: 18, left: 12)
            ->footerView('reports.footer', [
                'engine' => 'browsershot',
                'note' => $report->footerNote(),
                // Chromium mengisi sendiri isi elemen berkelas ini saat mencetak.
                'pageNumber' => '<span class="pageNumber"></span>',
                'totalPages' => '<span class="totalPages"></span>',
            ])
            ->when(
                filled($chromePath),
                fn (PdfBuilder $pdf): PdfBuilder => $pdf->withBrowsershot(
                    fn (Browsershot $browsershot) => $browsershot
                        ->setChromePath($chromePath)
                        ->noSandbox(),
                ),
            )
            ->save($path);
    }
}
