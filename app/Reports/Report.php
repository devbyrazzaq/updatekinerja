<?php

namespace App\Reports;

use App\Services\Pdf\PdfReporter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Kontrak dasar untuk seluruh laporan PDF. Turunan cukup mendefinisikan nama
 * berkas, view Blade, dan data yang dioper ke view; mekanik render HTML → PDF
 * ditangani oleh {@see PdfReporter} (berbasis spatie/laravel-pdf + Browsershot).
 */
abstract class Report
{
    /**
     * Nama berkas tanpa ekstensi, mis. "laporan-produk".
     */
    abstract public function filename(): string;

    /**
     * Nama view Blade yang merender isi laporan, mis. "reports.products".
     */
    abstract public function view(): string;

    /**
     * Data yang dioper ke view.
     *
     * @return array<string, mixed>
     */
    abstract public function data(): array;

    /**
     * Orientasi kertas. Laporan berkolom banyak lebih terbaca dalam bentuk
     * melintang; turunan boleh memutuskannya sendiri, mis. dari jumlah kolom.
     */
    public function landscape(): bool
    {
        return false;
    }

    /**
     * Teks kecil pada catatan kaki tiap halaman, di sisi kiri nomor halaman.
     */
    public function footerNote(): string
    {
        return 'Dokumen dibuat otomatis oleh sistem.';
    }

    public function download(): BinaryFileResponse
    {
        return app(PdfReporter::class)->download($this);
    }
}
