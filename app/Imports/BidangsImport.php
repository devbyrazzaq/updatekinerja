<?php

namespace App\Imports;

use App\Exports\BidangsExport;
use App\Models\Bidang;

class BidangsImport extends Import
{
    /**
     * Label kolom mengikuti kelas ekspor pasangannya, supaya berkas hasil ekspor
     * bisa langsung disunting lalu diimpor kembali.
     */
    public function columnLabels(): array
    {
        return (new BidangsExport)->columnLabels();
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
            ['BID-01', 'Akademik', 'Bidang akademik', 1],
            ['BID-02', 'Kemahasiswaan', 'Bidang kemahasiswaan', 1],
        ];
    }

    public function templateFilename(): string
    {
        return 'template-impor-bidang';
    }

    public function storeRow(array $row): void
    {
        Bidang::updateOrCreate(
            ['code' => $row['code']],
            [
                'name' => $row['name'],
                'description' => $row['description'] ?? null,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ],
        );
    }
}
