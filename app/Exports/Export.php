<?php

namespace App\Exports;

use App\Enums\EnumFormatKolom;
use App\Reports\TabularReport;
use App\Services\Excel\SpreadsheetExporter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Kontrak dasar untuk seluruh ekspor spreadsheet. Turunan cukup mendefinisikan
 * nama berkas, judul kolom, dan baris data; mekanik penulisan xlsx ditangani
 * oleh {@see SpreadsheetExporter} (berbasis openspout).
 *
 * Di atas data mentah itu ada lapisan presentasi opsional — {@see title()},
 * {@see columnLabels()}, {@see columnFormats()}, {@see summary()} — yang dipakai
 * bersama oleh berkas .xlsx dan laporan PDF ({@see TabularReport}). Semuanya punya
 * nilai bawaan yang ditebak dari nama kolom, jadi ekspor yang tidak meng-override
 * apa pun tetap tampil rapi.
 */
abstract class Export
{
    /**
     * Nama berkas tanpa ekstensi, mis. "produk".
     */
    abstract public function filename(): string;

    /**
     * Judul kolom pada baris kepala tabel. Nilainya adalah key mesin (`unit_kerja`,
     * `is_active`) sehingga berkas hasil ekspor tetap bisa diimpor kembali.
     *
     * @return list<string>
     */
    abstract public function headings(): array;

    /**
     * Baris data. Setiap elemen adalah array nilai kolom yang urutannya
     * sejajar dengan {@see headings()}.
     *
     * @return iterable<array-key, array<int, mixed>>
     */
    abstract public function rows(): iterable;

    /**
     * Judul dokumen pada blok kepala berkas .xlsx dan laporan PDF. Bawaannya
     * dirakit dari nama kelas, mis. `ProgramsExport` → "Data Programs".
     */
    public function title(): string
    {
        $name = Str::of(class_basename(static::class))
            ->beforeLast('Export')
            ->headline()
            ->toString();

        return "Data {$name}";
    }

    /**
     * Kalimat penjelas di bawah judul; null berarti baris ini dilewati.
     */
    public function subtitle(): ?string
    {
        return null;
    }

    /**
     * Label manusiawi per kolom, dikunci key mesin. Cukup isi kolom yang tebakan
     * otomatisnya kurang tepat — sisanya dirakit dari key lewat `Str::headline()`.
     *
     * @return array<string, string>
     */
    public function columnLabels(): array
    {
        return [];
    }

    /**
     * Tipe tampilan per kolom, dikunci key mesin. Kolom yang tidak disebut ditebak
     * dari namanya lewat {@see EnumFormatKolom::tebak()}.
     *
     * @return array<string, EnumFormatKolom>
     */
    public function columnFormats(): array
    {
        return [];
    }

    /**
     * Angka ringkas yang tampil sebagai kartu di atas tabel laporan PDF, urut
     * sesuai tampilan yang diinginkan: label => nilai yang sudah diformat.
     *
     * @return array<string, string>
     */
    public function summary(): array
    {
        return [];
    }

    /**
     * Metadata gabungan tiap kolom, urut sesuai {@see headings()}.
     *
     * @return list<array{key: string, label: string, format: EnumFormatKolom}>
     */
    public function columns(): array
    {
        $labels = $this->columnLabels();
        $formats = $this->columnFormats();

        return array_map(fn (string $key): array => [
            'key' => $key,
            'label' => $labels[$key] ?? Str::headline($key),
            'format' => $formats[$key] ?? EnumFormatKolom::tebak($key),
        ], $this->headings());
    }

    /**
     * Bahan blok kepala dokumen yang dipakai .xlsx maupun PDF.
     *
     * @return list<string>
     */
    public function headerLines(): array
    {
        return array_values(array_filter([
            $this->title(),
            $this->subtitle(),
        ]));
    }

    public function download(): BinaryFileResponse
    {
        return app(SpreadsheetExporter::class)->download($this);
    }
}
