<?php

namespace App\Imports;

use App\Exports\TemplateExport;
use App\Services\Excel\SpreadsheetExporter;
use App\Services\Excel\SpreadsheetImporter;
use Closure;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Kontrak dasar untuk seluruh impor spreadsheet. Turunan mendefinisikan judul
 * kolom yang diharapkan, aturan validasi per baris, dan cara menyimpan satu
 * baris; pembacaan berkas (.xlsx/.csv) ditangani {@see SpreadsheetImporter}.
 */
abstract class Import
{
    /**
     * Data tambahan dari form modal (mis. pilihan "Kategori Default") yang dapat
     * dijadikan atribut/rujukan saat menyimpan tiap baris.
     *
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * Judul kolom yang wajib ada pada baris pertama berkas.
     *
     * @return list<string>
     */
    abstract public function headings(): array;

    /**
     * Aturan validasi Laravel, dikunci berdasarkan judul kolom.
     *
     * @return array<string, mixed>
     */
    abstract public function rules(): array;

    /**
     * Simpan satu baris yang sudah tervalidasi.
     *
     * @param  array<string, mixed>  $row
     */
    abstract public function storeRow(array $row): void;

    /**
     * Label manusiawi tiap kolom, dikunci key mesin. Dipakai {@see SpreadsheetImporter}
     * sebagai nama alternatif kepala tabel supaya berkas hasil ekspor — yang menulis
     * label di atas key — tetap bisa diimpor kembali. Umumnya cukup meneruskan
     * `columnLabels()` milik kelas Export pasangannya.
     *
     * @return array<string, string>
     */
    public function columnLabels(): array
    {
        return [];
    }

    /**
     * Pesan validasi kustom untuk {@see rules()}, dikunci `kolom.aturan`.
     * Contoh: `['price.numeric' => 'Harga harus berupa angka.']`.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Nama atribut kustom untuk pesan validasi (mis. `['name' => 'nama produk']`).
     *
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [];
    }

    /**
     * Validasi bisnis tambahan per baris (mis. cek relasi ada di database).
     * Panggil `$fail('pesan kustom')` untuk menandai baris tidak valid; seluruh
     * impor akan dibatalkan bila ada satu saja kegagalan.
     *
     * @param  array<string, mixed>  $row
     */
    public function validateRow(array $row, int $rowNumber, Closure $fail): void
    {
        //
    }

    /**
     * Seluruh kolom yang dikenali (wajib + opsional) untuk berkas template,
     * urut sesuai tampilan yang diinginkan.
     *
     * @return list<string>
     */
    abstract public function templateColumns(): array;

    /**
     * Baris contoh pada template, nilainya sejajar dengan {@see templateColumns()}.
     *
     * @return list<array<int, mixed>>
     */
    abstract public function sampleRows(): array;

    public function templateFilename(): string
    {
        return 'template-impor';
    }

    /**
     * Isi konteks dari data form modal (di luar berkas unggahan).
     *
     * @param  array<string, mixed>  $context
     */
    public function withContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Ambil satu nilai konteks dari form modal.
     */
    public function context(string $key, mixed $default = null): mixed
    {
        return data_get($this->context, $key, $default);
    }

    /**
     * Validasi seluruh berkas tanpa menyimpan apa pun.
     *
     * @return list<string> daftar pesan error (kosong berarti valid)
     */
    public function validate(string $path): array
    {
        return app(SpreadsheetImporter::class)->validate($this, $path);
    }

    public function import(string $path): ImportResult
    {
        return app(SpreadsheetImporter::class)->import($this, $path);
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        return app(SpreadsheetExporter::class)->download(
            new TemplateExport(
                $this->templateFilename(),
                $this->templateColumns(),
                $this->sampleRows(),
                $this->columnLabels(),
            ),
        );
    }
}
