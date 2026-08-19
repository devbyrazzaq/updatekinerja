<?php

namespace App\Services\Pdf;

use App\Reports\Report;

/**
 * Mesin yang mengubah sebuah {@see Report} menjadi berkas PDF di path tertentu.
 * Implementasinya boleh berupa pustaka PHP murni maupun peramban headless; view
 * laporannya tetap sama, jadi keluarannya harus tetap terbaca sebagai dokumen yang
 * sama sekalipun mesinnya diganti.
 */
interface PdfEngine
{
    public function render(Report $report, string $path): void;
}
