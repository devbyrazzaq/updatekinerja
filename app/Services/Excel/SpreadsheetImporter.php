<?php

namespace App\Services\Excel;

use App\Imports\Import;
use App\Imports\ImportResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Membaca berkas .xlsx/.csv memakai openspout, memvalidasi SELURUH baris terhadap
 * aturan sebuah {@see Import}, lalu menyimpannya. Bersifat all-or-nothing: bila ada
 * satu baris/kolom yang tidak valid, tidak ada baris yang disimpan.
 */
class SpreadsheetImporter
{
    /**
     * Baca, validasi, lalu simpan bila bersih.
     */
    public function import(Import $import, string $path): ImportResult
    {
        [$errors, $rows] = $this->readAndValidate($import, $path);

        // Pemeriksaan lintas baris baru berguna bila tiap barisnya sendiri sudah sah;
        // berkas yang sudah gagal tidak perlu ditimpali pesan tambahan.
        if ($errors === []) {
            $errors = $import->validateBatch($rows);
        }

        if ($errors !== []) {
            return new ImportResult(imported: 0, errors: $errors);
        }

        // Satu transaksi untuk seluruh berkas: kegagalan di tengah penyimpanan tidak
        // boleh meninggalkan sebagian baris, sesuai janji "bila ada satu saja yang
        // keliru, tidak ada yang tersimpan".
        DB::transaction(function () use ($import, $rows): void {
            foreach ($rows as $row) {
                $import->storeRow($row);
            }
        });

        return new ImportResult(imported: count($rows), warnings: $import->warnings());
    }

    /**
     * Validasi berkas tanpa menyimpan.
     *
     * @return list<string>
     */
    public function validate(Import $import, string $path): array
    {
        return $this->readAndValidate($import, $path)[0];
    }

    /**
     * @return array{0: list<string>, 1: list<array<string, mixed>>}
     */
    protected function readAndValidate(Import $import, string $path): array
    {
        $reader = $this->readerFor($path);
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                return $this->readSheet($import, $sheet->getRowIterator());
            }
        } finally {
            $reader->close();
        }

        return [[], []];
    }

    /**
     * Baris di atas kepala tabel (judul, keterangan cetak, baris kosong) dilewati
     * sampai ditemukan baris yang memuat seluruh kolom wajib. Berkas hasil ekspor
     * menulis kepala tabel dua tingkat — label manusiawi lalu key mesin — sehingga
     * tingkat kedua yang isinya setara ikut dilewati, bukan dibaca sebagai data.
     *
     * @param  iterable<int, Row>  $rows
     * @return array{0: list<string>, 1: list<array<string, mixed>>}
     */
    protected function readSheet(Import $import, iterable $rows): array
    {
        $errors = [];
        $validRows = [];
        $headerMap = null;
        $aliases = $this->aliasMap($import);

        foreach ($rows as $rowIndex => $row) {
            $cells = $row->toArray();

            if ($this->isBlankRow($cells)) {
                continue;
            }

            $candidate = $this->mapHeaders($cells, $aliases);

            if (array_diff($import->headings(), array_values($candidate)) === []) {
                $headerMap = $candidate;

                continue;
            }

            if ($headerMap === null) {
                continue;
            }

            $this->validateRow($import, $headerMap, $cells, (int) $rowIndex, $errors, $validRows);
        }

        if ($headerMap === null) {
            return [['Kolom wajib tidak ditemukan: '.implode(', ', $import->headings()).'.'], []];
        }

        return [$errors, $validRows];
    }

    /**
     * Penamaan alternatif tiap kolom yang dikenali — wajib maupun opsional: key
     * mesin apa adanya plus label manusiawi yang dipakai berkas ekspor, keduanya
     * dalam bentuk ternormalisasi.
     *
     * @return array<string, string> alias ternormalisasi => key mesin
     */
    protected function aliasMap(Import $import): array
    {
        $aliases = [];

        foreach ([...$import->headings(), ...$import->templateColumns()] as $key) {
            $aliases[$this->normalize($key)] = $key;
        }

        foreach ($import->columnLabels() as $key => $label) {
            $aliases[$this->normalize($label)] = $key;
        }

        return $aliases;
    }

    /**
     * @param  array<int, string>  $headerMap
     * @param  array<int, mixed>  $cells
     * @param  list<string>  $errors
     * @param  list<array<string, mixed>>  $validRows
     */
    protected function validateRow(Import $import, array $headerMap, array $cells, int $rowNumber, array &$errors, array &$validRows): void
    {
        $data = $this->rowToAssoc($headerMap, $cells);

        $validator = Validator::make(
            $data,
            $import->rules(),
            $import->messages(),
            $import->validationAttributes(),
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $errors[] = "Baris {$rowNumber}: {$message}";
            }

            return;
        }

        $validated = $validator->validated();
        $rowHadError = false;

        $import->validateRow($validated, $rowNumber, function (string $message) use (&$errors, &$rowHadError): void {
            $errors[] = $message;
            $rowHadError = true;
        });

        if (! $rowHadError) {
            $validRows[] = $validated;
        }
    }

    /**
     * @param  array<int, string>  $headerMap
     * @param  array<int, mixed>  $cells
     * @return array<string, mixed>
     */
    protected function rowToAssoc(array $headerMap, array $cells): array
    {
        $data = [];

        foreach ($headerMap as $index => $key) {
            if ($key === '') {
                continue;
            }

            $value = $cells[$index] ?? null;

            if (is_string($value)) {
                $value = trim($value);
                $value = $value === '' ? null : $value;
            }

            $data[$key] = $value;
        }

        return $data;
    }

    /**
     * Terjemahkan sel-sel satu baris menjadi key mesin. Sel yang tidak dikenali
     * dibiarkan dalam bentuk ternormalisasi supaya baris itu gagal dianggap kepala
     * tabel.
     *
     * @param  array<int, mixed>  $cells
     * @param  array<string, string>  $aliases
     * @return array<int, string>
     */
    protected function mapHeaders(array $cells, array $aliases): array
    {
        return array_map(function ($value) use ($aliases): string {
            $normalized = $this->normalize((string) $value);

            // Kolom di luar daftar yang dikenali tetap dijadikan bentuk key mesin,
            // supaya kolom tambahan pada berkas pengguna terbaca seperti sebelumnya.
            return $aliases[$normalized] ?? str_replace(' ', '_', $normalized);
        }, $cells);
    }

    /**
     * Bentuk banding sebuah judul kolom: huruf kecil, tanpa tanda baca, spasi
     * tunggal — sehingga `unit_kerja` dan "Unit Kerja" bertemu di titik yang sama.
     */
    protected function normalize(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }

    /**
     * @param  array<int, mixed>  $cells
     */
    protected function isBlankRow(array $cells): bool
    {
        foreach ($cells as $value) {
            if (! blank(is_string($value) ? trim($value) : $value)) {
                return false;
            }
        }

        return true;
    }

    protected function readerFor(string $path): ReaderInterface
    {
        return str(pathinfo($path, PATHINFO_EXTENSION))->lower()->is('csv')
            ? new CsvReader
            : new XlsxReader;
    }
}
