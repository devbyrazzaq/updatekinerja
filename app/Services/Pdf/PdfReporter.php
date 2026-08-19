<?php

namespace App\Services\Pdf;

use App\Reports\Report;
use App\Services\Pdf\Engines\BrowsershotEngine;
use App\Services\Pdf\Engines\MpdfEngine;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Merender sebuah {@see Report} menjadi berkas PDF lalu mengembalikannya sebagai
 * respons unduhan yang otomatis dihapus setelah dikirim. Mesin rendernya dipilih
 * lewat `config('pdf.engine')` ({@see PdfEngine}).
 *
 * Sengaja menulis ke berkas temp dulu dan mengembalikan {@see BinaryFileResponse}
 * (bukan respons biasa berisi biner) agar Livewire mengenalinya sebagai unduhan —
 * kalau tidak, biner PDF ikut di-JSON-encode dan memicu error "Malformed UTF-8
 * characters".
 */
class PdfReporter
{
    /**
     * @var array<string, class-string<PdfEngine>>
     */
    protected const ENGINES = [
        'mpdf' => MpdfEngine::class,
        'browsershot' => BrowsershotEngine::class,
    ];

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
        $this->engine()->render($report, $path);
    }

    public function engine(): PdfEngine
    {
        $engine = (string) config('pdf.engine');

        if (! array_key_exists($engine, self::ENGINES)) {
            throw new InvalidArgumentException(
                'Mesin PDF ['.$engine.'] tidak dikenal. Pilihannya: '.implode(', ', array_keys(self::ENGINES)).'.',
            );
        }

        return app(self::ENGINES[$engine]);
    }
}
