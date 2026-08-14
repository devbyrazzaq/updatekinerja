<?php

namespace App\Imports;

use App\Exports\RekeningsExport;
use App\Models\Rekening;
use App\Models\UnitKerja;

class RekeningsImport extends Import
{
    /**
     * Label kolom mengikuti kelas ekspor pasangannya, supaya berkas hasil ekspor
     * bisa langsung disunting lalu diimpor kembali.
     */
    public function columnLabels(): array
    {
        return (new RekeningsExport)->columnLabels();
    }

    public function headings(): array
    {
        return ['code', 'name'];
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'unit_kerja' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function templateColumns(): array
    {
        return ['code', 'unit_kerja', 'name', 'description', 'is_active'];
    }

    public function sampleRows(): array
    {
        return [
            ['5.1.02.01', 'Fakultas Teknik', 'Belanja ATK', 'Alat tulis kantor', 1],
            ['5.2.03.02', '', 'Belanja Perjalanan Dinas', 'Perjalanan dinas', 1],
        ];
    }

    public function templateFilename(): string
    {
        return 'template-impor-rekening';
    }

    public function storeRow(array $row): void
    {
        $unitKerjaId = filled($row['unit_kerja'] ?? null)
            ? UnitKerja::where('name', $row['unit_kerja'])->value('id')
            : null;

        Rekening::updateOrCreate(
            ['code' => $row['code']],
            [
                'unit_kerja_id' => $unitKerjaId,
                'name' => $row['name'],
                'description' => $row['description'] ?? null,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ],
        );
    }
}
