<?php

namespace App\Imports;

use App\Exports\TahunKerjasExport;
use App\Models\Periode;
use App\Models\TahunKerja;
use Closure;

class TahunKerjasImport extends Import
{
    /**
     * Label kolom mengikuti kelas ekspor pasangannya, supaya berkas hasil ekspor
     * bisa langsung disunting lalu diimpor kembali.
     */
    public function columnLabels(): array
    {
        return (new TahunKerjasExport)->columnLabels();
    }

    public function headings(): array
    {
        return ['periode', 'name', 'tahun', 'start_datetime', 'end_datetime'];
    }

    public function rules(): array
    {
        return [
            'periode' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'description' => ['nullable', 'string'],
            'start_datetime' => ['required', 'date'],
            'end_datetime' => ['required', 'date', 'after:start_datetime'],
        ];
    }

    public function validateRow(array $row, int $rowNumber, Closure $fail): void
    {
        if (Periode::where('name', $row['periode'] ?? null)->doesntExist()) {
            $fail("Periode \"{$row['periode']}\" tidak ditemukan.");
        }
    }

    public function templateColumns(): array
    {
        return ['periode', 'name', 'tahun', 'description', 'start_datetime', 'end_datetime'];
    }

    public function sampleRows(): array
    {
        return [
            ['Periode 2024-2028', 'Tahun Kerja 2026', 2026, 'Tahun anggaran 2026', '2026-01-01 00:00:00', '2026-12-31 23:59:59'],
        ];
    }

    public function templateFilename(): string
    {
        return 'template-impor-tahun-kerja';
    }

    /**
     * Status siklus hidup sengaja tidak ikut diimpor. Slot berjalan dan slot
     * perencanaan hanya boleh dipegang satu tahun kerja, sehingga penempatannya
     * ditempuh lewat halaman Pengaturan Program Kerja — bukan lewat berkas yang bisa
     * saja memuat dua baris berstatus sama. Tahun kerja baru masuk sebagai Selesai
     * dan status tahun yang sudah ada dibiarkan apa adanya.
     */
    public function storeRow(array $row): void
    {
        $periode = Periode::where('name', $row['periode'])->first();

        if ($periode === null) {
            return;
        }

        TahunKerja::updateOrCreate(
            ['periode_id' => $periode->id, 'name' => $row['name']],
            [
                'tahun' => (int) $row['tahun'],
                'description' => $row['description'] ?? null,
                'start_datetime' => $row['start_datetime'],
                'end_datetime' => $row['end_datetime'],
            ],
        );
    }
}
