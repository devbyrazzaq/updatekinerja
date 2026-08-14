<?php

namespace App\Imports;

use App\Exports\KategorisExport;
use App\Models\Kategori;

class KategorisImport extends Import
{
    /**
     * Label kolom mengikuti kelas ekspor pasangannya, supaya berkas hasil ekspor
     * bisa langsung disunting lalu diimpor kembali.
     */
    public function columnLabels(): array
    {
        return (new KategorisExport)->columnLabels();
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
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function templateColumns(): array
    {
        return ['code', 'name', 'description', 'is_active'];
    }

    public function sampleRows(): array
    {
        return [
            ['KAT-01', 'Pendidikan', 'Kategori kegiatan pendidikan', 1],
            ['KAT-02', 'Penelitian', 'Kategori kegiatan penelitian', 1],
        ];
    }

    public function templateFilename(): string
    {
        return 'template-impor-kategori';
    }

    public function storeRow(array $row): void
    {
        Kategori::updateOrCreate(
            ['code' => $row['code']],
            [
                'name' => $row['name'],
                'description' => $row['description'] ?? null,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ],
        );
    }
}
