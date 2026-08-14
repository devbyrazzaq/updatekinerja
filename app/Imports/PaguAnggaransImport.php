<?php

namespace App\Imports;

use App\Exports\PaguAnggaransExport;
use App\Models\PaguAnggaran;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use Closure;

class PaguAnggaransImport extends Import
{
    /**
     * Label kolom mengikuti kelas ekspor pasangannya, supaya berkas hasil ekspor
     * bisa langsung disunting lalu diimpor kembali.
     */
    public function columnLabels(): array
    {
        return (new PaguAnggaransExport)->columnLabels();
    }

    public function headings(): array
    {
        return ['tahun_kerja', 'unit_kerja', 'amount'];
    }

    public function rules(): array
    {
        return [
            'tahun_kerja' => ['required', 'string'],
            'unit_kerja' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function validateRow(array $row, int $rowNumber, Closure $fail): void
    {
        if (TahunKerja::where('name', $row['tahun_kerja'] ?? null)->doesntExist()) {
            $fail("Tahun Kerja \"{$row['tahun_kerja']}\" tidak ditemukan.");
        }

        if (UnitKerja::where('name', $row['unit_kerja'] ?? null)->doesntExist()) {
            $fail("Unit Kerja \"{$row['unit_kerja']}\" tidak ditemukan.");
        }
    }

    public function templateColumns(): array
    {
        return ['tahun_kerja', 'unit_kerja', 'amount', 'description'];
    }

    public function sampleRows(): array
    {
        return [
            ['Tahun Kerja 2026', 'Fakultas Teknik', 50000000, 'Pagu tahun 2026'],
        ];
    }

    public function templateFilename(): string
    {
        return 'template-impor-pagu-anggaran';
    }

    public function storeRow(array $row): void
    {
        $tahunKerja = TahunKerja::where('name', $row['tahun_kerja'])->first();
        $unitKerja = UnitKerja::where('name', $row['unit_kerja'])->first();

        if ($tahunKerja === null || $unitKerja === null) {
            return;
        }

        PaguAnggaran::updateOrCreate(
            ['tahun_kerja_id' => $tahunKerja->id, 'unit_kerja_id' => $unitKerja->id],
            [
                'amount' => $row['amount'],
                'description' => $row['description'] ?? null,
            ],
        );
    }
}
