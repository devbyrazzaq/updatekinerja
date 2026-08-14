<?php

namespace App\Imports;

use App\Exports\UnitKerjasExport;
use App\Models\UnitKerja;

class UnitKerjasImport extends Import
{
    /**
     * Label kolom mengikuti kelas ekspor pasangannya, supaya berkas hasil ekspor
     * bisa langsung disunting lalu diimpor kembali.
     */
    public function columnLabels(): array
    {
        return (new UnitKerjasExport)->columnLabels();
    }

    public function headings(): array
    {
        return ['name'];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function templateColumns(): array
    {
        return ['name', 'description', 'is_active'];
    }

    public function sampleRows(): array
    {
        return [
            ['Fakultas Teknik', 'Unit kerja fakultas teknik', 1],
            ['Biro Administrasi Umum', 'Unit kerja BAU', 1],
        ];
    }

    public function templateFilename(): string
    {
        return 'template-impor-unit-kerja';
    }

    public function storeRow(array $row): void
    {
        UnitKerja::updateOrCreate(
            ['name' => $row['name']],
            [
                'description' => $row['description'] ?? null,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ],
        );
    }
}
