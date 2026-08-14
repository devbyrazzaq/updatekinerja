<?php

namespace App\Services\Pdf;

use App\Reports\Report;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Merender sebuah {@see Report} menjadi berkas PDF memakai spatie/laravel-pdf
 * (Browsershot / headless Chromium), lalu mengembalikannya sebagai respons unduhan
 * yang otomatis dihapus setelah dikirim.
 *
 * Sengaja menulis ke berkas temp dulu dan mengembalikan {@see BinaryFileResponse}
 * (bukan `->toResponse()` yang berupa Response biasa berisi biner) agar Livewire
 * mengenalinya sebagai unduhan — kalau tidak, biner PDF ikut di-JSON-encode dan
 * memicu error "Malformed UTF-8 characters".
 */
class PdfReporter
{
    public function download(Report $report): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'report_').'.pdf';

        $this->render($report, $path);

        return response()
            ->download($path, $report->filename().'.pdf', [
                'Content-Type' => 'application/pdf',
            ])
            ->deleteFileAfterSend();
    }

    /**
     * Render laporan ke path tertentu. Dipisah dari {@see download()} supaya bisa
     * dipakai langsung tanpa merakit respons HTTP.
     */
    public function render(Report $report, string $path): void
    {
        $chromePath = config('pdf.chrome_path');

        Pdf::view($report->view(), $report->data())
            ->format('a4')
            ->when($report->landscape(), fn (PdfBuilder $pdf): PdfBuilder => $pdf->landscape())
            // Ruang bawah disisakan lebih lega untuk catatan kaki bernomor halaman.
            ->margins(top: 12, right: 12, bottom: 18, left: 12)
            ->footerView('reports.footer', ['note' => $report->footerNote()])
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
