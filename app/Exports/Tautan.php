<?php

namespace App\Exports;

use App\Services\Excel\SpreadsheetExporter;
use Stringable;

/**
 * Nilai sel ekspor yang berupa tautan: teks yang dibaca pengguna beserta alamat
 * tujuannya. Pada berkas .xlsx ditulis sebagai rumus `HYPERLINK` sehingga selnya
 * benar-benar bisa diklik ({@see SpreadsheetExporter::cellValue()}); pada laporan
 * PDF menjadi anchor.
 *
 * Alamatnya selalu alamat di dalam aplikasi, bukan URL berkas langsung: berkas
 * privat hanya bisa dibuka lewat URL sementara yang umurnya pendek, sehingga
 * tautan di dalam berkas ekspor harus berupa kode yang ditukar dengan URL baru
 * setiap kali diikuti.
 */
final readonly class Tautan implements Stringable
{
    public function __construct(
        public string $label,
        public string $url,
    ) {}

    public function __toString(): string
    {
        return $this->label;
    }
}
