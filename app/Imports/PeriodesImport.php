<?php

namespace App\Imports;

use App\Exports\PeriodesExport;
use App\Models\Periode;

class PeriodesImport extends Import
{
    /**
     * Label kolom mengikuti kelas ekspor pasangannya, supaya berkas hasil ekspor
     * bisa langsung disunting lalu diimpor kembali.
     */
    public function columnLabels(): array
    {
        return (new PeriodesExport)->columnLabels();
    }

    public function headings(): array
    {
        return ['name', 'start_datetime', 'end_datetime'];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_datetime' => ['required', 'date'],
            'end_datetime' => ['required', 'date', 'after:start_datetime'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function templateColumns(): array
    {
        return ['name', 'description', 'start_datetime', 'end_datetime', 'is_active'];
    }

    public function sampleRows(): array
    {
        return [
            ['Periode 2024-2028', 'Renstra lima tahunan', '2024-01-01 00:00:00', '2028-12-31 23:59:59', 1],
        ];
    }

    public function templateFilename(): string
    {
        return 'template-impor-periode';
    }

    public function storeRow(array $row): void
    {
        Periode::updateOrCreate(
            ['name' => $row['name']],
            [
                'description' => $row['description'] ?? null,
                'start_datetime' => $row['start_datetime'],
                'end_datetime' => $row['end_datetime'],
                'is_active' => (bool) ($row['is_active'] ?? false),
            ],
        );
    }
}
